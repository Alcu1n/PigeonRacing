{{-- [IN]: Filament topbar render hook / Filament 顶部栏渲染钩子 --}}
{{-- [OUT]: Compact admin contact copy before user menu / 用户菜单前的紧凑后台联系信息 --}}
{{-- [POS]: Backend admin topbar contact fragment / 后端后台顶部栏联系信息片段 --}}
{{-- Protocol: When updating me, sync this header + parent folder's .folder.md --}}
{{-- 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md --}}

<style>
    .admin-topbar-contact {
        align-items: center;
        color: #71717a;
        display: none;
        font-size: 12px;
        font-weight: 600;
        gap: 8px;
        line-height: 1.2;
        margin-inline-end: 10px;
        white-space: nowrap;
    }

    .dark .admin-topbar-contact {
        color: #a1a1aa;
    }

    .admin-topbar-contact strong {
        color: #047857;
        font-weight: 800;
    }

    .dark .admin-topbar-contact strong {
        color: #34d399;
    }

    @media (min-width: 768px) {
        .admin-topbar-contact {
            display: inline-flex;
        }
    }
</style>

<div class="admin-topbar-contact">
    <span>联系电话：<strong>18650024626</strong></span>
    <span>定制开发 微信：<strong>lemonrere</strong></span>
</div>
