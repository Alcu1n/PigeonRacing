{{-- [IN]: Exact buyer-name ring-sale summary data and Filament page actions / 精确姓名售环汇总数据与 Filament 页面操作 --}}
{{-- [OUT]: Compact responsive summary cards, receipt gallery, lightbox, and sale history / 紧凑响应式汇总卡片、收据画廊、灯箱与售环历史 --}}
{{-- [POS]: Ring-sale buyer summary page view / 售环姓名汇总页视图 --}}
{{-- Protocol: When updating me, sync this header + parent folder's .folder.md --}}
{{-- 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md --}}

@php
    $money = static fn (int $cent): string => '¥'.number_format($cent / 100, 2);
    $summaryMoney = static fn (int $cent): string => '¥'.number_format($cent / 100, 0);
    $paymentClass = static fn ($sale): string => $sale->status === 'void'
        ? 'void'
        : ($sale->paid_amount_cent === 0 ? 'unpaid' : ($sale->unpaid_amount_cent === 0 ? 'paid' : 'partial'));
    $receiptGroups = $summary['sales']
        ->map(fn ($sale): array => ['sale' => $sale, 'receipts' => $sale->receipts])
        ->filter(fn (array $group): bool => $group['receipts']->isNotEmpty())
        ->values();
    $receiptImages = $receiptGroups
        ->flatMap(fn (array $group) => $group['receipts']->map(fn ($receipt): array => [
            'url' => route('admin.ring-sale-receipts.show', $receipt),
            'alt' => $group['sale']->sale_no.' · '.$receipt->original_name,
            'caption' => $group['sale']->sale_no.' · '.$group['sale']->sale_date->format('Y-m-d'),
        ]))
        ->values();
    $receiptIndex = 0;
@endphp

<x-filament-panels::page>
    <div
        class="ring-sale-buyer-summary"
        x-data="{
            images: {{ \Illuminate\Support\Js::from($receiptImages->all()) }},
            activeImage: null,
            openImage(index) {
                this.activeImage = index;
            },
            closeImage() {
                this.activeImage = null;
            },
            previousImage() {
                if (! this.images.length) return;
                this.activeImage = (this.activeImage - 1 + this.images.length) % this.images.length;
            },
            nextImage() {
                if (! this.images.length) return;
                this.activeImage = (this.activeImage + 1) % this.images.length;
            },
        }"
        x-on:keydown.escape.window="closeImage()"
        x-on:keydown.arrow-left.window="if (activeImage !== null) previousImage()"
        x-on:keydown.arrow-right.window="if (activeImage !== null) nextImage()"
    >
        <section class="ring-sale-buyer-summary__hero">
            <div>
                <p class="ring-sale-buyer-summary__eyebrow">{{ __('姓名售环汇总') }}</p>
                <h1>{{ $summary['buyer_name'] !== '' ? $summary['buyer_name'] : __('未指定姓名') }}</h1>
                <p class="ring-sale-buyer-summary__subline">
                    {{ number_format($summary['record_count']) }} {{ __('笔售环记录') }}
                    · {{ number_format($summary['active_record_count']) }} {{ __('笔有效') }}
                    @if ($summary['void_record_count'] > 0)
                        · {{ number_format($summary['void_record_count']) }} {{ __('笔作废') }}
                    @endif
                </p>
            </div>
            <div class="ring-sale-buyer-summary__hero-meta">
                <span class="ring-sale-buyer-summary__status-pill">{{ __('精确姓名匹配') }}</span>
                @if ($summary['receipt_count'] > 0)
                    <span class="ring-sale-buyer-summary__muted-pill">{{ number_format($summary['receipt_count']) }} {{ __('张收据') }}</span>
                @endif
            </div>
        </section>

        <section class="ring-sale-buyer-summary__overview">
            <div class="ring-sale-buyer-summary__panel ring-sale-buyer-summary__categories">
                <div class="ring-sale-buyer-summary__panel-heading">
                    <div>
                        <h2>{{ __('足环数量') }}</h2>
                        <p>{{ __('按足环类别汇总有效售环记录') }}</p>
                    </div>
                    <strong>{{ number_format($summary['total_quantity']) }} {{ __('枚') }}</strong>
                </div>

                <div class="ring-sale-buyer-summary__category-grid">
                    <div class="ring-sale-buyer-summary__category-card ring-sale-buyer-summary__category-card--total">
                        <span>{{ __('足环总数') }}</span>
                        <strong>{{ number_format($summary['total_quantity']) }}</strong>
                        <small>{{ __('枚') }}</small>
                    </div>
                    @forelse ($summary['category_quantities'] as $category)
                        <div class="ring-sale-buyer-summary__category-card">
                            <span>{{ $category['name'] }}</span>
                            <strong>{{ number_format($category['quantity']) }}</strong>
                            <small>{{ __('枚') }}</small>
                        </div>
                    @empty
                        <div class="ring-sale-buyer-summary__empty-inline">{{ __('暂无有效足环明细') }}</div>
                    @endforelse
                </div>
            </div>

            <div class="ring-sale-buyer-summary__panel ring-sale-buyer-summary__financials">
                <div class="ring-sale-buyer-summary__panel-heading">
                    <div>
                        <h2>{{ __('金额汇总') }}</h2>
                        <p>{{ __('仅统计有效售环记录和有效收款') }}</p>
                    </div>
                </div>

                <div class="ring-sale-buyer-summary__financial-grid">
                    <div class="ring-sale-buyer-summary__financial-card">
                        <span>{{ __('应收金额') }}</span>
                        <strong>{{ $summaryMoney($summary['total_amount_cent']) }}</strong>
                    </div>
                    <div class="ring-sale-buyer-summary__financial-card ring-sale-buyer-summary__financial-card--paid">
                        <span>{{ __('已付金额') }}</span>
                        <strong>{{ $summaryMoney($summary['paid_amount_cent']) }}</strong>
                    </div>
                    <div class="ring-sale-buyer-summary__financial-card ring-sale-buyer-summary__financial-card--unpaid">
                        <span>{{ __('未付金额') }}</span>
                        <strong>{{ $summaryMoney($summary['unpaid_amount_cent']) }}</strong>
                    </div>
                </div>
            </div>
        </section>

        <section class="ring-sale-buyer-summary__panel ring-sale-buyer-summary__receipts-panel">
            <div class="ring-sale-buyer-summary__panel-heading">
                <div>
                    <h2>{{ __('收据照片') }}</h2>
                    <p>{{ __('按售环单分组展示，点击照片可查看大图') }}</p>
                </div>
                <strong>{{ number_format($summary['receipt_count']) }} {{ __('张') }}</strong>
            </div>

            @if ($receiptGroups->isEmpty())
                <div class="ring-sale-buyer-summary__empty-state ring-sale-buyer-summary__empty-state--compact">
                    <x-filament::icon icon="heroicon-o-photo" class="ring-sale-buyer-summary__empty-icon" />
                    <span>{{ __('暂无收据照片') }}</span>
                </div>
            @else
                <div class="ring-sale-buyer-summary__receipt-groups">
                    @foreach ($receiptGroups as $group)
                        <div class="ring-sale-buyer-summary__receipt-group">
                            <div class="ring-sale-buyer-summary__receipt-heading">
                                <div>
                                    <strong>{{ $group['sale']->sale_no }}</strong>
                                    <span>{{ $group['sale']->sale_date->format('Y-m-d') }}</span>
                                </div>
                                @if ($group['sale']->status === 'void')
                                    <span class="ring-sale-buyer-summary__void-pill">{{ __('作废记录') }}</span>
                                @endif
                            </div>
                            <div class="ring-sale-buyer-summary__receipt-grid">
                                @foreach ($group['receipts'] as $receipt)
                                    <button
                                        type="button"
                                        class="ring-sale-buyer-summary__receipt-thumb"
                                        x-on:click="openImage({{ $receiptIndex }})"
                                        aria-label="{{ __('查看 :sale_no 收据照片', ['sale_no' => $group['sale']->sale_no]) }}"
                                    >
                                        <img
                                            src="{{ route('admin.ring-sale-receipts.show', $receipt) }}"
                                            alt="{{ __(':sale_no 收据照片 :name', ['sale_no' => $group['sale']->sale_no, 'name' => $receipt->original_name]) }}"
                                            loading="lazy"
                                        >
                                        <span>{{ $receipt->original_name }}</span>
                                    </button>
                                    @php($receiptIndex++)
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="ring-sale-buyer-summary__panel ring-sale-buyer-summary__records-panel">
            <div class="ring-sale-buyer-summary__panel-heading">
                <div>
                    <h2>{{ __('售环列表') }}</h2>
                    <p>{{ __('展示此姓名下全部售环记录，作废记录保留用于历史追溯') }}</p>
                </div>
                <span class="ring-sale-buyer-summary__muted-pill">{{ __('共 :count 笔', ['count' => number_format($summary['record_count'])]) }}</span>
            </div>

            @if ($summary['sales']->isEmpty())
                <div class="ring-sale-buyer-summary__empty-state">
                    <x-filament::icon icon="heroicon-o-document-magnifying-glass" class="ring-sale-buyer-summary__empty-icon" />
                    <strong>{{ __('暂无匹配的售环记录') }}</strong>
                    <span>{{ __('该姓名当前没有可查看的售环记录。') }}</span>
                    <a href="{{ \App\Filament\Resources\RingSaleResource::getUrl('index') }}" class="ring-sale-buyer-summary__empty-link">{{ __('返回售环列表') }}</a>
                </div>
            @else
                <div class="ring-sale-buyer-summary__desktop-table">
                    <div class="ring-sale-buyer-summary__table-scroll">
                        <table>
                            <thead>
                                <tr>
                                    <th>{{ __('售环日期') }}</th>
                                    <th>{{ __('状态') }}</th>
                                    <th>{{ __('售环单号') }}</th>
                                    <th>{{ __('号码段明细') }}</th>
                                    <th>{{ __('数量') }}</th>
                                    <th>{{ __('总金额') }}</th>
                                    <th>{{ __('已付') }}</th>
                                    <th>{{ __('未付') }}</th>
                                    <th class="ring-sale-buyer-summary__actions-heading">{{ __('操作') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($summary['sales'] as $sale)
                                    <tr>
                                        <td class="ring-sale-buyer-summary__nowrap">{{ $sale->sale_date->format('Y-m-d') }}</td>
                                        <td>
                                            <span class="ring-sale-buyer-summary__payment-pill ring-sale-buyer-summary__payment-pill--{{ $paymentClass($sale) }}">
                                                {{ $sale->payment_status_label }}
                                            </span>
                                        </td>
                                        <td class="ring-sale-buyer-summary__nowrap"><strong>{{ $sale->sale_no }}</strong></td>
                                        <td class="ring-sale-buyer-summary__segments">
                                            {{ $sale->items->map(fn ($item): string => $item->category_name_snapshot.' · '.$item->start_ring.'–'.$item->end_ring.'（'.$item->quantity.__('枚').'）')->implode('；') }}
                                        </td>
                                        <td class="ring-sale-buyer-summary__nowrap">{{ number_format($sale->total_quantity) }}</td>
                                        <td class="ring-sale-buyer-summary__nowrap">{{ $money($sale->total_amount_cent) }}</td>
                                        <td class="ring-sale-buyer-summary__nowrap ring-sale-buyer-summary__money--paid">{{ $money($sale->paid_amount_cent) }}</td>
                                        <td class="ring-sale-buyer-summary__nowrap ring-sale-buyer-summary__money--{{ $sale->unpaid_amount_cent > 0 ? 'unpaid' : 'paid' }}">{{ $money($sale->unpaid_amount_cent) }}</td>
                                        <td class="ring-sale-buyer-summary__actions-cell">
                                            <x-filament-actions::group
                                                :actions="$this->getSaleActions($sale)"
                                                icon-button
                                                icon="heroicon-m-ellipsis-vertical"
                                                :tooltip="__('更多操作')"
                                            />
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="ring-sale-buyer-summary__mobile-cards">
                    @foreach ($summary['sales'] as $sale)
                        <article class="ring-sale-buyer-summary__sale-card">
                            <div class="ring-sale-buyer-summary__sale-card-heading">
                                <div>
                                    <strong>{{ $sale->sale_no }}</strong>
                                    <span>{{ $sale->sale_date->format('Y-m-d') }}</span>
                                </div>
                                <span class="ring-sale-buyer-summary__payment-pill ring-sale-buyer-summary__payment-pill--{{ $paymentClass($sale) }}">
                                    {{ $sale->payment_status_label }}
                                </span>
                            </div>
                            <div class="ring-sale-buyer-summary__sale-card-stats">
                                <div><span>{{ __('数量') }}</span><strong>{{ number_format($sale->total_quantity) }} {{ __('枚') }}</strong></div>
                                <div><span>{{ __('总金额') }}</span><strong>{{ $money($sale->total_amount_cent) }}</strong></div>
                                <div><span>{{ __('未付') }}</span><strong class="ring-sale-buyer-summary__money--{{ $sale->unpaid_amount_cent > 0 ? 'unpaid' : 'paid' }}">{{ $money($sale->unpaid_amount_cent) }}</strong></div>
                            </div>
                            <div class="ring-sale-buyer-summary__sale-card-segments">
                                @foreach ($sale->items as $item)
                                    <span>{{ $item->category_name_snapshot }} · {{ $item->start_ring }}–{{ $item->end_ring }}（{{ $item->quantity }}{{ __('枚') }}）</span>
                                @endforeach
                            </div>
                            <div class="ring-sale-buyer-summary__sale-card-footer">
                                <span class="ring-sale-buyer-summary__money--paid">{{ __('已付') }} {{ $money($sale->paid_amount_cent) }}</span>
                                <x-filament-actions::group
                                    :actions="$this->getSaleActions($sale)"
                                    icon-button
                                    icon="heroicon-m-ellipsis-vertical"
                                    :tooltip="__('更多操作')"
                                />
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>

        <div
            x-cloak
            x-show="activeImage !== null"
            class="ring-sale-buyer-summary__lightbox"
            x-transition.opacity
            role="dialog"
            aria-modal="true"
            :aria-label="__('收据照片预览')"
            x-on:click.self="closeImage()"
        >
            <div class="ring-sale-buyer-summary__lightbox-content">
                <button
                    type="button"
                    class="ring-sale-buyer-summary__lightbox-close"
                    :aria-label="__('关闭照片预览')"
                    x-on:click="closeImage()"
                >
                    <x-filament::icon icon="heroicon-o-x-mark" />
                </button>
                <button
                    type="button"
                    class="ring-sale-buyer-summary__lightbox-nav ring-sale-buyer-summary__lightbox-nav--previous"
                    :aria-label="__('上一张')"
                    x-show="images.length > 1"
                    x-on:click="previousImage()"
                >
                    <x-filament::icon icon="heroicon-o-chevron-left" />
                </button>
                <img
                    class="ring-sale-buyer-summary__lightbox-image"
                    x-bind:src="activeImage !== null ? images[activeImage].url : ''"
                    x-bind:alt="activeImage !== null ? images[activeImage].alt : ''"
                >
                <button
                    type="button"
                    class="ring-sale-buyer-summary__lightbox-nav ring-sale-buyer-summary__lightbox-nav--next"
                    :aria-label="__('下一张')"
                    x-show="images.length > 1"
                    x-on:click="nextImage()"
                >
                    <x-filament::icon icon="heroicon-o-chevron-right" />
                </button>
                <p class="ring-sale-buyer-summary__lightbox-caption" x-text="activeImage !== null ? images[activeImage].caption : ''"></p>
            </div>
        </div>
    </div>

    <style>
        .ring-sale-buyer-summary {
            display: grid;
            gap: .8rem;
            max-width: 100%;
            color: rgb(15 23 42);
        }

        .dark .ring-sale-buyer-summary {
            color: rgb(241 245 249);
        }

        .ring-sale-buyer-summary__hero,
        .ring-sale-buyer-summary__panel {
            border: 1px solid rgb(226 232 240);
            border-radius: .9rem;
            background: rgb(255 255 255 / .92);
            box-shadow: 0 8px 24px rgb(15 23 42 / .04);
        }

        .dark .ring-sale-buyer-summary__hero,
        .dark .ring-sale-buyer-summary__panel {
            border-color: rgb(63 63 70);
            background: rgb(24 24 27 / .92);
            box-shadow: none;
        }

        .ring-sale-buyer-summary__hero {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 1.1rem 1.2rem;
        }

        .ring-sale-buyer-summary__eyebrow,
        .ring-sale-buyer-summary__subline,
        .ring-sale-buyer-summary__panel-heading p,
        .ring-sale-buyer-summary__receipt-heading span,
        .ring-sale-buyer-summary__sale-card-heading span {
            color: rgb(71 85 105);
        }

        .dark .ring-sale-buyer-summary__eyebrow,
        .dark .ring-sale-buyer-summary__subline,
        .dark .ring-sale-buyer-summary__panel-heading p,
        .dark .ring-sale-buyer-summary__receipt-heading span,
        .dark .ring-sale-buyer-summary__sale-card-heading span {
            color: rgb(161 161 170);
        }

        .ring-sale-buyer-summary__eyebrow {
            margin: 0 0 .25rem;
            font-size: .72rem;
            font-weight: 700;
            letter-spacing: .08em;
        }

        .ring-sale-buyer-summary h1,
        .ring-sale-buyer-summary h2,
        .ring-sale-buyer-summary p {
            margin: 0;
        }

        .ring-sale-buyer-summary h1 {
            font-size: clamp(1.3rem, 2vw, 1.65rem);
            line-height: 1.3;
        }

        .ring-sale-buyer-summary h2 {
            font-size: .98rem;
            font-weight: 750;
        }

        .ring-sale-buyer-summary__subline {
            margin-top: .35rem !important;
            font-size: .8rem;
        }

        .ring-sale-buyer-summary__hero-meta,
        .ring-sale-buyer-summary__receipt-heading,
        .ring-sale-buyer-summary__sale-card-heading,
        .ring-sale-buyer-summary__sale-card-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .55rem;
        }

        .ring-sale-buyer-summary__hero-meta {
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .ring-sale-buyer-summary__status-pill,
        .ring-sale-buyer-summary__muted-pill,
        .ring-sale-buyer-summary__void-pill,
        .ring-sale-buyer-summary__payment-pill {
            display: inline-flex;
            align-items: center;
            min-height: 1.55rem;
            padding: .2rem .55rem;
            border-radius: 999px;
            font-size: .7rem;
            font-weight: 700;
            line-height: 1;
            white-space: nowrap;
        }

        .ring-sale-buyer-summary__status-pill {
            color: rgb(6 95 70);
            background: rgb(209 250 229);
        }

        .ring-sale-buyer-summary__muted-pill {
            color: rgb(71 85 105);
            background: rgb(241 245 249);
        }

        .dark .ring-sale-buyer-summary__muted-pill {
            color: rgb(203 213 225);
            background: rgb(63 63 70);
        }

        .ring-sale-buyer-summary__void-pill,
        .ring-sale-buyer-summary__payment-pill--void {
            color: rgb(127 29 29);
            background: rgb(254 226 226);
        }

        .ring-sale-buyer-summary__payment-pill--paid {
            color: rgb(6 95 70);
            background: rgb(209 250 229);
        }

        .ring-sale-buyer-summary__payment-pill--partial {
            color: rgb(146 64 14);
            background: rgb(254 215 170);
        }

        .ring-sale-buyer-summary__payment-pill--unpaid {
            color: rgb(153 27 27);
            background: rgb(254 226 226);
        }

        .dark .ring-sale-buyer-summary__status-pill,
        .dark .ring-sale-buyer-summary__payment-pill--paid {
            color: rgb(167 243 208);
            background: rgb(6 78 59);
        }

        .dark .ring-sale-buyer-summary__void-pill,
        .dark .ring-sale-buyer-summary__payment-pill--void,
        .dark .ring-sale-buyer-summary__payment-pill--unpaid {
            color: rgb(254 202 202);
            background: rgb(127 29 29);
        }

        .dark .ring-sale-buyer-summary__payment-pill--partial {
            color: rgb(254 215 170);
            background: rgb(124 45 18);
        }

        .ring-sale-buyer-summary__overview {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: .8rem;
        }

        .ring-sale-buyer-summary__panel {
            min-width: 0;
            padding: .9rem;
        }

        .ring-sale-buyer-summary__panel-heading {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: .8rem;
            margin-bottom: .75rem;
        }

        .ring-sale-buyer-summary__panel-heading p {
            margin-top: .2rem;
            font-size: .75rem;
            line-height: 1.35;
        }

        .ring-sale-buyer-summary__panel-heading > strong {
            color: rgb(15 118 110);
            font-size: 1.15rem;
            line-height: 1.2;
            white-space: nowrap;
        }

        .dark .ring-sale-buyer-summary__panel-heading > strong {
            color: rgb(94 234 212);
        }

        .ring-sale-buyer-summary__category-grid,
        .ring-sale-buyer-summary__financial-grid {
            display: grid;
            gap: .55rem;
        }

        .ring-sale-buyer-summary__category-grid {
            grid-template-columns: repeat(auto-fit, minmax(7.8rem, 1fr));
        }

        .ring-sale-buyer-summary__financial-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .ring-sale-buyer-summary__category-card,
        .ring-sale-buyer-summary__financial-card {
            min-width: 0;
            padding: .7rem .75rem;
            border: 1px solid rgb(226 232 240);
            border-radius: .7rem;
            background: rgb(248 250 252);
        }

        .dark .ring-sale-buyer-summary__category-card,
        .dark .ring-sale-buyer-summary__financial-card {
            border-color: rgb(63 63 70);
            background: rgb(39 39 42);
        }

        .ring-sale-buyer-summary__category-card span,
        .ring-sale-buyer-summary__financial-card span {
            display: block;
            overflow: hidden;
            color: rgb(71 85 105);
            font-size: .72rem;
            line-height: 1.15;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .dark .ring-sale-buyer-summary__category-card span,
        .dark .ring-sale-buyer-summary__financial-card span {
            color: rgb(161 161 170);
        }

        .ring-sale-buyer-summary__category-card strong,
        .ring-sale-buyer-summary__financial-card strong {
            display: block;
            margin-top: .28rem;
            overflow: hidden;
            font-size: 1.1rem;
            line-height: 1.25;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .ring-sale-buyer-summary__category-card small {
            display: block;
            margin-top: .1rem;
            color: rgb(100 116 139);
            font-size: .68rem;
        }

        .ring-sale-buyer-summary__category-card--total {
            border-color: rgb(153 246 228);
            background: rgb(240 253 250);
        }

        .ring-sale-buyer-summary__financial-card--paid strong,
        .ring-sale-buyer-summary__money--paid {
            color: rgb(5 150 105);
        }

        .ring-sale-buyer-summary__financial-card--unpaid strong,
        .ring-sale-buyer-summary__money--unpaid {
            color: rgb(220 38 38);
        }

        .ring-sale-buyer-summary__empty-inline {
            grid-column: 1 / -1;
            padding: .8rem;
            color: rgb(100 116 139);
            font-size: .8rem;
            text-align: center;
        }

        .ring-sale-buyer-summary__receipts-panel,
        .ring-sale-buyer-summary__records-panel {
            overflow: hidden;
        }

        .ring-sale-buyer-summary__receipt-groups {
            display: grid;
            gap: .75rem;
        }

        .ring-sale-buyer-summary__receipt-group {
            padding: .7rem;
            border: 1px solid rgb(226 232 240);
            border-radius: .7rem;
            background: rgb(248 250 252);
        }

        .dark .ring-sale-buyer-summary__receipt-group {
            border-color: rgb(63 63 70);
            background: rgb(39 39 42);
        }

        .ring-sale-buyer-summary__receipt-heading {
            margin-bottom: .55rem;
        }

        .ring-sale-buyer-summary__receipt-heading > div,
        .ring-sale-buyer-summary__sale-card-heading > div {
            display: flex;
            align-items: baseline;
            flex-wrap: wrap;
            gap: .45rem;
        }

        .ring-sale-buyer-summary__receipt-heading strong,
        .ring-sale-buyer-summary__sale-card-heading strong {
            font-size: .8rem;
        }

        .ring-sale-buyer-summary__receipt-heading span,
        .ring-sale-buyer-summary__sale-card-heading span {
            font-size: .72rem;
        }

        .ring-sale-buyer-summary__receipt-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(7.2rem, 1fr));
            gap: .55rem;
        }

        .ring-sale-buyer-summary__receipt-thumb {
            display: grid;
            gap: .35rem;
            min-width: 0;
            padding: 0;
            border: 0;
            color: inherit;
            background: transparent;
            text-align: left;
            cursor: pointer;
        }

        .ring-sale-buyer-summary__receipt-thumb img {
            display: block;
            width: 100%;
            aspect-ratio: 1.25;
            border: 1px solid rgb(226 232 240);
            border-radius: .55rem;
            object-fit: cover;
            transition: border-color 180ms ease, opacity 180ms ease;
        }

        .ring-sale-buyer-summary__receipt-thumb:hover img,
        .ring-sale-buyer-summary__receipt-thumb:focus-visible img {
            border-color: rgb(20 184 166);
            opacity: .82;
        }

        .ring-sale-buyer-summary__receipt-thumb:focus-visible {
            outline: 2px solid rgb(20 184 166);
            outline-offset: 3px;
            border-radius: .55rem;
        }

        .ring-sale-buyer-summary__receipt-thumb span {
            overflow: hidden;
            color: rgb(71 85 105);
            font-size: .68rem;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .dark .ring-sale-buyer-summary__receipt-thumb span {
            color: rgb(203 213 225);
        }

        .ring-sale-buyer-summary__table-scroll {
            max-width: 100%;
            overflow-x: auto;
        }

        .ring-sale-buyer-summary__desktop-table table {
            width: 100%;
            border-collapse: collapse;
            font-size: .78rem;
        }

        .ring-sale-buyer-summary__desktop-table th,
        .ring-sale-buyer-summary__desktop-table td {
            padding: .62rem .55rem;
            border-bottom: 1px solid rgb(226 232 240);
            text-align: left;
            vertical-align: middle;
        }

        .dark .ring-sale-buyer-summary__desktop-table th,
        .dark .ring-sale-buyer-summary__desktop-table td {
            border-color: rgb(63 63 70);
        }

        .ring-sale-buyer-summary__desktop-table th {
            color: rgb(71 85 105);
            font-size: .69rem;
            font-weight: 700;
            white-space: nowrap;
        }

        .dark .ring-sale-buyer-summary__desktop-table th {
            color: rgb(161 161 170);
        }

        .ring-sale-buyer-summary__desktop-table tbody tr:last-child td {
            border-bottom: 0;
        }

        .ring-sale-buyer-summary__nowrap {
            white-space: nowrap;
        }

        .ring-sale-buyer-summary__segments {
            max-width: 22rem;
            color: rgb(71 85 105);
            line-height: 1.45;
        }

        .dark .ring-sale-buyer-summary__segments {
            color: rgb(203 213 225);
        }

        .ring-sale-buyer-summary__actions-heading,
        .ring-sale-buyer-summary__actions-cell {
            text-align: right !important;
        }

        .ring-sale-buyer-summary__mobile-cards {
            display: none;
        }

        .ring-sale-buyer-summary__sale-card {
            display: grid;
            gap: .65rem;
            padding: .8rem;
            border: 1px solid rgb(226 232 240);
            border-radius: .75rem;
            background: rgb(248 250 252);
        }

        .dark .ring-sale-buyer-summary__sale-card {
            border-color: rgb(63 63 70);
            background: rgb(39 39 42);
        }

        .ring-sale-buyer-summary__sale-card-stats {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: .45rem;
        }

        .ring-sale-buyer-summary__sale-card-stats div {
            min-width: 0;
            padding: .45rem .5rem;
            border-radius: .5rem;
            background: rgb(255 255 255 / .72);
        }

        .dark .ring-sale-buyer-summary__sale-card-stats div {
            background: rgb(24 24 27 / .7);
        }

        .ring-sale-buyer-summary__sale-card-stats span {
            display: block;
            color: rgb(100 116 139);
            font-size: .68rem;
        }

        .ring-sale-buyer-summary__sale-card-stats strong {
            display: block;
            margin-top: .18rem;
            overflow: hidden;
            font-size: .82rem;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .ring-sale-buyer-summary__sale-card-segments {
            display: grid;
            gap: .25rem;
            color: rgb(71 85 105);
            font-size: .72rem;
            line-height: 1.45;
        }

        .dark .ring-sale-buyer-summary__sale-card-segments {
            color: rgb(203 213 225);
        }

        .ring-sale-buyer-summary__sale-card-footer {
            padding-top: .4rem;
            border-top: 1px solid rgb(226 232 240);
            font-size: .72rem;
        }

        .dark .ring-sale-buyer-summary__sale-card-footer {
            border-color: rgb(63 63 70);
        }

        .ring-sale-buyer-summary__empty-state {
            display: grid;
            justify-items: center;
            gap: .4rem;
            padding: 2.2rem 1rem;
            color: rgb(71 85 105);
            font-size: .82rem;
            text-align: center;
        }

        .dark .ring-sale-buyer-summary__empty-state {
            color: rgb(203 213 225);
        }

        .ring-sale-buyer-summary__empty-state--compact {
            padding-block: 1.4rem;
        }

        .ring-sale-buyer-summary__empty-icon {
            width: 1.8rem;
            height: 1.8rem;
            color: rgb(100 116 139);
        }

        .ring-sale-buyer-summary__empty-link {
            margin-top: .25rem;
            color: rgb(13 148 136);
            font-weight: 700;
        }

        .ring-sale-buyer-summary__lightbox {
            position: fixed;
            z-index: 100;
            inset: 0;
            display: grid;
            place-items: center;
            padding: 1rem;
            background: rgb(2 6 23 / .84);
        }

        .ring-sale-buyer-summary__lightbox-content {
            position: relative;
            display: grid;
            max-width: min(94vw, 70rem);
            max-height: 94vh;
            place-items: center;
        }

        .ring-sale-buyer-summary__lightbox-image {
            display: block;
            max-width: 92vw;
            max-height: 82vh;
            border-radius: .7rem;
            object-fit: contain;
            box-shadow: 0 20px 60px rgb(0 0 0 / .35);
        }

        .ring-sale-buyer-summary__lightbox-close,
        .ring-sale-buyer-summary__lightbox-nav {
            position: absolute;
            z-index: 2;
            display: grid;
            width: 2.35rem;
            height: 2.35rem;
            place-items: center;
            border: 1px solid rgb(255 255 255 / .22);
            border-radius: 999px;
            color: white;
            background: rgb(15 23 42 / .72);
            cursor: pointer;
            transition: background 180ms ease;
        }

        .ring-sale-buyer-summary__lightbox-close:hover,
        .ring-sale-buyer-summary__lightbox-close:focus-visible,
        .ring-sale-buyer-summary__lightbox-nav:hover,
        .ring-sale-buyer-summary__lightbox-nav:focus-visible {
            background: rgb(13 148 136 / .92);
            outline: none;
        }

        .ring-sale-buyer-summary__lightbox-close {
            top: -1rem;
            right: -1rem;
        }

        .ring-sale-buyer-summary__lightbox-nav--previous {
            left: -3.5rem;
        }

        .ring-sale-buyer-summary__lightbox-nav--next {
            right: -3.5rem;
        }

        .ring-sale-buyer-summary__lightbox-caption {
            margin-top: .65rem;
            color: rgb(226 232 240);
            font-size: .78rem;
            text-align: center;
        }

        @media (max-width: 900px) {
            .ring-sale-buyer-summary__overview {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 767px) {
            .ring-sale-buyer-summary {
                gap: .6rem;
            }

            .ring-sale-buyer-summary__hero {
                align-items: flex-start;
                padding: .9rem;
            }

            .ring-sale-buyer-summary__hero-meta {
                display: none;
            }

            .ring-sale-buyer-summary__panel {
                padding: .75rem;
                border-radius: .75rem;
            }

            .ring-sale-buyer-summary__panel-heading {
                margin-bottom: .6rem;
            }

            .ring-sale-buyer-summary__category-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .ring-sale-buyer-summary__category-card,
            .ring-sale-buyer-summary__financial-card {
                padding: .6rem;
            }

            .ring-sale-buyer-summary__financial-card strong {
                font-size: .92rem;
            }

            .ring-sale-buyer-summary__desktop-table {
                display: none;
            }

            .ring-sale-buyer-summary__mobile-cards {
                display: grid;
                gap: .55rem;
            }

            .ring-sale-buyer-summary__lightbox-nav--previous {
                left: .4rem;
            }

            .ring-sale-buyer-summary__lightbox-nav--next {
                right: .4rem;
            }

            .ring-sale-buyer-summary__lightbox-close {
                top: .4rem;
                right: .4rem;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .ring-sale-buyer-summary__receipt-thumb img,
            .ring-sale-buyer-summary__lightbox-close,
            .ring-sale-buyer-summary__lightbox-nav {
                transition: none;
            }
        }
    </style>
</x-filament-panels::page>
