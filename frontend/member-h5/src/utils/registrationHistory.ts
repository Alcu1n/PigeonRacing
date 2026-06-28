// [IN]: Existing registration snapshot / 已存在报名快照
// [OUT]: Read-only single and multi registration history matrices / 只读单羽与多羽报名历史矩阵
// [POS]: Frontend registration history shaping helper / 前端报名历史整形辅助函数
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md
import type { ExistingRegistration, ExistingRegistrationEntry } from '../types/domain'

export interface HistorySingleProject {
  id: number
  name: string
  price_cent: number
}

export interface HistorySingleRow {
  ring_number: string
  selected_project_ids: Record<number, true>
  count: number
  amount_cent: number
}

export interface HistoryMultiProject {
  project_id: number
  project_name: string
  group_size: number
  price_cent: number
  groups: Array<{ group_index: number; rings: string[] }>
  group_count: number
  amount_cent: number
}

export interface HistoryMatrix {
  single: {
    projects: HistorySingleProject[]
    rows: HistorySingleRow[]
    total_count: number
    total_amount_cent: number
  }
  multi: HistoryMultiProject[]
}

export function buildRegistrationHistoryMatrix(registration: ExistingRegistration): HistoryMatrix {
  const singleEntries = registration.entries.filter((entry) => entry.group_size === 1)
  const singleProjects = buildSingleProjects(singleEntries)
  const singleRows = buildSingleRows(singleEntries, singleProjects)
  const multi = buildMultiProjects(registration.entries.filter((entry) => entry.group_size > 1))

  return {
    single: {
      projects: singleProjects,
      rows: singleRows,
      total_count: singleRows.reduce((sum, row) => sum + row.count, 0),
      total_amount_cent: singleRows.reduce((sum, row) => sum + row.amount_cent, 0),
    },
    multi,
  }
}

function buildSingleProjects(entries: ExistingRegistrationEntry[]): HistorySingleProject[] {
  const projects = new Map<number, HistorySingleProject>()

  for (const entry of entries) {
    if (!projects.has(entry.project_id)) {
      projects.set(entry.project_id, {
        id: entry.project_id,
        name: entry.project_name,
        price_cent: entry.price_cent,
      })
    }
  }

  return [...projects.values()].sort((left, right) => left.id - right.id)
}

function buildSingleRows(entries: ExistingRegistrationEntry[], projects: HistorySingleProject[]): HistorySingleRow[] {
  const rows = new Map<string, HistorySingleRow>()
  const projectPrices = new Map(projects.map((project) => [project.id, project.price_cent]))

  for (const entry of entries) {
    const ringNumber = entry.pigeons[0]?.ring_number
    if (!ringNumber) continue

    const row = rows.get(ringNumber) ?? {
      ring_number: ringNumber,
      selected_project_ids: {},
      count: 0,
      amount_cent: 0,
    }

    if (!row.selected_project_ids[entry.project_id]) {
      row.selected_project_ids[entry.project_id] = true
      row.count += 1
      row.amount_cent += projectPrices.get(entry.project_id) ?? entry.price_cent
    }

    rows.set(ringNumber, row)
  }

  return [...rows.values()].sort((left, right) => left.ring_number.localeCompare(right.ring_number))
}

function buildMultiProjects(entries: ExistingRegistrationEntry[]): HistoryMultiProject[] {
  const projects = new Map<number, HistoryMultiProject>()

  for (const entry of entries) {
    const project = projects.get(entry.project_id) ?? {
      project_id: entry.project_id,
      project_name: entry.project_name,
      group_size: entry.group_size,
      price_cent: entry.price_cent,
      groups: [],
      group_count: 0,
      amount_cent: 0,
    }

    project.groups.push({
      group_index: entry.group_index,
      rings: [...entry.pigeons]
        .sort((left, right) => left.sort_order - right.sort_order)
        .map((pigeon) => pigeon.ring_number),
    })
    project.group_count = project.groups.length
    project.amount_cent = project.group_count * project.price_cent
    projects.set(entry.project_id, project)
  }

  return [...projects.values()]
    .map((project) => ({
      ...project,
      groups: project.groups.sort((left, right) => left.group_index - right.group_index),
    }))
    .sort((left, right) => left.project_id - right.project_id)
}
