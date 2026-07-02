{{-- [IN]: AdminDashboard feature-card data / AdminDashboard 功能卡片数据 --}}
{{-- [OUT]: Compact Filament dashboard navigation cards / 紧凑的 Filament 仪表板导航卡片 --}}
{{-- [POS]: Admin dashboard Blade view / 后台仪表板 Blade 视图 --}}
{{-- Protocol: When updating me, sync this header + parent folder's .folder.md --}}
{{-- 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md --}}

<x-filament-panels::page>
    <style>
        .admin-dashboard-shell {
            max-width: 1180px;
        }

        .admin-dashboard-intro {
            display: flex;
            align-items: end;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .admin-dashboard-kicker {
            margin: 0 0 .3rem;
            color: color-mix(in oklch, currentColor 54%, transparent);
            font-size: .84rem;
            font-weight: 650;
        }

        .admin-dashboard-title {
            margin: 0;
            color: color-mix(in oklch, currentColor 96%, transparent);
            font-size: clamp(1.55rem, 2vw, 2rem);
            font-weight: 780;
            letter-spacing: 0;
        }

        .admin-dashboard-hint {
            max-width: 28rem;
            margin: 0;
            color: color-mix(in oklch, currentColor 58%, transparent);
            font-size: .92rem;
            line-height: 1.55;
            text-align: right;
        }

        .admin-dashboard-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: .9rem;
        }

        .admin-dashboard-card {
            position: relative;
            display: grid;
            grid-template-rows: auto 1fr;
            min-height: 9.25rem;
            overflow: hidden;
            border: 1px solid color-mix(in oklch, var(--card-accent) 24%, transparent);
            border-radius: 1rem;
            background:
                radial-gradient(circle at 90% 8%, color-mix(in oklch, var(--card-accent) 16%, transparent), transparent 34%),
                color-mix(in oklch, var(--card-accent) 4%, var(--fi-panel-bg, oklch(0.16 0.006 164)));
            padding: 1.05rem;
            color: inherit;
            text-decoration: none;
            transition: border-color .18s ease, box-shadow .18s ease, transform .18s ease;
        }

        .admin-dashboard-card:hover,
        .admin-dashboard-card:focus-visible {
            border-color: color-mix(in oklch, var(--card-accent) 58%, transparent);
            box-shadow: 0 18px 38px color-mix(in oklch, var(--card-accent) 12%, transparent);
            transform: translateY(-2px);
            outline: none;
        }

        .admin-dashboard-card-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .8rem;
            min-height: 2.75rem;
        }

        .admin-dashboard-icon {
            display: grid;
            width: 2.75rem;
            height: 2.75rem;
            place-items: center;
            border-radius: .85rem;
            background: color-mix(in oklch, var(--card-accent) 18%, transparent);
            color: var(--card-accent);
        }

        .admin-dashboard-icon svg {
            width: 1.35rem;
            height: 1.35rem;
        }

        .admin-dashboard-arrow {
            display: grid;
            width: 1.5rem;
            height: 1.5rem;
            place-items: center;
            color: color-mix(in oklch, var(--card-accent) 82%, currentColor);
            font-size: 1.35rem;
            line-height: 1;
        }

        .admin-dashboard-copy {
            display: grid;
            align-content: end;
            gap: .42rem;
            min-height: 4.7rem;
        }

        .admin-dashboard-label {
            display: block;
            margin: 0;
            color: color-mix(in oklch, currentColor 98%, transparent);
            font-size: 1.16rem;
            font-weight: 760;
            line-height: 1.25;
            letter-spacing: 0;
        }

        .admin-dashboard-description {
            display: block;
            margin: 0;
            color: color-mix(in oklch, currentColor 62%, transparent);
            font-size: .9rem;
            line-height: 1.55;
        }

        @media (max-width: 1100px) {
            .admin-dashboard-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 680px) {
            .admin-dashboard-intro {
                display: block;
            }

            .admin-dashboard-hint {
                margin-top: .45rem;
                text-align: left;
            }

            .admin-dashboard-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <section class="admin-dashboard-shell">

        <div class="admin-dashboard-grid">
            @foreach ($this->featureCards() as $card)
                <a
                    class="admin-dashboard-card"
                    href="{{ $card['href'] }}"
                    style="--card-accent: {{ $card['accent'] }}"
                    wire:navigate
                >
                    <span class="admin-dashboard-card-top">
                        <span class="admin-dashboard-icon">
                            <x-filament::icon :icon="$card['icon']" />
                        </span>
                        <span class="admin-dashboard-arrow" aria-hidden="true">›</span>
                    </span>

                    <span class="admin-dashboard-copy">
                        <span class="admin-dashboard-label">{{ $card['label'] }}</span>
                        <span class="admin-dashboard-description">{{ $card['description'] }}</span>
                    </span>
                </a>
            @endforeach
        </div>
    </section>
</x-filament-panels::page>
