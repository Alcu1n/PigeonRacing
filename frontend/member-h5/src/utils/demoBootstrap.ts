// [IN]: Development-only frontend runtime / 仅开发环境前端运行时
// [OUT]: Demo bootstrap payload for visual QA without backend / 无后端视觉验证用演示初始化数据
// [POS]: Frontend development fixture provider / 前端开发夹具提供者
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md
import type { BootstrapPayload } from '../types/domain'

export function demoBootstrap(): BootstrapPayload {
  return {
    race: {
      id: 1,
      name: '2026 春季大奖赛',
      description: '演示赛事',
      registration_start_at: '2026-05-01 08:00:00',
      registration_end_at: '2026-05-01 18:00:00',
      status: 'open',
      config_version: 1,
      allow_member_edit: true,
    },
    member: { id: 1, loft_number: 'A001', participant_name: '张三鸽舍', must_change_password: false },
    projects: [
      { id: 1, race_id: 1, pigeon_library_id: 1, name: '单羽 50 元', group_size: 1, price_cent: 5000, sort_order: 1, is_enabled: true, allow_repeat_pigeon_in_project: false },
      { id: 2, race_id: 1, pigeon_library_id: 1, name: '单羽 100 元', group_size: 1, price_cent: 10000, sort_order: 2, is_enabled: true, allow_repeat_pigeon_in_project: false },
      { id: 3, race_id: 1, pigeon_library_id: 1, name: '单羽 200 元', group_size: 1, price_cent: 20000, sort_order: 3, is_enabled: true, allow_repeat_pigeon_in_project: false },
      { id: 4, race_id: 1, pigeon_library_id: 1, name: '双羽组 200 元', group_size: 2, price_cent: 20000, sort_order: 4, is_enabled: true, allow_repeat_pigeon_in_project: false },
      { id: 5, race_id: 1, pigeon_library_id: 1, name: '三羽组 300 元', group_size: 3, price_cent: 30000, sort_order: 5, is_enabled: true, allow_repeat_pigeon_in_project: false },
    ],
    pigeons: Array.from({ length: 18 }, (_, index) => ({
      id: 101 + index,
      pigeon_library_id: 1,
      ring_number: `CHN-2026-03-${String(index + 1).padStart(6, '0')}`,
    })),
    pigeon_libraries: [{
      id: 1,
      name: '默认足环库',
      pigeon_count: 18,
      pigeons: Array.from({ length: 18 }, (_, index) => ({
        id: 101 + index,
        pigeon_library_id: 1,
        ring_number: `CHN-2026-03-${String(index + 1).padStart(6, '0')}`,
      })),
    }],
    existing_registration: null,
  }
}
