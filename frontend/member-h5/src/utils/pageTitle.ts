// [IN]: Route page title text / 路由页面标题文本
// [OUT]: Browser document title without automatic suffix / 不自动追加后缀的浏览器标题
// [POS]: Frontend browser title helper / 前端浏览器标题辅助工具
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

const appTitle = '赛鸽赛事报名'

export function setPageTitle(title?: string | null): void {
  const normalizedTitle = title?.trim()
  document.title = normalizedTitle || appTitle
}
