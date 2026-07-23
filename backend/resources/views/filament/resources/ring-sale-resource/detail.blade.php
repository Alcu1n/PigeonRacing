{{-- [IN]: Fully loaded ring sale with items, payments, receipts, and operators / 已加载明细、收款、收据与操作人的售环单 --}}
{{-- [OUT]: Dense mobile-friendly sale detail / 紧凑移动友好的售环详情 --}}
{{-- [POS]: Ring-sale detail modal / 售环详情弹层 --}}
{{-- Protocol: When updating me, sync this header + parent folder's .folder.md --}}
{{-- 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md --}}

@php
    $money = static fn (int $cent): string => '¥'.number_format($cent / 100, 2);
    $statusClass = match ($sale->payment_status_label) {
        '付清' => 'is-paid',
        '部分付款' => 'is-partial',
        '未付款' => 'is-unpaid',
        default => 'is-void',
    };
@endphp

<div class="ring-sale-detail">
    <section class="ring-sale-detail__hero">
        <div>
            <span class="ring-sale-detail__badge {{ $statusClass }}">{{ $sale->payment_status_label }}</span>
            <h3>{{ $sale->buyer_name }} @if($sale->loft_number) · {{ $sale->loft_number }} @endif</h3>
            <p>{{ $sale->sale_date->format('Y-m-d') }} · {{ $sale->sale_no }}</p>
        </div>
        <div class="ring-sale-detail__amount">
            <span>未付金额</span>
            <strong>{{ $money($sale->unpaid_amount_cent) }}</strong>
        </div>
    </section>

    <section class="ring-sale-detail__stats">
        <div><span>足环数量</span><strong>{{ $sale->total_quantity }}</strong></div>
        <div><span>总金额</span><strong>{{ $money($sale->total_amount_cent) }}</strong></div>
        <div><span>已付</span><strong>{{ $money($sale->paid_amount_cent) }}</strong></div>
    </section>

    <section class="ring-sale-detail__section">
        <h4>号码段明细</h4>
        <div class="ring-sale-detail__list">
            @foreach($sale->items as $item)
                <article>
                    <div>
                        <strong>{{ $item->category_name_snapshot }}</strong>
                        <span>{{ $money($item->unit_price_cent) }}/枚</span>
                    </div>
                    <p>{{ $item->start_ring }} – {{ $item->end_ring }}</p>
                    <small>{{ $item->quantity }} 枚 · {{ $money($item->line_amount_cent) }}</small>
                </article>
            @endforeach
        </div>
    </section>

    <section class="ring-sale-detail__section">
        <h4>收款流水</h4>
        <div class="ring-sale-detail__list">
            @forelse($sale->payments as $payment)
                <article class="{{ $payment->status === 'void' ? 'is-muted' : '' }}">
                    <div>
                        <strong>{{ $money($payment->amount_cent) }}</strong>
                        <span>{{ $payment->status === 'active' ? '有效' : '已作废' }}</span>
                    </div>
                    <p>{{ $payment->payment_date->format('Y-m-d') }} · {{ $payment->creator?->name ?? '—' }}</p>
                    @if($payment->remark)<small>{{ $payment->remark }}</small>@endif
                    @if($payment->void_reason)<small>作废原因：{{ $payment->void_reason }}</small>@endif
                </article>
            @empty
                <p class="ring-sale-detail__empty">尚未登记收款</p>
            @endforelse
        </div>
    </section>

    @if($sale->receipts->isNotEmpty())
        <section class="ring-sale-detail__section">
            <h4>收据照片</h4>
            <div class="ring-sale-detail__receipts">
                @foreach($sale->receipts as $receipt)
                    <a href="{{ route('admin.ring-sale-receipts.show', $receipt) }}" target="_blank" rel="noopener">
                        <img src="{{ route('admin.ring-sale-receipts.show', $receipt) }}" alt="收据照片 {{ $loop->iteration }}">
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    @if($sale->remark || $sale->status === 'void')
        <section class="ring-sale-detail__section">
            <h4>备注与状态</h4>
            @if($sale->remark)<p>{{ $sale->remark }}</p>@endif
            @if($sale->status === 'void')
                <p class="ring-sale-detail__danger">作废原因：{{ $sale->void_reason }}</p>
            @endif
        </section>
    @endif

    @if($logs->isNotEmpty())
        <section class="ring-sale-detail__section">
            <h4>操作记录</h4>
            <div class="ring-sale-detail__timeline">
                @foreach($logs as $log)
                    <div>
                        <span>{{ $log->created_at?->format('Y-m-d H:i') }}</span>
                        <strong>{{ match($log->action) {
                            'ring_sale.created' => '新增售环单',
                            'ring_sale.updated' => '编辑售环单',
                            'ring_sale.voided' => '作废售环单',
                            'ring_sale_payment.created' => '登记收款',
                            'ring_sale_payment.updated' => '修改收款',
                            'ring_sale_payment.voided' => '作废收款',
                            default => $log->action,
                        } }}</strong>
                        <small>{{ $log->admin?->name ?? '—' }}</small>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <p class="ring-sale-detail__meta">
        创建人：{{ $sale->creator?->name ?? '—' }} · 创建时间：{{ $sale->created_at?->format('Y-m-d H:i') }}
    </p>
</div>

<style>
    .ring-sale-detail { display: grid; gap: 1rem; color: rgb(30 41 59); }
    .dark .ring-sale-detail { color: rgb(226 232 240); }
    .ring-sale-detail__hero { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; padding: 1rem; border-radius: 1rem; background: rgb(248 250 252); }
    .dark .ring-sale-detail__hero { background: rgb(31 41 55); }
    .ring-sale-detail__hero h3 { margin: .45rem 0 .15rem; font-size: 1.1rem; font-weight: 700; }
    .ring-sale-detail__hero p, .ring-sale-detail__meta { color: rgb(100 116 139); font-size: .8rem; }
    .ring-sale-detail__badge { display: inline-flex; padding: .18rem .55rem; border-radius: 999px; font-size: .72rem; font-weight: 700; }
    .ring-sale-detail__badge.is-paid { color: rgb(4 120 87); background: rgb(209 250 229); }
    .ring-sale-detail__badge.is-partial { color: rgb(180 83 9); background: rgb(254 243 199); }
    .ring-sale-detail__badge.is-unpaid { color: rgb(185 28 28); background: rgb(254 226 226); }
    .ring-sale-detail__badge.is-void { color: rgb(71 85 105); background: rgb(226 232 240); }
    .ring-sale-detail__amount { text-align: right; }
    .ring-sale-detail__amount span { display: block; color: rgb(100 116 139); font-size: .75rem; }
    .ring-sale-detail__amount strong { color: rgb(220 38 38); font-size: 1.25rem; }
    .ring-sale-detail__stats { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .6rem; }
    .ring-sale-detail__stats div { padding: .75rem; border: 1px solid rgb(226 232 240); border-radius: .8rem; }
    .dark .ring-sale-detail__stats div { border-color: rgb(55 65 81); }
    .ring-sale-detail__stats span { display: block; color: rgb(100 116 139); font-size: .72rem; }
    .ring-sale-detail__stats strong { display: block; margin-top: .15rem; }
    .ring-sale-detail__section h4 { margin-bottom: .55rem; font-size: .86rem; font-weight: 700; }
    .ring-sale-detail__list { display: grid; gap: .5rem; }
    .ring-sale-detail__list article { padding: .7rem .8rem; border: 1px solid rgb(226 232 240); border-radius: .8rem; }
    .dark .ring-sale-detail__list article { border-color: rgb(55 65 81); }
    .ring-sale-detail__list article > div { display: flex; justify-content: space-between; gap: .8rem; }
    .ring-sale-detail__list article p { margin-top: .25rem; overflow-wrap: anywhere; font-variant-numeric: tabular-nums; }
    .ring-sale-detail__list article small { display: block; margin-top: .2rem; color: rgb(100 116 139); }
    .ring-sale-detail__list article.is-muted { opacity: .55; }
    .ring-sale-detail__receipts { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .6rem; }
    .ring-sale-detail__receipts img { width: 100%; aspect-ratio: 4 / 3; object-fit: cover; border-radius: .75rem; border: 1px solid rgb(226 232 240); }
    .ring-sale-detail__empty { color: rgb(100 116 139); font-size: .85rem; }
    .ring-sale-detail__danger { color: rgb(220 38 38); }
    .ring-sale-detail__timeline { display: grid; gap: .45rem; }
    .ring-sale-detail__timeline div { display: grid; grid-template-columns: 8.5rem 1fr auto; gap: .6rem; align-items: baseline; padding: .55rem 0; border-bottom: 1px solid rgb(226 232 240); font-size: .78rem; }
    .dark .ring-sale-detail__timeline div { border-color: rgb(55 65 81); }
    .ring-sale-detail__timeline span, .ring-sale-detail__timeline small { color: rgb(100 116 139); }
    @media (max-width: 640px) {
        .ring-sale-detail__hero { align-items: stretch; flex-direction: column; }
        .ring-sale-detail__amount { display: flex; align-items: baseline; justify-content: space-between; text-align: left; }
        .ring-sale-detail__stats { gap: .4rem; }
        .ring-sale-detail__stats div { padding: .6rem; }
        .ring-sale-detail__receipts { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
</style>
