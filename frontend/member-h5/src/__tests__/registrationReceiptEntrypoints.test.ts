// [IN]: Result, profile history, and registration history detail views / 报名结果、个人历史与报名历史详情视图
// [OUT]: Receipt entry ordering, isolated list actions, and mobile WeChat fallback assertions / 凭证入口顺序、列表动作隔离与移动微信降级断言
// [POS]: Frontend receipt entrypoint integration tests / 前端凭证入口集成测试
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md
import { flushPromises, mount } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import ProfileView from '../views/ProfileView.vue'
import RegistrationHistoryDetailView from '../views/RegistrationHistoryDetailView.vue'
import ResultView from '../views/ResultView.vue'
import RegistrationReceiptDownload from '../components/RegistrationReceiptDownload.vue'

const { apiGet, downloadBlob, isMobileClient, isWechat, renderReceipt, routerPush } = vi.hoisted(() => ({
  apiGet: vi.fn(),
  downloadBlob: vi.fn(),
  isMobileClient: vi.fn(),
  isWechat: vi.fn(),
  renderReceipt: vi.fn(),
  routerPush: vi.fn(),
}))

vi.mock('../api/client', () => ({
  api: { get: apiGet, post: vi.fn() },
  ensureCsrf: vi.fn(),
}))

vi.mock('../utils/registrationReceiptExport', () => ({
  downloadReceiptBlob: downloadBlob,
  isMobileReceiptClient: isMobileClient,
  isWechatClient: isWechat,
  renderRegistrationReceipt: renderReceipt,
  shareReceiptFile: vi.fn(),
}))

vi.mock('vue-router', () => ({
  useRoute: () => ({ params: { registrationId: '10' }, query: {} }),
  useRouter: () => ({ push: routerPush, replace: vi.fn() }),
}))

describe('registration receipt entrypoints', () => {
  beforeEach(() => {
    apiGet.mockReset()
    downloadBlob.mockReset()
    isMobileClient.mockReset().mockReturnValue(false)
    isWechat.mockReset().mockReturnValue(false)
    renderReceipt.mockReset().mockResolvedValue(new Blob(['png'], { type: 'image/png' }))
    routerPush.mockReset()
  })

  it('places the result download before returning to the race list', async () => {
    apiGet.mockResolvedValue({ data: registration() })
    const wrapper = mount(ResultView, { global: { plugins: [createPinia()] } })
    await flushPromises()

    expect(wrapper.findComponent(RegistrationReceiptDownload).exists()).toBe(true)
    expect(wrapper.text().indexOf('下载报名明细')).toBeLessThan(wrapper.text().indexOf('返回赛事列表'))
  })

  it('places the history detail download before returning to profile', async () => {
    apiGet.mockResolvedValue({ data: registration() })
    const wrapper = mount(RegistrationHistoryDetailView, { global: { plugins: [createPinia()] } })
    await flushPromises()

    expect(wrapper.findComponent(RegistrationReceiptDownload).exists()).toBe(true)
    expect(wrapper.text().indexOf('下载报名明细')).toBeLessThan(wrapper.text().indexOf('返回个人信息'))
  })

  it('provides a separate compact download action for each profile history record', async () => {
    apiGet.mockImplementation((url: string) => {
      if (url === '/api/member/profile') {
        return Promise.resolve({ data: {
          member: { id: 1, loft_number: 'A001', participant_name: '张三鸽舍', must_change_password: false },
          pigeons: [],
          pigeon_libraries: [],
        } })
      }

      if (url === '/api/member/registrations/10') return Promise.resolve({ data: registration() })

      return Promise.resolve({ data: [{
        registration_id: 10,
        race_id: 1,
        race_name: '春赛',
        registration_no: 'R1-A001',
        status: 'submitted',
        total_amount_cent: 5000,
        submitted_at: '2026-07-22 10:00:00',
        single_count: 1,
        multi_group_count: 0,
        progressive_count: 0,
      }] })
    })
    const wrapper = mount(ProfileView, { global: { plugins: [createPinia()] } })
    await flushPromises()

    const download = wrapper.findComponent(RegistrationReceiptDownload)
    expect(download.exists()).toBe(true)
    expect(download.props('registrationId')).toBe(10)
    expect(download.props('compact')).toBe(true)
    expect(wrapper.text()).toContain('查看明细')

    vi.stubGlobal('requestAnimationFrame', (callback: FrameRequestCallback) => {
      callback(0)
      return 1
    })
    await download.find('button').trigger('click')
    await flushPromises()
    expect(apiGet).toHaveBeenCalledWith('/api/member/registrations/10')
    expect(downloadBlob).toHaveBeenCalledOnce()
    expect(routerPush).not.toHaveBeenCalled()
    vi.unstubAllGlobals()
  })

  it('opens a mobile preview with long-press guidance in WeChat', async () => {
    apiGet.mockResolvedValue({ data: registration() })
    isMobileClient.mockReturnValue(true)
    isWechat.mockReturnValue(true)
    vi.stubGlobal('URL', { createObjectURL: vi.fn(() => 'blob:receipt'), revokeObjectURL: vi.fn() })
    vi.stubGlobal('requestAnimationFrame', (callback: FrameRequestCallback) => {
      callback(0)
      return 1
    })
    const wrapper = mount(ResultView, { attachTo: document.body, global: { plugins: [createPinia()] } })
    await flushPromises()
    const trigger = wrapper.findComponent(RegistrationReceiptDownload).find('button')

    await trigger.trigger('click')
    await flushPromises()

    expect(document.body.textContent).toContain('请长按上方图片选择保存')
    expect(document.body.textContent).toContain('下载 PNG')
    expect(document.body.textContent).not.toContain('保存到相册')
    expect(document.activeElement?.getAttribute('aria-label')).toBe('关闭预览')

    document.activeElement?.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))
    await flushPromises()
    expect(document.querySelector('[aria-label="报名明细图片预览"]')).toBeNull()
    expect(document.activeElement).toBe(trigger.element)
    wrapper.unmount()
    vi.unstubAllGlobals()
  })
})

function registration() {
  return {
    id: 10,
    race_id: 1,
    race_name: '春赛',
    loft_number: 'A001',
    participant_name: '张三鸽舍',
    registration_no: 'R1-A001',
    status: 'submitted',
    total_amount_cent: 5000,
    submitted_at: '2026-07-22 10:00:00',
    entries: [{
      project_id: 1,
      project_name: '单羽 50',
      group_size: 1,
      price_cent: 5000,
      group_index: 1,
      pigeons: [{ pigeon_id: 1, ring_number: '2026-13-000001', sort_order: 1 }],
    }],
    progressive_entries: [],
  }
}
