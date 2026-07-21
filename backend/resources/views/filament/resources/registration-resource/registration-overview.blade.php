{{-- [IN]: Registration record with eager-loaded member and race relations / 含已预加载会员和赛事关联的报名记录 --}}
{{-- [OUT]: Priority-led registration overview with prominent loft, participant, and total amount / 突出棚号、参赛名和总金额的报名概览 --}}
{{-- [POS]: Backend admin registration overview view / 后端后台报名概览视图 --}}
{{-- Protocol: When updating me, sync this header + parent folder's .folder.md --}}
{{-- 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md --}}

@php
    use App\Models\Registration;
    use App\Services\RegistrationSummaryService;

    $statusLabel = Registration::statusLabel($registration->status);
    $isConfirmed = $statusLabel === '已确认';
@endphp

<style>
    .registration-overview {
        background: rgba(255, 255, 255, 0.96);
        border: 1px solid rgba(148, 163, 184, 0.22);
        border-radius: 14px;
        box-shadow: 0 10px 28px rgba(15, 23, 42, 0.05);
        overflow: hidden;
    }

    .dark .registration-overview {
        background: rgba(24, 24, 27, 0.92);
        border-color: rgba(255, 255, 255, 0.08);
        box-shadow: none;
    }

    .registration-overview-heading {
        border-bottom: 1px solid rgba(148, 163, 184, 0.18);
        color: #0f172a;
        font-size: 16px;
        font-weight: 800;
        line-height: 1.25;
        padding: 16px 20px;
    }

    .dark .registration-overview-heading {
        color: #f8fafc;
    }

    .registration-overview-primary {
        display: grid;
        gap: 16px;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        padding: 20px;
    }

    .registration-overview-key {
        min-width: 0;
    }

    .registration-overview-key-label,
    .registration-overview-detail-label {
        color: #64748b;
        font-size: 12px;
        font-weight: 650;
        line-height: 1.35;
    }

    .dark .registration-overview-key-label,
    .dark .registration-overview-detail-label {
        color: #a1a1aa;
    }

    .registration-overview-key-value {
        color: #111827;
        font-size: 24px;
        font-weight: 800;
        letter-spacing: -0.02em;
        line-height: 1.25;
        margin-top: 6px;
        overflow-wrap: anywhere;
    }

    .dark .registration-overview-key-value {
        color: #f8fafc;
    }

    .registration-overview-total {
        color: #059669;
        font-size: 28px;
        font-weight: 850;
    }

    .dark .registration-overview-total {
        color: #34d399;
    }

    .registration-overview-total-unit {
        font-size: 16px;
        font-weight: 750;
        margin-right: 4px;
    }

    .registration-overview-details {
        border-top: 1px solid rgba(148, 163, 184, 0.18);
        display: grid;
        gap: 16px 24px;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        padding: 18px 20px 20px;
    }

    .registration-overview-detail {
        min-width: 0;
    }

    .registration-overview-detail-value {
        color: #334155;
        font-size: 14px;
        font-weight: 600;
        line-height: 1.55;
        margin-top: 4px;
        overflow-wrap: anywhere;
    }

    .dark .registration-overview-detail-value {
        color: #e4e4e7;
    }

    .registration-overview-status {
        align-items: center;
        border: 1px solid;
        border-radius: 8px;
        display: inline-flex;
        font-size: 13px;
        font-weight: 750;
        line-height: 1;
        padding: 5px 8px;
    }

    .registration-overview-status-confirmed {
        background: rgba(16, 185, 129, 0.08);
        border-color: rgba(16, 185, 129, 0.42);
        color: #047857;
    }

    .dark .registration-overview-status-confirmed {
        background: rgba(52, 211, 153, 0.12);
        border-color: rgba(52, 211, 153, 0.46);
        color: #6ee7b7;
    }

    .registration-overview-status-pending {
        background: rgba(245, 158, 11, 0.08);
        border-color: rgba(245, 158, 11, 0.42);
        color: #b45309;
    }

    .dark .registration-overview-status-pending {
        background: rgba(251, 191, 36, 0.12);
        border-color: rgba(251, 191, 36, 0.46);
        color: #fcd34d;
    }

    @media (max-width: 767px) {
        .registration-overview-primary,
        .registration-overview-details {
            grid-template-columns: 1fr;
        }

        .registration-overview-primary {
            gap: 18px;
        }
    }
</style>

<section class="registration-overview" aria-labelledby="registration-overview-heading">
    <h2 id="registration-overview-heading" class="registration-overview-heading">报名概览</h2>

    <div class="registration-overview-primary">
        <div class="registration-overview-key">
            <div class="registration-overview-key-label">会员棚号</div>
            <div class="registration-overview-key-value">{{ $registration->member?->loft_number ?: '-' }}</div>
        </div>
        <div class="registration-overview-key">
            <div class="registration-overview-key-label">会员参赛名</div>
            <div class="registration-overview-key-value">{{ $registration->member?->participant_name ?: '-' }}</div>
        </div>
        <div class="registration-overview-key">
            <div class="registration-overview-key-label">总金额</div>
            <div class="registration-overview-key-value registration-overview-total"><span class="registration-overview-total-unit">￥</span>{{ RegistrationSummaryService::formatYuan((int) $registration->total_amount_cent) }}</div>
        </div>
    </div>

    <div class="registration-overview-details">
        <div class="registration-overview-detail">
            <div class="registration-overview-detail-label">报名编号</div>
            <div class="registration-overview-detail-value">{{ $registration->registration_no ?: '-' }}</div>
        </div>
        <div class="registration-overview-detail">
            <div class="registration-overview-detail-label">赛事名称</div>
            <div class="registration-overview-detail-value">{{ $registration->race?->name ?: '-' }}</div>
        </div>
        <div class="registration-overview-detail">
            <div class="registration-overview-detail-label">确认状态</div>
            <div class="registration-overview-detail-value"><span class="registration-overview-status {{ $isConfirmed ? 'registration-overview-status-confirmed' : 'registration-overview-status-pending' }}">{{ $statusLabel }}</span></div>
        </div>
        <div class="registration-overview-detail">
            <div class="registration-overview-detail-label">提交时间</div>
            <div class="registration-overview-detail-value">{{ $registration->submitted_at?->format('Y年m月d日 H:i:s') ?: '-' }}</div>
        </div>
        <div class="registration-overview-detail">
            <div class="registration-overview-detail-label">确认时间</div>
            <div class="registration-overview-detail-value">{{ $registration->confirmed_at?->format('Y年m月d日 H:i:s') ?: '-' }}</div>
        </div>
        <div class="registration-overview-detail">
            <div class="registration-overview-detail-label">备注</div>
            <div class="registration-overview-detail-value">{{ $registration->remark ?: '-' }}</div>
        </div>
    </div>
</section>
