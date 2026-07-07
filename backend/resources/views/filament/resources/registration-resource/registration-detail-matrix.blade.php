{{-- [IN]: Registration detail matrix from RegistrationDetailMatrixService / 来自 RegistrationDetailMatrixService 的报名详情矩阵 --}}
{{-- [OUT]: Dense ring-first single matrix, multi groups, and progressive stage details / 高密度足环优先单羽矩阵、多羽组与递进阶段明细 --}}
{{-- [POS]: Backend admin registration detail matrix view / 后端后台报名详情矩阵视图 --}}
{{-- Protocol: When updating me, sync this header + parent folder's .folder.md --}}
{{-- 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md --}}

@php
    use App\Services\RegistrationSummaryService;

    $single = $matrix['single'];
    $multi = $matrix['multi'];
    $progressive = $matrix['progressive'] ?? [];
@endphp

<style>
    .registration-detail-stack {
        display: grid;
        gap: 16px;
    }

    .registration-detail-panel {
        border: 1px solid rgba(148, 163, 184, 0.22);
        border-radius: 14px;
        background: rgba(255, 255, 255, 0.96);
        box-shadow: 0 10px 28px rgba(15, 23, 42, 0.05);
        overflow: hidden;
    }

    .dark .registration-detail-panel {
        border-color: rgba(255, 255, 255, 0.08);
        background: rgba(24, 24, 27, 0.92);
        box-shadow: none;
    }

    .registration-detail-heading {
        align-items: center;
        border-bottom: 1px solid rgba(148, 163, 184, 0.18);
        display: flex;
        gap: 12px;
        justify-content: space-between;
        padding: 14px 16px;
    }

    .registration-detail-title {
        color: #0f172a;
        font-size: 16px;
        font-weight: 800;
        line-height: 1.25;
    }

    .dark .registration-detail-title {
        color: #f8fafc;
    }

    .registration-detail-meta {
        color: #64748b;
        font-size: 12px;
        font-weight: 600;
        white-space: nowrap;
    }

    .dark .registration-detail-meta {
        color: #a1a1aa;
    }

    .registration-detail-scroll {
        overflow-x: auto;
    }

    .registration-detail-table {
        border-collapse: separate;
        border-spacing: 0;
        min-width: 720px;
        width: 100%;
    }

    .registration-detail-table th,
    .registration-detail-table td {
        border-bottom: 1px solid rgba(148, 163, 184, 0.18);
        color: #111827;
        font-size: 13px;
        line-height: 1.35;
        padding: 9px 10px;
        text-align: center;
        white-space: nowrap;
    }

    .dark .registration-detail-table th,
    .dark .registration-detail-table td {
        color: #f4f4f5;
    }

    .registration-detail-table th {
        background: rgba(241, 245, 249, 0.82);
        color: #475569;
        font-weight: 700;
    }

    .dark .registration-detail-table th {
        background: rgba(39, 39, 42, 0.92);
        color: #d4d4d8;
    }

    .registration-detail-table tbody tr:last-child td {
        border-bottom: 0;
    }

    .registration-detail-project {
        font-weight: 750;
        min-width: 150px;
        text-align: left !important;
    }

    .registration-detail-ring {
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        min-width: 132px;
    }

    .registration-detail-sticky {
        background: rgba(255, 255, 255, 0.98);
        box-shadow: 1px 0 0 rgba(148, 163, 184, 0.18);
        left: 0;
        position: sticky;
        text-align: left !important;
        z-index: 2;
    }

    th.registration-detail-sticky {
        z-index: 4;
    }

    .dark .registration-detail-sticky {
        background: rgba(24, 24, 27, 0.98);
        box-shadow: 1px 0 0 rgba(255, 255, 255, 0.08);
    }

    .registration-detail-check {
        color: #059669;
        font-size: 16px;
        font-weight: 900;
    }

    .registration-detail-empty {
        color: #94a3b8 !important;
    }

    .registration-detail-subpanel {
        padding: 12px;
    }

    .registration-detail-project-title {
        align-items: center;
        color: #0f172a;
        display: flex;
        font-size: 14px;
        font-weight: 800;
        justify-content: space-between;
        margin: 4px 0 10px;
    }

    .dark .registration-detail-project-title {
        color: #f8fafc;
    }
</style>

<div class="registration-detail-stack">
    <section class="registration-detail-panel">
        <div class="registration-detail-heading">
            <div class="registration-detail-title">单羽项目矩阵</div>
            <div class="registration-detail-meta">
                {{ $single['total_count'] }} 项 / {{ RegistrationSummaryService::formatYuan($single['total_amount_cent']) }} 元
            </div>
        </div>

        @if ($single['rows'] === [] || $single['projects'] === [])
            <div class="registration-detail-subpanel registration-detail-empty">暂无单羽项目报名。</div>
        @else
            <div class="registration-detail-scroll">
                <table class="registration-detail-table">
                    <thead>
                        <tr>
                            <th class="registration-detail-ring registration-detail-sticky">足环号</th>
                            @foreach ($single['projects'] as $project)
                                <th class="registration-detail-project">{{ $project['project_name'] }}</th>
                            @endforeach
                            <th>数量</th>
                            <th>金额</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($single['rows'] as $row)
                            <tr>
                                <td class="registration-detail-ring registration-detail-sticky">{{ $row['ring_number'] }}</td>
                                @foreach ($single['projects'] as $project)
                                    <td>
                                        @if (array_key_exists($project['key'], $row['selected_projects']))
                                            <span class="registration-detail-check">✓</span>
                                        @else
                                            <span class="registration-detail-empty">-</span>
                                        @endif
                                    </td>
                                @endforeach
                                <td>{{ $row['count'] }}</td>
                                <td>{{ RegistrationSummaryService::formatYuan($row['amount_cent']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section class="registration-detail-panel">
        <div class="registration-detail-heading">
            <div class="registration-detail-title">多羽组明细</div>
            <div class="registration-detail-meta">
                {{ collect($multi)->sum('group_count') }} 组 / {{ RegistrationSummaryService::formatYuan(collect($multi)->sum('amount_cent')) }} 元
            </div>
        </div>

        @if ($multi === [])
            <div class="registration-detail-subpanel registration-detail-empty">暂无多羽组报名。</div>
        @else
            @foreach ($multi as $project)
                <div class="registration-detail-subpanel">
                    <div class="registration-detail-project-title">
                        <span>{{ $project['project_name'] }}</span>
                        <span class="registration-detail-meta">
                            {{ $project['group_count'] }} 组 / {{ RegistrationSummaryService::formatYuan($project['amount_cent']) }} 元
                        </span>
                    </div>
                    <div class="registration-detail-scroll">
                        <table class="registration-detail-table">
                            <thead>
                                <tr>
                                    <th style="width: 96px;">组号</th>
                                    @for ($index = 1; $index <= $project['group_size']; $index++)
                                        <th>第{{ $index }}羽</th>
                                    @endfor
                                    <th style="width: 100px;">金额</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($project['groups'] as $group)
                                    <tr>
                                        <td>第{{ $group['group_index'] }}组</td>
                                        @for ($index = 0; $index < $project['group_size']; $index++)
                                            <td class="registration-detail-ring">{{ $group['rings'][$index] ?? '-' }}</td>
                                        @endfor
                                        <td>{{ RegistrationSummaryService::formatYuan($project['price_cent']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach
        @endif
    </section>

    <section class="registration-detail-panel">
        <div class="registration-detail-heading">
            <div class="registration-detail-title">递进阶段明细</div>
            <div class="registration-detail-meta">
                {{ collect($progressive)->sum('total_count') }} 组 / {{ RegistrationSummaryService::formatYuan(collect($progressive)->sum('total_amount_cent')) }} 元
            </div>
        </div>

        @if ($progressive === [])
            <div class="registration-detail-subpanel registration-detail-empty">暂无递进阶段报名。</div>
        @else
            @foreach ($progressive as $category)
                <div class="registration-detail-subpanel">
                    <div class="registration-detail-project-title">
                        <strong>{{ $category['category_name'] }}</strong>
                        <span class="registration-detail-meta">
                            {{ $category['total_count'] }} 组 / {{ RegistrationSummaryService::formatYuan($category['total_amount_cent']) }} 元
                        </span>
                    </div>
                    <div class="registration-detail-scroll">
                        <table class="registration-detail-table">
                            <thead>
                                <tr>
                                    <th>阶段项目</th>
                                    <th>组号</th>
                                    <th>足环号</th>
                                    <th>状态</th>
                                    <th>金额（元）</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($category['projects'] as $project)
                                    @foreach ($project['groups'] as $group)
                                        <tr>
                                            <td>{{ $project['project_name'] }}</td>
                                            <td>第 {{ $group['group_index'] }} 组</td>
                                            <td class="registration-detail-ring">{{ implode(' / ', $group['rings']) }}</td>
                                            <td>{{ $group['status'] === 'confirmed' ? '已确认' : '未确认' }}</td>
                                            <td>{{ RegistrationSummaryService::formatYuan($project['price_cent']) }}</td>
                                        </tr>
                                    @endforeach
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach
        @endif
    </section>
</div>
