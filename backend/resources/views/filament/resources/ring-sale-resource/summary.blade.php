{{-- [IN]: Filter-aware active ring-sale totals / 随筛选变化的有效售环汇总 --}}
{{-- [OUT]: Integrated reconciliation cards, single-line ledger cells, and aligned compact mobile modal styling / 融合式对账卡片、单行台账单元格与对齐紧凑手机弹层样式 --}}
{{-- [POS]: Ring-sale table summary / 售环列表汇总 --}}
{{-- Protocol: When updating me, sync this header + parent folder's .folder.md --}}
{{-- 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md --}}

@php
    $money = static fn (int $cent): string => '¥'.number_format($cent / 100, 2);
@endphp

<div class="ring-sale-summary">
    <div class="ring-sale-summary__item">
        <span>售环单</span>
        <strong>{{ number_format($summary['sales']) }}</strong>
    </div>
    <div class="ring-sale-summary__item">
        <span>足环数量</span>
        <strong>{{ number_format($summary['quantity']) }}</strong>
    </div>
    <div class="ring-sale-summary__item">
        <span>应收</span>
        <strong>{{ $money($summary['total_amount_cent']) }}</strong>
    </div>
    <div class="ring-sale-summary__item ring-sale-summary__item--paid">
        <span>已收</span>
        <strong>{{ $money($summary['paid_amount_cent']) }}</strong>
    </div>
    <div class="ring-sale-summary__item ring-sale-summary__item--unpaid">
        <span>未收</span>
        <strong>{{ $money($summary['unpaid_amount_cent']) }}</strong>
    </div>
</div>

<style>
    .ring-sale-summary {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: .5rem;
        padding: .75rem;
        border-bottom: 1px solid rgb(229 231 235);
        border-radius: .75rem .75rem 0 0;
        background: transparent;
    }

    .dark .ring-sale-summary {
        border-color: rgb(55 65 81);
        background: transparent;
    }

    .ring-sale-summary__item {
        min-width: 0;
        padding: .65rem .75rem;
        border: 1px solid rgb(226 232 240);
        border-radius: .7rem;
        background: rgb(249 250 251);
    }

    .dark .ring-sale-summary__item {
        border-color: rgb(63 63 70);
        background: rgb(39 39 42);
    }

    .ring-sale-summary__item span {
        display: block;
        color: rgb(100 116 139);
        font-size: .72rem;
        line-height: 1rem;
    }

    .ring-sale-summary__item strong {
        display: block;
        overflow: hidden;
        margin-top: .16rem;
        color: rgb(15 23 42);
        font-size: .98rem;
        line-height: 1.35rem;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .dark .ring-sale-summary__item strong {
        color: rgb(241 245 249);
    }

    .ring-sale-summary__item--paid strong {
        color: rgb(5 150 105);
    }

    .ring-sale-summary__item--unpaid strong {
        color: rgb(220 38 38);
    }

    .ring-sale-nowrap,
    .ring-sale-segments-scroll {
        white-space: nowrap;
    }

    .ring-sale-segments-scroll {
        max-width: min(34rem, 36vw);
        overflow-x: auto;
        overflow-y: hidden;
        padding-block: .25rem;
        scrollbar-width: thin;
        overscroll-behavior-inline: contain;
        -webkit-overflow-scrolling: touch;
    }

    .ring-sale-segments-scroll:focus-visible,
    .ring-sale-item-summary:focus-visible {
        border-radius: .35rem;
        outline: 2px solid rgb(16 185 129);
        outline-offset: 2px;
    }

    .ring-sale-item-summary {
        max-width: 100%;
        overflow-x: auto;
        overflow-y: hidden;
        padding: .5rem .65rem;
        border: 1px solid rgb(226 232 240);
        border-radius: .55rem;
        color: rgb(71 85 105);
        font-size: .78rem;
        line-height: 1.1rem;
        white-space: nowrap;
        scrollbar-width: thin;
        overscroll-behavior-inline: contain;
        -webkit-overflow-scrolling: touch;
    }

    .dark .ring-sale-item-summary {
        border-color: rgb(63 63 70);
        color: rgb(161 161 170);
        background: rgb(24 24 27 / .45);
    }

    @media (max-width: 640px) {
        .ring-sale-summary {
            display: flex;
            gap: .45rem;
            overflow-x: auto;
            padding: .6rem;
            scrollbar-width: none;
            overscroll-behavior-inline: contain;
            -webkit-overflow-scrolling: touch;
        }

        .ring-sale-summary::-webkit-scrollbar {
            display: none;
        }

        .ring-sale-summary__item {
            flex: 0 0 9rem;
            padding: .55rem .65rem;
        }

        .fi-modal-window-ctn:has(> .ring-sale-entry-modal) {
            display: block !important;
            overflow: hidden;
            padding: 0 !important;
        }

        .ring-sale-entry-modal {
            position: fixed !important;
            inset: 0 !important;
            width: 100vw !important;
            max-width: none !important;
            height: 100dvh !important;
            min-height: 100dvh;
            max-height: 100dvh !important;
            margin: 0 !important;
            overflow: hidden !important;
            border-radius: 0 !important;
        }

        .ring-sale-entry-modal > .fi-modal-header {
            position: sticky;
            z-index: 30;
            top: 0;
            min-height: 3.5rem;
            align-items: center;
            justify-content: center;
            padding: .7rem 5.75rem !important;
            border-bottom: 1px solid rgb(229 231 235);
            background: rgb(255 255 255 / .96);
            backdrop-filter: blur(12px);
        }

        .dark .ring-sale-entry-modal > .fi-modal-header {
            border-color: rgb(63 63 70);
            background: rgb(24 24 27 / .96);
        }

        .ring-sale-entry-modal .fi-modal-heading {
            position: absolute;
            top: 50%;
            inset-inline-start: 50%;
            max-width: calc(100% - 12rem);
            overflow: hidden;
            text-align: center;
            text-overflow: ellipsis;
            transform: translate(-50%, -50%);
            white-space: nowrap;
        }

        .ring-sale-entry-modal .fi-modal-close-btn {
            inset-inline-start: .75rem !important;
            inset-inline-end: auto !important;
            top: .65rem !important;
        }

        .ring-sale-entry-modal > .fi-modal-content {
            flex: 1;
            gap: .55rem !important;
            overflow-y: auto;
            padding: .65rem .7rem 1rem !important;
        }

        .ring-sale-entry-modal > .fi-modal-footer {
            position: absolute;
            z-index: 40;
            top: .55rem;
            bottom: auto !important;
            inset-inline-start: auto !important;
            inset-inline-end: .65rem;
            width: auto;
            height: auto;
            padding: 0 !important;
            border: 0 !important;
            background: transparent !important;
            box-shadow: none !important;
        }

        .ring-sale-entry-modal .fi-modal-footer-actions {
            gap: 0;
        }

        .ring-sale-entry-modal .fi-modal-footer-actions > :not([type="submit"]) {
            display: none;
        }

        .ring-sale-entry-modal .fi-modal-footer-actions > [type="submit"] {
            min-height: 2.35rem;
            padding-inline: .8rem;
            font-size: .8rem;
        }

        .ring-sale-entry-modal .fi-sc-section {
            margin: 0;
        }

        .ring-sale-entry-modal .fi-section-content {
            gap: .55rem;
        }

        .ring-sale-entry-modal .ring-sale-paired-grid > .fi-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        }

        .ring-sale-entry-modal .ring-sale-entry-mode {
            flex-wrap: nowrap !important;
        }

        .ring-sale-entry-modal .ring-sale-entry-mode .fi-fo-toggle-buttons-btn-ctn {
            min-width: 0;
            flex: 1 1 0;
        }

        .ring-sale-entry-modal .ring-sale-entry-mode .fi-btn {
            width: 100%;
            padding-inline: .55rem;
            white-space: nowrap;
        }

        .ring-sale-entry-modal .fi-fo-repeater-item {
            border-radius: .65rem;
        }

        .ring-sale-entry-modal .fi-fo-repeater-item-content {
            gap: .55rem;
            padding: .65rem;
        }

        .ring-sale-item-summary {
            padding: .42rem .55rem;
            font-size: .74rem;
        }
    }
</style>
