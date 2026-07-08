// [IN]: Bootstrap payload and local member registration actions / 初始化数据与会员本地报名动作
// [OUT]: Database-prioritized matrix restore, progressive stages, drafts, repeat-aware groups, summaries, and submit payload / 数据库优先矩阵恢复、递进阶段、草稿、支持重复规则的组合、汇总与提交数据
// [POS]: Frontend registration state machine / 前端报名状态机
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md
import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import type { BootstrapPayload, ExistingRegistration, Pigeon, ProgressiveCategory, ProgressiveRegistrationEntryPayload, ProgressiveStageGroup, RaceProject, RegistrationEntryPayload } from '../types/domain'

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
  progressiveSelections: Record<number, ProgressiveSelectionGroup[]>
  updatedAt: string
}

export interface ProgressiveSelectionGroup {
  group_key: string
  pigeon_ids: number[]
}

export const useRegistrationStore = defineStore('registration', () => {
  const bootstrap = ref<BootstrapPayload | null>(null)
  const activeTab = ref<string>('single')
  const searchQuery = ref('')
  const selectedMultiProjectId = ref<number | null>(null)
  const pendingMultiPigeonIds = ref<number[]>([])
  const singleMatrix = ref<Record<number, Record<number, boolean>>>({})
  const multiGroups = ref<MultiGroup[]>([])
  const progressiveSelections = ref<Record<number, ProgressiveSelectionGroup[]>>({})

  const race = computed(() => bootstrap.value?.race ?? null)
  const member = computed(() => bootstrap.value?.member ?? null)
  const projects = computed(() => bootstrap.value?.projects ?? [])
  const pigeons = computed(() => bootstrap.value?.pigeons ?? [])
  const pigeonLibraries = computed(() => bootstrap.value?.pigeon_libraries ?? [])
  const progressiveCategories = computed(() => bootstrap.value?.progressive_categories ?? [])
  const singleProjects = computed(() => projects.value.filter((project) => project.group_size === 1))
  const multiProjects = computed(() => projects.value.filter((project) => project.group_size > 1))
  const selectedMultiProject = computed(() => multiProjects.value.find((project) => project.id === selectedMultiProjectId.value) ?? null)
  const activeProgressiveCategory = computed(() => {
    const categoryId = progressiveCategoryIdFromTab(activeTab.value)
    if (categoryId === null) return null

    return progressiveCategories.value.find((category) => category.id === categoryId) ?? null
  })

  const filteredPigeons = computed(() => {
    const query = searchQuery.value.trim().toLowerCase()
    const source = activePigeons()
    if (!query) return source
    return source.filter((pigeon) => pigeon.ring_number.toLowerCase().includes(query))
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

  const progressiveEntries = computed<ProgressiveRegistrationEntryPayload[]>(() => progressiveCategories.value
    .filter((category) => category.current_stage)
    .map((category) => ({
      category_id: category.id,
      stage_project_id: category.current_stage!.id,
      groups: (progressiveSelections.value[category.id] ?? []).map((group) => ({ pigeon_ids: [...group.pigeon_ids] })),
    }))
    .filter((entry) => entry.groups.length > 0))

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
  const submitProgressiveEntries = computed<ProgressiveRegistrationEntryPayload[]>(() => progressiveEntries.value)

  const singleAmountCent = computed(() => singleEntries.value.reduce((sum, entry) => sum + priceFor(entry.project_id), 0))
  const multiAmountCent = computed(() => multiEntries.value.reduce((sum, entry) => sum + priceFor(entry.project_id), 0))
  const progressiveAmountCent = computed(() => progressiveEntries.value.reduce((sum, entry) => {
    const category = categoryById(entry.category_id)

    return sum + entry.groups.length * (category.current_stage?.price_cent ?? 0)
  }, 0))
  const totalAmountCent = computed(() => singleAmountCent.value + multiAmountCent.value + progressiveAmountCent.value)
  const selectedCount = computed(() => submitEntries.value.length + progressiveEntries.value.reduce((sum, entry) => sum + entry.groups.length, 0))

  const singleProjectStats = computed(() => singleProjects.value.map((project) => {
    const count = singleEntries.value.filter((entry) => entry.project_id === project.id).length
    return { project, count, amount_cent: count * project.price_cent }
  }))

  function load(payload: BootstrapPayload): void {
    bootstrap.value = payload
    singleMatrix.value = {}
    multiGroups.value = []
    progressiveSelections.value = {}
    selectedMultiProjectId.value = multiProjects.value[0]?.id ?? null
    activeTab.value = defaultTab()
    hydrateExisting(payload.existing_registration)
    hydrateProgressiveCategories()
    restoreDraftIfFresh(payload.existing_registration)
  }

  function resetRuntimeState(): void {
    bootstrap.value = null
    activeTab.value = 'single'
    searchQuery.value = ''
    selectedMultiProjectId.value = null
    pendingMultiPigeonIds.value = []
    singleMatrix.value = {}
    multiGroups.value = []
    progressiveSelections.value = {}
  }

  function defaultTab(): string {
    if (singleProjects.value.length > 0) return 'single'
    if (multiProjects.value.length > 0) return 'multi'
    const firstProgressive = progressiveCategories.value[0]
    if (firstProgressive) return progressiveTabId(firstProgressive.id)

    return 'detail'
  }

  function toggleSingle(pigeonId: number, projectId: number): void {
    const project = requireProject(projectId)
    if (project.group_size !== 1) return
    if (!isSingleProjectAvailable(pigeonId, projectId)) return
    singleMatrix.value[pigeonId] = { ...(singleMatrix.value[pigeonId] ?? {}) }
    singleMatrix.value[pigeonId][projectId] = !singleMatrix.value[pigeonId][projectId]
    saveDraft()
  }

  function isSingleRowAllSelected(pigeonId: number): boolean {
    const availableProjects = singleProjects.value.filter((project) => isSingleProjectAvailable(pigeonId, project.id))

    return availableProjects.length > 0 && availableProjects.every((project) => singleMatrix.value[pigeonId]?.[project.id])
  }

  function toggleSingleRowAll(pigeonId: number): void {
    const nextSelected = !isSingleRowAllSelected(pigeonId)
    singleMatrix.value[pigeonId] = { ...(singleMatrix.value[pigeonId] ?? {}) }

    for (const project of singleProjects.value.filter((candidate) => isSingleProjectAvailable(pigeonId, candidate.id))) {
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
    if (!pigeonBelongsToProjectLibrary(pigeonId, project)) return
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

  function progressiveTabId(categoryId: number): string {
    return `progressive:${categoryId}`
  }

  function progressiveCategoryIdFromTab(tab: string): number | null {
    if (!tab.startsWith('progressive:')) return null
    const id = Number(tab.slice('progressive:'.length))

    return Number.isFinite(id) ? id : null
  }

  function toggleProgressiveGroup(categoryId: number, groupKey: string): void {
    const category = categoryById(categoryId)
    const group = progressiveGroupByKey(category, groupKey)
    if (!category.current_stage || !group) return

    const selected = progressiveSelections.value[categoryId] ?? []
    progressiveSelections.value[categoryId] = selected.some((item) => item.group_key === groupKey)
      ? selected.filter((item) => item.group_key !== groupKey)
      : [...selected, { group_key: group.group_key, pigeon_ids: [...group.pigeon_ids] }]
    saveDraft()
  }

  function isProgressiveGroupSelected(categoryId: number, groupKey: string): boolean {
    return (progressiveSelections.value[categoryId] ?? []).some((group) => group.group_key === groupKey)
  }

  function progressiveCategoryById(categoryId: number): ProgressiveCategory {
    return categoryById(categoryId)
  }

  function priceFor(projectId: number): number {
    return requireProject(projectId).price_cent
  }

  function projectName(projectId: number): string {
    return requireProject(projectId).name
  }

  function pigeonById(pigeonId: number): Pigeon {
    const pigeon = pigeons.value.find((candidate) => candidate.id === pigeonId)
    if (pigeon) return pigeon
    const existingPigeon = bootstrap.value?.existing_registration?.entries
      .flatMap((entry) => entry.pigeons)
      .find((candidate) => candidate.pigeon_id === pigeonId)
    if (existingPigeon) return { id: existingPigeon.pigeon_id, ring_number: existingPigeon.ring_number }
    throw new Error(`Unknown pigeon ${pigeonId}`)
  }

  function requireProject(projectId: number): RaceProject {
    const project = projects.value.find((candidate) => candidate.id === projectId)
    if (!project) throw new Error(`Unknown project ${projectId}`)
    return project
  }

  function categoryById(categoryId: number): ProgressiveCategory {
    const category = progressiveCategories.value.find((candidate) => candidate.id === categoryId)
    if (!category) throw new Error(`Unknown progressive category ${categoryId}`)

    return category
  }

  function progressiveGroupByKey(category: ProgressiveCategory, groupKey: string): ProgressiveStageGroup | null {
    return progressiveEligibleGroups(category).find((group) => group.group_key === groupKey) ?? null
  }

  function progressiveEligibleGroups(category: ProgressiveCategory): ProgressiveStageGroup[] {
    if (category.eligible_groups && category.eligible_groups.length > 0) return category.eligible_groups

    return category.eligible_pigeons.map((pigeon, index) => ({
      group_key: String(pigeon.id),
      group_index: index + 1,
      pigeon_ids: [pigeon.id],
      pigeons: [{ ...pigeon, sort_order: 1 }],
    }))
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
    if (!pigeonBelongsToProjectLibrary(pigeonId, project)) return false
    if (alreadyPending) return true

    const currentUsage = selectedMultiProjectUsage.value[pigeonId] ?? 0
    if (!project.allow_repeat_pigeon_in_project && currentUsage > 0) return false
    if (project.max_usage_per_pigeon !== null && project.max_usage_per_pigeon !== undefined && currentUsage >= project.max_usage_per_pigeon) return false

    return true
  }

  function isSingleProjectAvailable(pigeonId: number, projectId: number): boolean {
    const project = singleProjects.value.find((candidate) => candidate.id === projectId)
    if (!project) return false

    return pigeonBelongsToProjectLibrary(pigeonId, project)
  }

  function pigeonBelongsToProjectLibrary(pigeonId: number, project: RaceProject): boolean {
    if (project.pigeon_library_id === null || project.pigeon_library_id === undefined) return true
    const pigeon = pigeons.value.find((candidate) => candidate.id === pigeonId)

    return (pigeon?.pigeon_library_id ?? null) === project.pigeon_library_id
  }

  function activePigeons(): Pigeon[] {
    const queryProjects = activeTab.value === 'single'
      ? singleProjects.value
      : activeTab.value === 'multi' && selectedMultiProject.value
        ? [selectedMultiProject.value]
        : []

    if (queryProjects.length === 0) return pigeons.value

    const libraryIds = new Set(queryProjects
      .map((project) => project.pigeon_library_id)
      .filter((libraryId): libraryId is number => typeof libraryId === 'number'))
    if (libraryIds.size === 0) return pigeons.value

    return pigeons.value.filter((pigeon) => pigeon.pigeon_library_id !== null && pigeon.pigeon_library_id !== undefined && libraryIds.has(pigeon.pigeon_library_id))
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

  function hydrateProgressiveCategories(): void {
    for (const category of progressiveCategories.value) {
      if (category.selected_groups && category.selected_groups.length > 0) {
        progressiveSelections.value[category.id] = category.selected_groups.map((group) => ({
          group_key: group.group_key,
          pigeon_ids: [...group.pigeon_ids],
        }))
        continue
      }

      progressiveSelections.value[category.id] = category.selected_pigeon_ids.map((pigeonId) => ({
        group_key: String(pigeonId),
        pigeon_ids: [pigeonId],
      }))
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
      progressiveSelections: progressiveSelections.value,
      updatedAt: new Date().toISOString(),
    }
    localStorage.setItem(key, JSON.stringify(payload))
  }

  function restoreDraftIfFresh(existing: ExistingRegistration | null): void {
    const key = draftKey()
    if (!key || !race.value) return
    const raw = localStorage.getItem(key)
    if (!raw) return
    const draft = parseDraft(raw)
    if (!draft) {
      localStorage.removeItem(key)
      return
    }
    if (draft.configVersion !== race.value.config_version) {
      localStorage.removeItem(key)
      return
    }
    if (existing && !draftIsNewerThanExisting(draft, existing)) {
      localStorage.removeItem(key)
      return
    }
    singleMatrix.value = draft.singleMatrix
    multiGroups.value = draft.multiGroups
    progressiveSelections.value = normalizeDraftProgressiveSelections(draft.progressiveSelections ?? progressiveSelections.value)
  }

  function normalizeDraftProgressiveSelections(value: unknown): Record<number, ProgressiveSelectionGroup[]> {
    const normalized: Record<number, ProgressiveSelectionGroup[]> = {}
    if (!value || typeof value !== 'object') return normalized

    for (const [categoryId, groups] of Object.entries(value as Record<string, unknown>)) {
      if (!Array.isArray(groups)) continue
      normalized[Number(categoryId)] = groups
        .map((group): ProgressiveSelectionGroup | null => {
          if (typeof group === 'number') return { group_key: String(group), pigeon_ids: [group] }
          if (!group || typeof group !== 'object') return null
          const payload = group as Partial<ProgressiveSelectionGroup>
          const pigeonIds = Array.isArray(payload.pigeon_ids) ? payload.pigeon_ids.map(Number).filter(Number.isFinite) : []
          if (!payload.group_key || pigeonIds.length === 0) return null

          return { group_key: String(payload.group_key), pigeon_ids: pigeonIds }
        })
        .filter((group): group is ProgressiveSelectionGroup => group !== null)
    }

    return normalized
  }

  function parseDraft(raw: string): DraftPayload | null {
    try {
      return JSON.parse(raw) as DraftPayload
    } catch {
      return null
    }
  }

  function draftIsNewerThanExisting(draft: DraftPayload, existing: ExistingRegistration): boolean {
    const draftTime = parseTimestamp(draft.updatedAt)
    const submittedTime = parseTimestamp(existing.submitted_at)
    if (draftTime === null || submittedTime === null) return false

    return draftTime > submittedTime
  }

  function parseTimestamp(value: string | null | undefined): number | null {
    if (!value) return null
    const normalized = value.includes('T') ? value : value.replace(' ', 'T')
    const timestamp = Date.parse(normalized)

    return Number.isNaN(timestamp) ? null : timestamp
  }

  return {
    bootstrap,
    activeTab,
    searchQuery,
    selectedMultiProjectId,
    pendingMultiPigeonIds,
    singleMatrix,
    multiGroups,
    progressiveSelections,
    race,
    member,
    projects,
    pigeons,
    progressiveCategories,
    pigeonLibraries,
    singleProjects,
    multiProjects,
    selectedMultiProject,
    activeProgressiveCategory,
    filteredPigeons,
    singleEntries,
    multiEntries,
    progressiveEntries,
    selectedMultiProjectUsage,
    selectedMultiProjectGroupCount,
    canConfirmMultiGroup,
    submitEntries,
    submitProgressiveEntries,
    singleAmountCent,
    multiAmountCent,
    progressiveAmountCent,
    totalAmountCent,
    selectedCount,
    singleProjectStats,
    load,
    resetRuntimeState,
    toggleSingle,
    isSingleRowAllSelected,
    toggleSingleRowAll,
    setMultiProject,
    togglePendingMultiPigeon,
    confirmMultiGroup,
    canUsePigeonInSelectedProject,
    isSingleProjectAvailable,
    deleteMultiGroup,
    progressiveTabId,
    toggleProgressiveGroup,
    isProgressiveGroupSelected,
    progressiveCategoryById,
    progressiveEligibleGroups,
    priceFor,
    projectName,
    pigeonById,
  }
})
