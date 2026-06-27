// [IN]: Bootstrap payload and local member registration actions / 初始化数据与会员本地报名动作
// [OUT]: Matrix state, row select-all, repeat-aware unique multi groups, per-project group counts, summaries, drafts, and submit payload / 矩阵状态、行全选、支持重复规则且唯一的多羽组合、按项目成组数、汇总、草稿与提交数据
// [POS]: Frontend registration state machine / 前端报名状态机
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md
import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import type { BootstrapPayload, ExistingRegistration, Pigeon, RaceProject, RegistrationEntryPayload } from '../types/domain'

export interface MultiGroup {
  id: string
  project_id: number
  pigeon_ids: number[]
}

interface DraftPayload {
  raceId: number
  memberId: number
  configVersion: number
  singleMatrix: Record<number, Record<number, boolean>>
  multiGroups: MultiGroup[]
  updatedAt: string
}

export const useRegistrationStore = defineStore('registration', () => {
  const bootstrap = ref<BootstrapPayload | null>(null)
  const activeTab = ref<'single' | 'multi' | 'detail'>('single')
  const searchQuery = ref('')
  const selectedMultiProjectId = ref<number | null>(null)
  const pendingMultiPigeonIds = ref<number[]>([])
  const singleMatrix = ref<Record<number, Record<number, boolean>>>({})
  const multiGroups = ref<MultiGroup[]>([])

  const race = computed(() => bootstrap.value?.race ?? null)
  const member = computed(() => bootstrap.value?.member ?? null)
  const projects = computed(() => bootstrap.value?.projects ?? [])
  const pigeons = computed(() => bootstrap.value?.pigeons ?? [])
  const singleProjects = computed(() => projects.value.filter((project) => project.group_size === 1))
  const multiProjects = computed(() => projects.value.filter((project) => project.group_size > 1))
  const selectedMultiProject = computed(() => multiProjects.value.find((project) => project.id === selectedMultiProjectId.value) ?? null)

  const filteredPigeons = computed(() => {
    const query = searchQuery.value.trim().toLowerCase()
    if (!query) return pigeons.value
    return pigeons.value.filter((pigeon) => pigeon.ring_number.toLowerCase().includes(query))
  })

  const singleEntries = computed<RegistrationEntryPayload[]>(() => {
    const entries: RegistrationEntryPayload[] = []
    for (const pigeon of pigeons.value) {
      for (const project of singleProjects.value) {
        if (singleMatrix.value[pigeon.id]?.[project.id]) {
          entries.push({ project_id: project.id, pigeon_ids: [pigeon.id] })
        }
      }
    }
    return entries
  })

  const multiEntries = computed<RegistrationEntryPayload[]>(() => multiGroups.value.map((group) => ({
    project_id: group.project_id,
    pigeon_ids: [...group.pigeon_ids],
  })))

  const selectedMultiProjectUsage = computed<Record<number, number>>(() => {
    const project = selectedMultiProject.value
    if (!project) return {}

    return multiGroups.value
      .filter((group) => group.project_id === project.id)
      .flatMap((group) => group.pigeon_ids)
      .reduce<Record<number, number>>((usage, pigeonId) => {
        usage[pigeonId] = (usage[pigeonId] ?? 0) + 1
        return usage
      }, {})
  })

  const selectedMultiProjectGroupCount = computed(() => {
    const project = selectedMultiProject.value
    if (!project) return 0

    return multiGroups.value.filter((group) => group.project_id === project.id).length
  })

  const canConfirmMultiGroup = computed(() => {
    const project = selectedMultiProject.value
    if (!project || pendingMultiPigeonIds.value.length !== project.group_size) return false
    if (hasSameMultiGroup(project.id, pendingMultiPigeonIds.value)) return false
    return pendingMultiPigeonIds.value.every((pigeonId) => canUsePigeonInSelectedProject(pigeonId, true))
  })

  const submitEntries = computed<RegistrationEntryPayload[]>(() => [...singleEntries.value, ...multiEntries.value])

  const singleAmountCent = computed(() => singleEntries.value.reduce((sum, entry) => sum + priceFor(entry.project_id), 0))
  const multiAmountCent = computed(() => multiEntries.value.reduce((sum, entry) => sum + priceFor(entry.project_id), 0))
  const totalAmountCent = computed(() => singleAmountCent.value + multiAmountCent.value)
  const selectedCount = computed(() => submitEntries.value.length)

  const singleProjectStats = computed(() => singleProjects.value.map((project) => {
    const count = singleEntries.value.filter((entry) => entry.project_id === project.id).length
    return { project, count, amount_cent: count * project.price_cent }
  }))

  function load(payload: BootstrapPayload): void {
    bootstrap.value = payload
    singleMatrix.value = {}
    multiGroups.value = []
    selectedMultiProjectId.value = multiProjects.value[0]?.id ?? null
    hydrateExisting(payload.existing_registration)
    restoreDraftIfFresh()
  }

  function toggleSingle(pigeonId: number, projectId: number): void {
    const project = requireProject(projectId)
    if (project.group_size !== 1) return
    singleMatrix.value[pigeonId] = { ...(singleMatrix.value[pigeonId] ?? {}) }
    singleMatrix.value[pigeonId][projectId] = !singleMatrix.value[pigeonId][projectId]
    saveDraft()
  }

  function isSingleRowAllSelected(pigeonId: number): boolean {
    return singleProjects.value.length > 0 && singleProjects.value.every((project) => singleMatrix.value[pigeonId]?.[project.id])
  }

  function toggleSingleRowAll(pigeonId: number): void {
    const nextSelected = !isSingleRowAllSelected(pigeonId)
    singleMatrix.value[pigeonId] = { ...(singleMatrix.value[pigeonId] ?? {}) }

    for (const project of singleProjects.value) {
      singleMatrix.value[pigeonId][project.id] = nextSelected
    }

    saveDraft()
  }

  function setMultiProject(projectId: number): void {
    const project = requireProject(projectId)
    if (project.group_size <= 1) return
    selectedMultiProjectId.value = project.id
    pendingMultiPigeonIds.value = []
  }

  function togglePendingMultiPigeon(pigeonId: number): void {
    const project = selectedMultiProject.value
    if (!project) return
    const existingIndex = pendingMultiPigeonIds.value.indexOf(pigeonId)
    if (existingIndex >= 0) {
      pendingMultiPigeonIds.value.splice(existingIndex, 1)
      return
    }
    if (pendingMultiPigeonIds.value.length >= project.group_size) return
    if (!canUsePigeonInSelectedProject(pigeonId, false)) return
    pendingMultiPigeonIds.value.push(pigeonId)
  }

  function confirmMultiGroup(): void {
    const project = selectedMultiProject.value
    if (!project || !canConfirmMultiGroup.value) return
    if (hasSameMultiGroup(project.id, pendingMultiPigeonIds.value)) return
    if (!project.allow_repeat_pigeon_in_project && hasProjectPigeonOverlap(project.id, pendingMultiPigeonIds.value)) return

    multiGroups.value.push({
      id: `${project.id}-${Date.now()}-${Math.random().toString(16).slice(2)}`,
      project_id: project.id,
      pigeon_ids: [...pendingMultiPigeonIds.value],
    })
    pendingMultiPigeonIds.value = []
    saveDraft()
  }

  function deleteMultiGroup(groupId: string): void {
    multiGroups.value = multiGroups.value.filter((group) => group.id !== groupId)
    saveDraft()
  }

  function priceFor(projectId: number): number {
    return requireProject(projectId).price_cent
  }

  function projectName(projectId: number): string {
    return requireProject(projectId).name
  }

  function pigeonById(pigeonId: number): Pigeon {
    const pigeon = pigeons.value.find((candidate) => candidate.id === pigeonId)
    if (!pigeon) throw new Error(`Unknown pigeon ${pigeonId}`)
    return pigeon
  }

  function requireProject(projectId: number): RaceProject {
    const project = projects.value.find((candidate) => candidate.id === projectId)
    if (!project) throw new Error(`Unknown project ${projectId}`)
    return project
  }

  function hasProjectPigeonOverlap(projectId: number, pigeonIds: number[]): boolean {
    const used = new Set(multiGroups.value.filter((group) => group.project_id === projectId).flatMap((group) => group.pigeon_ids))
    return pigeonIds.some((pigeonId) => used.has(pigeonId))
  }

  function hasSameMultiGroup(projectId: number, pigeonIds: number[]): boolean {
    const signature = groupSignature(pigeonIds)
    return multiGroups.value.some((group) => group.project_id === projectId && groupSignature(group.pigeon_ids) === signature)
  }

  function groupSignature(pigeonIds: number[]): string {
    return [...pigeonIds].sort((left, right) => left - right).join(':')
  }

  function canUsePigeonInSelectedProject(pigeonId: number, alreadyPending: boolean = pendingMultiPigeonIds.value.includes(pigeonId)): boolean {
    const project = selectedMultiProject.value
    if (!project) return false
    if (alreadyPending) return true

    const currentUsage = selectedMultiProjectUsage.value[pigeonId] ?? 0
    if (!project.allow_repeat_pigeon_in_project && currentUsage > 0) return false
    if (project.max_usage_per_pigeon !== null && project.max_usage_per_pigeon !== undefined && currentUsage >= project.max_usage_per_pigeon) return false

    return true
  }

  function hydrateExisting(existing: ExistingRegistration | null): void {
    if (!existing) return
    for (const entry of existing.entries) {
      if (entry.group_size === 1) {
        const pigeonId = entry.pigeons[0]?.pigeon_id
        if (pigeonId) {
          singleMatrix.value[pigeonId] = { ...(singleMatrix.value[pigeonId] ?? {}), [entry.project_id]: true }
        }
      } else {
        multiGroups.value.push({
          id: `existing-${entry.project_id}-${entry.group_index}`,
          project_id: entry.project_id,
          pigeon_ids: entry.pigeons.map((pigeon) => pigeon.pigeon_id),
        })
      }
    }
  }

  function draftKey(): string | null {
    if (!race.value || !member.value) return null
    return `pigeon-registration-draft:${race.value.id}:${member.value.id}`
  }

  function saveDraft(): void {
    const key = draftKey()
    if (!key || !race.value || !member.value) return
    const payload: DraftPayload = {
      raceId: race.value.id,
      memberId: member.value.id,
      configVersion: race.value.config_version,
      singleMatrix: singleMatrix.value,
      multiGroups: multiGroups.value,
      updatedAt: new Date().toISOString(),
    }
    localStorage.setItem(key, JSON.stringify(payload))
  }

  function restoreDraftIfFresh(): void {
    const key = draftKey()
    if (!key || !race.value) return
    const raw = localStorage.getItem(key)
    if (!raw) return
    const draft = JSON.parse(raw) as DraftPayload
    if (draft.configVersion !== race.value.config_version) {
      localStorage.removeItem(key)
      return
    }
    singleMatrix.value = draft.singleMatrix
    multiGroups.value = draft.multiGroups
  }

  return {
    bootstrap,
    activeTab,
    searchQuery,
    selectedMultiProjectId,
    pendingMultiPigeonIds,
    singleMatrix,
    multiGroups,
    race,
    member,
    projects,
    pigeons,
    singleProjects,
    multiProjects,
    selectedMultiProject,
    filteredPigeons,
    singleEntries,
    multiEntries,
    selectedMultiProjectUsage,
    selectedMultiProjectGroupCount,
    canConfirmMultiGroup,
    submitEntries,
    singleAmountCent,
    multiAmountCent,
    totalAmountCent,
    selectedCount,
    singleProjectStats,
    load,
    toggleSingle,
    isSingleRowAllSelected,
    toggleSingleRowAll,
    setMultiProject,
    togglePendingMultiPigeon,
    confirmMultiGroup,
    canUsePigeonInSelectedProject,
    deleteMultiGroup,
    priceFor,
    projectName,
    pigeonById,
  }
})
