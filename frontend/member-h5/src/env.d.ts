// [IN]: Third-party UI library type gaps / 第三方 UI 库类型缺口
// [OUT]: Minimal global JSX namespace for Vue library declarations / Vue 库声明所需的最小 JSX 全局命名空间
// [POS]: Frontend ambient type bridge / 前端环境类型桥
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md
declare namespace JSX {
  interface Element {}
  interface IntrinsicElements {
    [elem: string]: unknown
  }
}
