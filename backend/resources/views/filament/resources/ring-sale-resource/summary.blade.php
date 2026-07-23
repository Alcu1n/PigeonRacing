{{-- [IN]: Filter-aware active ring-sale totals / 随筛选变化的有效售环汇总 --}}
{{-- [OUT]: Compact responsive reconciliation cards / 紧凑响应式对账卡片 --}}
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
        gap: .65rem;
        padding: .75rem;
        border-bottom: 1px solid rgb(229 231 235);
        background: linear-gradient(135deg, rgb(248 250 252), rgb(255 255 255));
    }

    .dark .ring-sale-summary {
        border-color: rgb(55 65 81);
        background: linear-gradient(135deg, rgb(17 24 39), rgb(31 41 55));
    }

    .ring-sale-summary__item {
        min-width: 0;
        padding: .7rem .8rem;
        border: 1px solid rgb(226 232 240);
        border-radius: .8rem;
        background: rgba(255, 255, 255, .82);
    }

    .dark .ring-sale-summary__item {
        border-color: rgb(55 65 81);
        background: rgba(17, 24, 39, .7);
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

    .ring-sale-mobile-summary {
        display: none;
    }

    @media (max-width: 640px) {
        .ring-sale-summary {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .ring-sale-summary__item:last-child {
            grid-column: 1 / -1;
        }

        .ring-sale-entry-modal {
            width: 100vw !important;
            max-width: none !important;
            min-height: 100dvh;
            border-radius: 0 !important;
        }

        .ring-sale-mobile-summary {
            position: sticky;
            z-index: 20;
            bottom: .5rem;
            display: block;
            border-color: rgb(203 213 225);
            box-shadow: 0 -8px 24px rgba(15, 23, 42, .12);
        }
    }
</style>
