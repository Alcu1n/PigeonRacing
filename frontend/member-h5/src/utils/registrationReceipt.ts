// [IN]: Snapshot-stable existing registration details / 快照稳定的既有报名明细
// [OUT]: Print-oriented receipt identity, summaries, and detail tables / 面向打印的凭证身份、汇总与明细表格
// [POS]: Registration receipt data boundary / 报名凭证数据边界
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md
import type { ExistingRegistration } from '../types/domain'
import { buildRegistrationHistoryMatrix, type HistoryMatrix } from './registrationHistory'

export interface RegistrationReceiptProjectSummary {
  category: '单羽组' | '多羽组' | '递进阶段'
  project_name: string
  unit_price_cent: number
  quantity: number
  quantity_unit: '羽' | '组'
  amount_cent: number
}

export interface RegistrationReceiptData extends HistoryMatrix {
  race_name: string
  loft_number: string
  participant_name: string
  registration_no: string
  submitted_at: string
  status: string
  total_amount_cent: number
  project_summaries: RegistrationReceiptProjectSummary[]
}

export function buildRegistrationReceiptData(registration: ExistingRegistration): RegistrationReceiptData {
  const matrix = buildRegistrationHistoryMatrix(registration)

  return {
    race_name: registration.race_name,
    loft_number: registration.loft_number,
    participant_name: registration.participant_name,
    registration_no: registration.registration_no,
    submitted_at: registration.submitted_at,
    status: registration.status,
    total_amount_cent: registration.total_amount_cent,
    project_summaries: [
      ...matrix.single.projects.map((project) => {
        const quantity = matrix.single.rows.filter((row) => row.selected_project_ids[project.id]).length

        return {
          category: '单羽组' as const,
          project_name: project.name,
          unit_price_cent: project.price_cent,
          quantity,
          quantity_unit: '羽' as const,
          amount_cent: quantity * project.price_cent,
        }
      }).filter((summary) => summary.quantity > 0),
      ...matrix.multi.map((project) => ({
        category: '多羽组' as const,
        project_name: project.project_name,
        unit_price_cent: project.price_cent,
        quantity: project.group_count,
        quantity_unit: '组' as const,
        amount_cent: project.amount_cent,
      })),
      ...matrix.progressive.map((project) => ({
        category: '递进阶段' as const,
        project_name: `${project.category_name} · ${project.stage_project_name}`,
        unit_price_cent: project.price_cent,
        quantity: project.count,
        quantity_unit: '组' as const,
        amount_cent: project.amount_cent,
      })),
    ],
    ...matrix,
  }
}

export function receiptFileName(receipt: Pick<RegistrationReceiptData, 'race_name' | 'registration_no'>): string {
  const safeRaceName = receipt.race_name.replace(/[\\/:*?"<>|\u0000-\u001F]/g, '-').replace(/\s+/g, ' ').trim() || '赛事'
  const safeRegistrationNo = receipt.registration_no.replace(/[\\/:*?"<>|\u0000-\u001F]/g, '-').trim() || '报名'

  return `${safeRaceName}-报名明细-${safeRegistrationNo}.png`
}
