// [IN]: Integer cent amount / 整数分金额
// [OUT]: Human-readable CNY amount / 人类可读人民币金额
// [POS]: Frontend money formatting helper / 前端金额格式化工具
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md
export function yuan(cent: number): string {
  return `¥${(cent / 100).toLocaleString('zh-CN', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
  })}`
}
