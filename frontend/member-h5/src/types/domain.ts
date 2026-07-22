// [IN]: Backend API JSON contracts / 后端 API JSON 契约
// [OUT]: Frontend domain types, snapshot-stable registration identity, status helpers, information, and published details / 前端领域类型、快照稳定报名身份、状态辅助、信息发布与已发布赛事明细
// [POS]: Frontend shared domain contract / 前端共享领域契约
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md
export interface Member {
  id: number
  phone?: string
  loft_number: string
  participant_name: string
  must_change_password: boolean
}

export interface Race {
  id: number
  name: string
  description?: string | null
  registration_start_at?: string
  registration_end_at: string
  status: string
  config_version: number
  allow_member_edit?: boolean
  has_published_details?: boolean
  registration_details_scope?: PublishedRaceDetailsScope
}

export type PublishedRaceDetailsScope = 'confirmed_only' | 'all_submitted'

export interface RaceProject {
  id: number
  race_id: number
  pigeon_library_id?: number | null
  project_type?: 'standard' | 'progressive_stage'
  registration_category_id?: number | null
  stage_order?: number | null
  name: string
  group_size: number
  price_cent: number
  description?: string | null
  sort_order: number
  is_enabled: boolean
  allow_repeat_pigeon_in_project: boolean
  max_entries_per_member?: number | null
  max_usage_per_pigeon?: number | null
}

export interface Pigeon {
  id: number
  pigeon_library_id?: number | null
  ring_number: string
}

export interface PigeonLibrary {
  id: number
  name: string
  pigeon_count: number
  pigeons: Pigeon[]
}

export interface MemberProfile {
  member: Member
  pigeons: Pigeon[]
  pigeon_libraries: PigeonLibrary[]
}

export interface RegistrationEntryPayload {
  project_id: number
  pigeon_ids: number[]
}

export interface ProgressiveRegistrationEntryPayload {
  category_id: number
  stage_project_id: number
  groups: Array<{ pigeon_ids: number[] }>
  pigeon_ids?: number[]
}

export interface ExistingRegistrationEntry {
  project_id: number
  project_name: string
  group_size: number
  price_cent: number
  group_index: number
  pigeons: Array<{ pigeon_id: number; ring_number: string; sort_order: number }>
}

export interface ExistingRegistration {
  id: number
  race_id?: number
  race_name: string
  loft_number: string
  participant_name: string
  registration_no: string
  status: string
  total_amount_cent: number
  submitted_at: string
  entries: ExistingRegistrationEntry[]
  progressive_entries?: ExistingProgressiveEntry[]
}

export interface ExistingProgressiveEntry {
  category_id: number
  category_name?: string | null
  stage_project_id: number
  stage_project_name: string
  group_key?: string | null
  group_index?: number
  group_size?: number
  pigeon_id: number
  ring_number: string
  pigeon_sort_order?: number
  price_cent: number
  status: string
  submitted_at?: string | null
}

export interface RegistrationHistoryItem {
  registration_id: number
  race_id: number
  race_name: string
  registration_no: string
  status: string
  total_amount_cent: number
  submitted_at: string
  single_count: number
  multi_group_count: number
  progressive_count?: number
}

export interface ProgressiveCategory {
  id: number
  name: string
  sort_order: number
  current_stage: {
    id: number
    name: string
    price_cent: number
    group_size: number
    pigeon_library_id?: number | null
    stage_order?: number | null
    sort_order: number
  } | null
  eligible_groups?: ProgressiveStageGroup[]
  eligible_pigeons: Pigeon[]
  selected_groups?: ProgressiveStageGroup[]
  selected_pigeon_ids: number[]
  status?: string | null
}

export interface ProgressiveStageGroup {
  group_key: string
  group_index: number
  pigeon_ids: number[]
  pigeons: Array<Pigeon & { sort_order?: number }>
}

export type InformationCategory = 'rules' | 'results' | 'notice'

export interface InformationPostListItem {
  id: number
  title: string
  slug: string
  category: InformationCategory
  summary?: string | null
  is_pinned: boolean
  published_at?: string | null
}

export interface InformationPostDetail extends InformationPostListItem {
  content_html: string
}

export interface PublishedRaceDetails {
  race: {
    id: number
    name: string
    registration_end_at: string
  }
  published_at?: string | null
  scope: PublishedRaceDetailsScope
  scope_label: string
  single: {
    projects: Array<{ id: number; name: string; sort_order: number }>
    rows: Array<{
      loft_number: string
      participant_name: string
      ring_number: string
      selected_projects: Record<string, string>
    }>
  }
  multi: Array<{
    project_id: number
    project_name: string
    group_size: number
    groups: PublishedRaceDetailsGroup[]
  }>
  progressive: Array<{
    category_id: number
    category_name: string
    stages: Array<{
      stage_project_id: number
      stage_project_name: string
      group_size: number
      groups: PublishedRaceDetailsGroup[]
    }>
  }>
}

export interface PublishedRaceDetailsGroup {
  loft_number: string
  participant_name: string
  group_index: number
  status: string
  rings: string[]
}

export interface BootstrapPayload {
  race: Race
  member: Member
  projects: RaceProject[]
  pigeons: Pigeon[]
  pigeon_libraries?: PigeonLibrary[]
  progressive_categories?: ProgressiveCategory[]
  existing_registration: ExistingRegistration | null
}

export function registrationStatusText(status?: string | null): string {
  return status === 'confirmed' ? '已确认' : '未确认'
}

export function registrationStatusTone(status?: string | null): 'confirmed' | 'pending' {
  return status === 'confirmed' ? 'confirmed' : 'pending'
}
