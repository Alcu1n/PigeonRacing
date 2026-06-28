// [IN]: Backend API JSON contracts / 后端 API JSON 契约
// [OUT]: Frontend domain TypeScript types and localized registration status helpers / 前端领域 TypeScript 类型与本地化报名状态辅助函数
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
}

export interface RaceProject {
  id: number
  race_id: number
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
  ring_number: string
}

export interface MemberProfile {
  member: Member
  pigeons: Pigeon[]
}

export interface RegistrationEntryPayload {
  project_id: number
  pigeon_ids: number[]
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
  race_name?: string
  registration_no: string
  status: string
  total_amount_cent: number
  submitted_at: string
  entries: ExistingRegistrationEntry[]
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
}

export interface BootstrapPayload {
  race: Race
  member: Member
  projects: RaceProject[]
  pigeons: Pigeon[]
  existing_registration: ExistingRegistration | null
}

export function registrationStatusText(status?: string | null): string {
  return status === 'confirmed' ? '已确认' : '未确认'
}

export function registrationStatusTone(status?: string | null): 'confirmed' | 'pending' {
  return status === 'confirmed' ? 'confirmed' : 'pending'
}
