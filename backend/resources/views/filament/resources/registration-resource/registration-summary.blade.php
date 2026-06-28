{{-- [IN]: Registration summary totals from ListRegistrations / 来自 ListRegistrations 的报名汇总 --}}
{{-- [OUT]: Inline registration amount and loft cards / 内联报名金额与棚数卡片 --}}
{{-- [POS]: Backend admin registration list summary view / 后端后台报名列表汇总视图 --}}
{{-- Protocol: When updating me, sync this header + parent folder's .folder.md --}}
{{-- 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md --}}

@php
    use App\Services\RegistrationSummaryService;

    $cards = [
        [
            'label' => '已报名总金额',
            'value' => RegistrationSummaryService::formatYuan($summary['total_amount_cent']).' 元',
            'description' => '所有报名记录金额合计',
            'accent' => '#10b981',
        ],
        [
            'label' => '已确认金额',
            'value' => RegistrationSummaryService::formatYuan($summary['confirmed_amount_cent']).' 元',
            'description' => '已确认报名金额',
            'accent' => '#22c55e',
        ],
        [
            'label' => '未确认金额',
            'value' => RegistrationSummaryService::formatYuan($summary['unconfirmed_amount_cent']).' 元',
            'description' => '等待确认的报名金额',
            'accent' => '#f59e0b',
        ],
        [
            'label' => '报名总棚数',
            'value' => number_format($summary['loft_count']).' 棚',
            'description' => '已有报名记录的会员棚数',
            'accent' => '#38bdf8',
        ],
    ];
@endphp

<style>
    .registration-summary-grid {
        display: grid;
        gap: 12px;
        grid-template-columns: repeat(1, minmax(0, 1fr));
    }

    @media (min-width: 768px) {
        .registration-summary-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (min-width: 1280px) {
        .registration-summary-grid {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }
    }

    .registration-summary-card {
        position: relative;
        overflow: hidden;
        border: 1px solid rgba(148, 163, 184, 0.22);
        border-radius: 14px;
        background: rgba(255, 255, 255, 0.96);
        padding: 16px 18px;
        box-shadow: 0 10px 28px rgba(15, 23, 42, 0.06);
    }

    .dark .registration-summary-card {
        border-color: rgba(255, 255, 255, 0.08);
        background: rgba(24, 24, 27, 0.92);
        box-shadow: none;
    }

    .registration-summary-card::before {
        position: absolute;
        inset: 0 auto 0 0;
        width: 4px;
        background: var(--summary-accent);
        content: "";
    }

    .registration-summary-label {
        color: #64748b;
        font-size: 13px;
        font-weight: 600;
        line-height: 1.4;
    }

    .dark .registration-summary-label {
        color: #a1a1aa;
    }

    .registration-summary-value {
        margin-top: 6px;
        color: #0f172a;
        font-size: 26px;
        font-weight: 800;
        letter-spacing: 0;
        line-height: 1.15;
    }

    .dark .registration-summary-value {
        color: #f8fafc;
    }

    .registration-summary-description {
        margin-top: 6px;
        color: #94a3b8;
        font-size: 12px;
        line-height: 1.4;
    }
</style>

<div class="registration-summary-grid">
    @foreach ($cards as $card)
        <section class="registration-summary-card" style="--summary-accent: {{ $card['accent'] }}">
            <div class="registration-summary-label">{{ $card['label'] }}</div>
            <div class="registration-summary-value">{{ $card['value'] }}</div>
            <div class="registration-summary-description">{{ $card['description'] }}</div>
        </section>
    @endforeach
</div>
