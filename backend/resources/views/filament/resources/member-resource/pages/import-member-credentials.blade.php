{{-- [IN]: Member credential import page Livewire state / 会员登录凭据导入页 Livewire 状态 --}}
{{-- [OUT]: Password-safe credential upload, preview, confirm, and report UI / 不泄露密码的凭据上传、预览、确认与报告 UI --}}
{{-- [POS]: Backend admin member credential import Blade view / 后端后台会员登录凭据导入 Blade 视图 --}}
{{-- Protocol: When updating me, sync this header + parent folder's .folder.md --}}
{{-- 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md --}}
<x-filament-panels::page>
    @once
        <style>
            .excel-import-page { display: flex; flex-direction: column; gap: 1.5rem; }
            .excel-import-stack { display: flex; flex-direction: column; gap: 1.25rem; }
            .excel-import-note { border: 1px solid rgb(209 213 219); border-radius: .75rem; background: rgb(249 250 251); padding: .875rem 1rem; color: rgb(55 65 81); font-size: .875rem; line-height: 1.625; }
            .dark .excel-import-note { border-color: rgb(55 65 81); background: rgb(17 24 39 / .4); color: rgb(229 231 235); }
            .excel-import-note-title { margin-bottom: .25rem; font-weight: 600; color: rgb(17 24 39); }
            .dark .excel-import-note-title { color: #fff; }
            .excel-import-note-help { margin-top: .25rem; color: rgb(107 114 128); font-size: .8125rem; }
            .dark .excel-import-note-help { color: rgb(156 163 175); }
            .excel-import-upload-row, .excel-import-actions, .excel-import-result { display: flex; flex-wrap: wrap; align-items: center; gap: .75rem; }
            .excel-import-file-name { min-width: 12rem; max-width: 28rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; border-radius: .5rem; background: rgb(243 244 246); padding: .5rem .75rem; color: rgb(55 65 81); font-size: .875rem; }
            .dark .excel-import-file-name { background: rgb(31 41 55); color: rgb(229 231 235); }
            .excel-import-loading { color: rgb(37 99 235); font-size: .875rem; }
            .excel-import-error { color: rgb(220 38 38); font-size: .875rem; }
            .excel-import-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(9rem, 1fr)); gap: .75rem; }
            .excel-import-stat { border-radius: .625rem; background: rgb(249 250 251); padding: .625rem .75rem; color: rgb(55 65 81); font-size: .875rem; }
            .dark .excel-import-stat { background: rgb(31 41 55 / .72); color: rgb(229 231 235); }
            .excel-import-preview-actions { margin-top: 1rem; display: flex; flex-wrap: wrap; align-items: center; gap: .75rem; }
            .excel-import-table-wrap { margin-top: 1.5rem; overflow-x: auto; border: 1px solid rgb(209 213 219); border-radius: .75rem; }
            .dark .excel-import-table-wrap { border-color: rgb(55 65 81); }
        </style>
    @endonce

    <div class="excel-import-page">
        <x-filament::section heading="上传 Excel">
            <div class="excel-import-stack">
                <div class="excel-import-note">
                    <div class="excel-import-note-title">Excel 导入格式</div>
                    <div>
                        表头必须严格为：<span class="font-semibold">会员棚号、手机号、密码</span>
                    </div>
                    <div class="excel-import-note-help">
                        仅给已有会员补充登录凭据；已有手机号的会员将整行跳过。手机号须为 11 位大陆手机号，密码须为 6–128 个字符。
                    </div>
                </div>

                <div class="excel-import-upload-row">
                    <input id="member-credential-import-upload" type="file" wire:model="upload" accept=".xlsx,.xls" style="display: none;" />
                    <x-filament::button tag="label" for="member-credential-import-upload" icon="heroicon-o-arrow-up-tray" color="gray">
                        选择文件
                    </x-filament::button>
                    <span class="excel-import-file-name">
                        {{ $upload?->getClientOriginalName() ?? '尚未选择文件' }}
                    </span>
                    <span wire:loading wire:target="upload" class="excel-import-loading">正在上传...</span>
                </div>
                @error('upload')
                    <p class="excel-import-error">{{ $message }}</p>
                @enderror
                <div class="excel-import-actions">
                    <x-filament::button wire:click="previewUpload" wire:loading.attr="disabled">预览导入</x-filament::button>
                    <x-filament::button color="gray" outlined wire:click="resetImport" wire:loading.attr="disabled">清空</x-filament::button>
                </div>
            </div>
        </x-filament::section>

        @if ($lastResult)
            <x-filament::section heading="最近一次导入结果">
                <div class="excel-import-result">
                    <span>成功：<strong>{{ $lastResult['success_rows'] }}</strong> 行</span>
                    <span>跳过/错误：<strong>{{ $lastResult['failed_rows'] }}</strong> 行</span>
                    @if ($lastResult['has_error_report'])
                        <x-filament::button color="warning" wire:click="downloadErrorReport">下载错误报告</x-filament::button>
                    @endif
                </div>
            </x-filament::section>
        @endif

        @if ($preview)
            <x-filament::section heading="导入预览">
                <div class="excel-import-stats">
                    <div class="excel-import-stat">总行数：<strong>{{ $preview['total_rows'] }}</strong></div>
                    <div class="excel-import-stat">可导入：<strong>{{ $preview['valid_rows'] }}</strong></div>
                    <div class="excel-import-stat">已跳过：<strong>{{ $preview['skipped_rows'] }}</strong></div>
                    <div class="excel-import-stat">格式错误：<strong>{{ $preview['invalid_rows'] }}</strong></div>
                    <div class="excel-import-stat">重复/冲突：<strong>{{ $preview['duplicate_rows'] }}</strong></div>
                </div>

                <div class="excel-import-preview-actions">
                    <x-filament::button color="success" wire:click="confirmImport" wire:loading.attr="disabled">
                        确认导入
                    </x-filament::button>
                    <x-filament::button color="gray" wire:click="resetImport" wire:loading.attr="disabled">重新选择</x-filament::button>
                </div>

                <div class="excel-import-table-wrap">
                    <table class="w-full min-w-[680px] text-left text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th class="px-3 py-2">行号</th>
                                <th class="px-3 py-2">会员棚号</th>
                                <th class="px-3 py-2">手机号</th>
                                <th class="px-3 py-2">密码</th>
                                <th class="px-3 py-2">处理</th>
                                <th class="px-3 py-2">原因</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach (array_slice($preview['rows'], 0, 50) as $row)
                                <tr class="border-t border-gray-200 dark:border-gray-700">
                                    <td class="px-3 py-2">{{ $row['line'] }}</td>
                                    <td class="px-3 py-2">{{ $row['data']['loft_number'] }}</td>
                                    <td class="px-3 py-2">{{ $row['data']['phone'] }}</td>
                                    <td class="px-3 py-2">{{ $row['password_filled'] ? '已填写' : '未填写' }}</td>
                                    <td class="px-3 py-2">
                                        @if ($row['status'] === 'ready')
                                            导入凭据
                                        @elseif ($row['status'] === 'skipped')
                                            跳过
                                        @else
                                            格式错误
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-danger-600">{{ implode('；', $row['errors']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
