// [IN]: Pinia registration store and fake bootstrap data / Pinia 报名 Store 与模拟初始化数据
// [OUT]: Local registration, database restore, draft priority, reset, and unique group assertions / 本地报名、数据库恢复、草稿优先级、重置与唯一组合断言
// [POS]: Frontend registration store tests / 前端报名状态测试
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useRegistrationStore } from '../stores/registration'
import type { BootstrapPayload } from '../types/domain'

describe('registration store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    const memory = new Map<string, string>()
    vi.stubGlobal('localStorage', {
      getItem: (key: string) => memory.get(key) ?? null,
      setItem: (key: string, value: string) => memory.set(key, value),
      removeItem: (key: string) => memory.delete(key),
      clear: () => memory.clear(),
    })
  })

  it('keeps single matrix selection after search changes', () => {
    const store = useRegistrationStore()
    store.load(bootstrap())
    store.toggleSingle(101, 1)
    store.searchQuery = '999'
    store.searchQuery = ''

    expect(store.singleMatrix[101][1]).toBe(true)
    expect(store.submitEntries).toEqual([{ project_id: 1, pigeon_ids: [101] }])
  })

  it('toggles every single project for one pigeon from the row select-all control', () => {
    const store = useRegistrationStore()
    const payload = bootstrap()
    payload.projects.splice(1, 0, { id: 3, race_id: 1, name: '单羽 100 元', group_size: 1, price_cent: 10000, sort_order: 2, is_enabled: true, allow_repeat_pigeon_in_project: false })
    store.load(payload)

    store.toggleSingleRowAll(101)

    expect(store.isSingleRowAllSelected(101)).toBe(true)
    expect(store.submitEntries).toEqual([
      { project_id: 1, pigeon_ids: [101] },
      { project_id: 3, pigeon_ids: [101] },
    ])

    store.toggleSingleRowAll(101)
    expect(store.submitEntries).toEqual([])
  })

  it('selects only available single projects for a pigeon library', () => {
    const store = useRegistrationStore()
    const payload = bootstrap()
    payload.projects.splice(1, 0, { id: 3, race_id: 1, pigeon_library_id: 2, name: '二关单羽', group_size: 1, price_cent: 8000, sort_order: 2, is_enabled: true, allow_repeat_pigeon_in_project: false })
    payload.pigeon_libraries = [
      { id: 1, name: '一关库', pigeon_count: 2, pigeons: payload.pigeons.filter((pigeon) => pigeon.pigeon_library_id === 1) },
      { id: 2, name: '二关库', pigeon_count: 1, pigeons: payload.pigeons.filter((pigeon) => pigeon.pigeon_library_id === 2) },
    ]
    store.load(payload)

    expect(store.isSingleProjectAvailable(101, 1)).toBe(true)
    expect(store.isSingleProjectAvailable(101, 3)).toBe(false)

    store.toggleSingleRowAll(101)

    expect(store.submitEntries).toEqual([{ project_id: 1, pigeon_ids: [101] }])
  })

  it('creates multi group only when enough pigeons are selected', () => {
    const store = useRegistrationStore()
    store.load(bootstrap())
    store.setMultiProject(2)
    store.togglePendingMultiPigeon(101)
    store.confirmMultiGroup()
    expect(store.multiGroups).toHaveLength(0)

    store.togglePendingMultiPigeon(102)
    store.confirmMultiGroup()
    expect(store.multiGroups).toHaveLength(1)
    expect(store.multiEntries).toEqual([{ project_id: 2, pigeon_ids: [101, 102] }])
    expect(store.selectedMultiProjectGroupCount).toBe(1)
  })

  it('filters multi pigeons by the selected project library', () => {
    const store = useRegistrationStore()
    const payload = bootstrap()
    payload.projects[1].pigeon_library_id = 2
    store.load(payload)
    store.activeTab = 'multi'
    store.setMultiProject(2)

    expect(store.filteredPigeons.map((pigeon) => pigeon.id)).toEqual([888])
    expect(store.canUsePigeonInSelectedProject(101)).toBe(false)
  })

  it('counts confirmed groups for the selected multi project only', () => {
    const store = useRegistrationStore()
    const payload = bootstrap()
    payload.projects.push({ id: 3, race_id: 1, name: '三羽组 300 元', group_size: 3, price_cent: 30000, sort_order: 3, is_enabled: true, allow_repeat_pigeon_in_project: false })
    store.load(payload)

    store.setMultiProject(2)
    store.togglePendingMultiPigeon(101)
    store.togglePendingMultiPigeon(102)
    store.confirmMultiGroup()
    expect(store.selectedMultiProjectGroupCount).toBe(1)

    store.setMultiProject(3)
    expect(store.selectedMultiProjectGroupCount).toBe(0)
  })

  it('allows the same pigeon in another multi group when the project permits repeat usage', () => {
    const store = useRegistrationStore()
    const payload = bootstrap()
    payload.projects[1].allow_repeat_pigeon_in_project = true
    store.load(payload)
    store.setMultiProject(2)

    store.togglePendingMultiPigeon(101)
    store.togglePendingMultiPigeon(102)
    store.confirmMultiGroup()
    store.togglePendingMultiPigeon(101)
    store.togglePendingMultiPigeon(999)
    store.confirmMultiGroup()

    expect(store.multiEntries).toEqual([
      { project_id: 2, pigeon_ids: [101, 102] },
      { project_id: 2, pigeon_ids: [101, 999] },
    ])
  })

  it('blocks identical multi group when repeat usage is enabled', () => {
    const store = useRegistrationStore()
    const payload = bootstrap()
    payload.projects[1].allow_repeat_pigeon_in_project = true
    store.load(payload)
    store.setMultiProject(2)

    store.togglePendingMultiPigeon(101)
    store.togglePendingMultiPigeon(102)
    store.confirmMultiGroup()
    store.togglePendingMultiPigeon(102)
    store.togglePendingMultiPigeon(101)

    expect(store.canConfirmMultiGroup).toBe(false)
    store.confirmMultiGroup()
    expect(store.multiEntries).toEqual([{ project_id: 2, pigeon_ids: [101, 102] }])
  })

  it('blocks the same pigeon in another multi group when repeat usage is disabled', () => {
    const store = useRegistrationStore()
    store.load(bootstrap())
    store.setMultiProject(2)

    store.togglePendingMultiPigeon(101)
    store.togglePendingMultiPigeon(102)
    store.confirmMultiGroup()
    store.togglePendingMultiPigeon(101)
    store.togglePendingMultiPigeon(999)
    store.confirmMultiGroup()

    expect(store.multiEntries).toEqual([{ project_id: 2, pigeon_ids: [101, 102] }])
  })

  it('blocks repeat usage after max usage per pigeon is reached', () => {
    const store = useRegistrationStore()
    const payload = bootstrap()
    payload.projects[1].allow_repeat_pigeon_in_project = true
    payload.projects[1].max_usage_per_pigeon = 1
    store.load(payload)
    store.setMultiProject(2)

    store.togglePendingMultiPigeon(101)
    store.togglePendingMultiPigeon(102)
    store.confirmMultiGroup()

    expect(store.canUsePigeonInSelectedProject(101)).toBe(false)
  })

  it('calculates total amount from selected projects', () => {
    const store = useRegistrationStore()
    store.load(bootstrap())
    store.toggleSingle(101, 1)
    store.setMultiProject(2)
    store.togglePendingMultiPigeon(101)
    store.togglePendingMultiPigeon(102)
    store.confirmMultiGroup()

    expect(store.singleAmountCent).toBe(5000)
    expect(store.multiAmountCent).toBe(20000)
    expect(store.totalAmountCent).toBe(25000)
  })

  it('submits progressive current-stage selections with amount', () => {
    const store = useRegistrationStore()
    const payload = bootstrap()
    payload.progressive_categories = [{
      id: 10,
      name: '站站赛',
      sort_order: 1,
      current_stage: { id: 30, name: '平阳 1.5K', price_cent: 150000, group_size: 1, stage_order: 2, sort_order: 3 },
      eligible_pigeons: [{ id: 101, ring_number: 'CHN-2026-03-000101' }],
      selected_pigeon_ids: [],
      status: null,
    }]
    store.load(payload)

    store.toggleProgressiveGroup(10, '101')
    store.toggleProgressiveGroup(10, '102')

    expect(store.progressiveSelections[10]).toEqual([{ group_key: '101', pigeon_ids: [101] }])
    expect(store.submitProgressiveEntries).toEqual([{ category_id: 10, stage_project_id: 30, groups: [{ pigeon_ids: [101] }] }])
    expect(store.progressiveAmountCent).toBe(150000)
    expect(store.totalAmountCent).toBe(150000)
  })

  it('restores progressive current-stage selections from bootstrap', () => {
    const store = useRegistrationStore()
    const payload = bootstrap()
    payload.progressive_categories = [{
      id: 10,
      name: '站站赛',
      sort_order: 1,
      current_stage: { id: 30, name: '平阳 1.5K', price_cent: 150000, group_size: 1, stage_order: 2, sort_order: 3 },
      eligible_pigeons: [{ id: 101, ring_number: 'CHN-2026-03-000101' }],
      selected_pigeon_ids: [101],
      status: 'pending_confirmation',
    }]

    store.load(payload)

    expect(store.progressiveSelections[10]).toEqual([{ group_key: '101', pigeon_ids: [101] }])
    expect(store.submitProgressiveEntries).toEqual([{ category_id: 10, stage_project_id: 30, groups: [{ pigeon_ids: [101] }] }])
  })

  it('submits and restores progressive multi-pigeon groups', () => {
    const store = useRegistrationStore()
    const payload = bootstrap()
    payload.progressive_categories = [{
      id: 10,
      name: '站站赛',
      sort_order: 1,
      current_stage: { id: 30, name: '丽水 1K', price_cent: 100000, group_size: 3, stage_order: 2, sort_order: 3 },
      eligible_groups: [{
        group_key: '101:102:103',
        group_index: 1,
        pigeon_ids: [101, 102, 103],
        pigeons: [
          { id: 101, ring_number: 'CHN-2026-03-000101', sort_order: 1 },
          { id: 102, ring_number: 'CHN-2026-03-000102', sort_order: 2 },
          { id: 103, ring_number: 'CHN-2026-03-000103', sort_order: 3 },
        ],
      }],
      eligible_pigeons: [],
      selected_groups: [{
        group_key: '101:102:103',
        group_index: 1,
        pigeon_ids: [101, 102, 103],
        pigeons: [
          { id: 101, ring_number: 'CHN-2026-03-000101', sort_order: 1 },
          { id: 102, ring_number: 'CHN-2026-03-000102', sort_order: 2 },
          { id: 103, ring_number: 'CHN-2026-03-000103', sort_order: 3 },
        ],
      }],
      selected_pigeon_ids: [],
      status: 'pending_confirmation',
    }]

    store.load(payload)

    expect(store.progressiveSelections[10]).toEqual([{ group_key: '101:102:103', pigeon_ids: [101, 102, 103] }])
    expect(store.submitProgressiveEntries).toEqual([{ category_id: 10, stage_project_id: 30, groups: [{ pigeon_ids: [101, 102, 103] }] }])
    expect(store.progressiveAmountCent).toBe(100000)
  })

  it('drops stale local draft when config version changes', () => {
    const store = useRegistrationStore()
    store.load(bootstrap())
    store.toggleSingle(101, 1)

    const next = bootstrap()
    next.race.config_version = 2
    store.load(next)

    expect(store.submitEntries).toEqual([])
  })

  it('restores submitted registration from bootstrap for cross-browser recovery', () => {
    const store = useRegistrationStore()
    const payload = bootstrap()
    payload.existing_registration = existingRegistration('2026-06-29 10:00:00')

    store.load(payload)

    expect(store.singleMatrix[101][1]).toBe(true)
    expect(store.multiEntries).toEqual([{ project_id: 2, pigeon_ids: [101, 102] }])
    expect(store.submitEntries).toEqual([
      { project_id: 1, pigeon_ids: [101] },
      { project_id: 2, pigeon_ids: [101, 102] },
    ])
  })

  it('keeps submitted registration when local draft is older than database submission', () => {
    const store = useRegistrationStore()
    const first = bootstrap()
    store.load(first)
    store.toggleSingle(999, 1)

    const next = bootstrap()
    next.existing_registration = existingRegistration('2026-06-29 10:00:00')
    localStorage.setItem('pigeon-registration-draft:1:1', JSON.stringify({
      raceId: 1,
      memberId: 1,
      configVersion: 1,
      singleMatrix: { 999: { 1: true } },
      multiGroups: [],
      updatedAt: '2026-06-29T01:59:00.000Z',
    }))

    store.load(next)

    expect(store.submitEntries).toEqual([
      { project_id: 1, pigeon_ids: [101] },
      { project_id: 2, pigeon_ids: [101, 102] },
    ])
    expect(localStorage.getItem('pigeon-registration-draft:1:1')).toBeNull()
  })

  it('restores newer local draft over existing registration for unsent same-browser edits', () => {
    const store = useRegistrationStore()
    const payload = bootstrap()
    payload.existing_registration = existingRegistration('2026-06-29 10:00:00')
    localStorage.setItem('pigeon-registration-draft:1:1', JSON.stringify({
      raceId: 1,
      memberId: 1,
      configVersion: 1,
      singleMatrix: { 999: { 1: true } },
      multiGroups: [],
      updatedAt: '2026-06-29T10:01:00.000Z',
    }))

    store.load(payload)

    expect(store.submitEntries).toEqual([{ project_id: 1, pigeon_ids: [999] }])
  })

  it('drops invalid local draft and keeps database registration', () => {
    const store = useRegistrationStore()
    const payload = bootstrap()
    payload.existing_registration = existingRegistration('2026-06-29 10:00:00')
    localStorage.setItem('pigeon-registration-draft:1:1', '{bad-json')

    store.load(payload)

    expect(store.submitEntries).toEqual([
      { project_id: 1, pigeon_ids: [101] },
      { project_id: 2, pigeon_ids: [101, 102] },
    ])
    expect(localStorage.getItem('pigeon-registration-draft:1:1')).toBeNull()
  })

  it('resets runtime state without deleting saved drafts', () => {
    const store = useRegistrationStore()
    store.load(bootstrap())
    store.toggleSingle(101, 1)

    store.resetRuntimeState()

    expect(store.bootstrap).toBeNull()
    expect(store.submitEntries).toEqual([])
    expect(localStorage.getItem('pigeon-registration-draft:1:1')).not.toBeNull()
  })
})

function bootstrap(): BootstrapPayload {
  return {
    race: {
      id: 1,
      name: '2026 春季大奖赛',
      registration_end_at: '2026-05-01 18:00:00',
      status: 'open',
      config_version: 1,
      allow_member_edit: true,
    },
    member: { id: 1, loft_number: 'A001', participant_name: '张三鸽舍', must_change_password: false },
    projects: [
      { id: 1, race_id: 1, name: '单羽 50 元', group_size: 1, price_cent: 5000, sort_order: 1, is_enabled: true, allow_repeat_pigeon_in_project: false },
      { id: 2, race_id: 1, name: '双羽组 200 元', group_size: 2, price_cent: 20000, sort_order: 2, is_enabled: true, allow_repeat_pigeon_in_project: false },
    ],
    pigeons: [
      { id: 101, pigeon_library_id: 1, ring_number: 'CHN-2026-03-000101' },
      { id: 102, pigeon_library_id: 1, ring_number: 'CHN-2026-03-000102' },
      { id: 999, pigeon_library_id: 1, ring_number: 'CHN-2026-03-000999' },
      { id: 888, pigeon_library_id: 2, ring_number: 'CHN-2026-03-000888' },
    ],
    pigeon_libraries: [
      { id: 1, name: '默认足环库', pigeon_count: 2, pigeons: [
        { id: 101, pigeon_library_id: 1, ring_number: 'CHN-2026-03-000101' },
        { id: 102, pigeon_library_id: 1, ring_number: 'CHN-2026-03-000102' },
      ] },
      { id: 2, name: '二关库', pigeon_count: 1, pigeons: [
        { id: 888, pigeon_library_id: 2, ring_number: 'CHN-2026-03-000888' },
      ] },
    ],
    existing_registration: null,
  }
}

function existingRegistration(submittedAt: string) {
  return {
    id: 10,
    registration_no: 'R1-A001',
    status: 'submitted',
    total_amount_cent: 25000,
    submitted_at: submittedAt,
    entries: [
      {
        project_id: 1,
        project_name: '单羽 50 元',
        group_size: 1,
        price_cent: 5000,
        group_index: 1,
        pigeons: [{ pigeon_id: 101, ring_number: 'CHN-2026-03-000101', sort_order: 1 }],
      },
      {
        project_id: 2,
        project_name: '双羽组 200 元',
        group_size: 2,
        price_cent: 20000,
        group_index: 1,
        pigeons: [
          { pigeon_id: 101, ring_number: 'CHN-2026-03-000101', sort_order: 1 },
          { pigeon_id: 102, ring_number: 'CHN-2026-03-000102', sort_order: 2 },
        ],
      },
    ],
  }
}
