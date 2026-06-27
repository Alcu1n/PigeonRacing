{{-- [IN]: Import page Livewire state / 导入页面 Livewire 状态 --}}
{{-- [OUT]: Compact styled member import upload, preview, and confirm UI / 紧凑样式统一的会员导入上传、预览与确认 UI --}}
{{-- [POS]: Backend admin member import Blade view / 后端后台会员导入 Blade 视图 --}}
{{-- Protocol: When updating me, sync this header + parent folder's .folder.md --}}
{{-- 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md --}}
<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section heading="上传 Excel">
            <div class="space-y-5">
                <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm leading-6 text-gray-700 dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-200">
                    <div class="font-medium text-gray-950 dark:text-white">Excel 导入格式</div>
                    <div class="mt-1">
                        表头固定为：<span class="font-semibold">序号、棚号、参赛名、手机号、密码</span>
                    </div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        支持 .xlsx / .xls，最大 10MB。密码可留空；密码非空时会员首次登录必须修改密码。
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <input id="member-import-upload" type="file" wire:model="upload" accept=".xlsx,.xls" style="display: none;" />
                    <x-filament::button tag="label" for="member-import-upload" icon="heroicon-o-arrow-up-tray" color="gray">
                        选择文件
                    </x-filament::button>
                    <span class="min-w-0 max-w-md truncate rounded-md bg-gray-100 px-3 py-2 text-sm text-gray-700 dark:bg-gray-800 dark:text-gray-200">
                        {{ $upload?->getClientOriginalName() ?? '尚未选择文件' }}
                    </span>
                    <span wire:loading wire:target="upload" class="text-sm text-primary-600">
                        正在上传...
                    </span>
                </div>
                @error('upload')
                    <p class="text-sm text-danger-600">{{ $message }}</p>
                @enderror
                <div class="flex flex-wrap gap-2">
                    <x-filament::button wire:click="previewUpload" wire:loading.attr="disabled">
                        预览导入
                    </x-filament::button>
                    <x-filament::button color="gray" outlined wire:click="resetImport" wire:loading.attr="disabled">
                        清空
                    </x-filament::button>
                </div>
            </div>
        </x-filament::section>

        @if ($lastResult)
            <x-filament::section heading="最近一次导入结果">
                <div class="flex flex-wrap items-center gap-4 text-sm">
                    <span>成功：<strong>{{ $lastResult['success_rows'] }}</strong> 行</span>
                    <span>失败：<strong>{{ $lastResult['failed_rows'] }}</strong> 行</span>
                    @if ($lastResult['error_report_path'])
                        <x-filament::button color="warning" wire:click="downloadErrorReport">
                            下载错误报告
                        </x-filament::button>
                    @endif
                </div>
            </x-filament::section>
        @endif

        @if ($preview)
            <x-filament::section heading="导入预览">
                <div class="grid gap-3 md:grid-cols-6">
                    <div>总行数：<strong>{{ $preview['total_rows'] }}</strong></div>
                    <div>可导入：<strong>{{ $preview['valid_rows'] }}</strong></div>
                    <div>失败：<strong>{{ $preview['failed_rows'] }}</strong></div>
                    <div>重复/冲突：<strong>{{ $preview['duplicate_rows'] }}</strong></div>
                    <div>新建会员：<strong>{{ $preview['create_member_rows'] }}</strong></div>
                    <div>重置密码：<strong>{{ $preview['reset_password_rows'] }}</strong></div>
                </div>

                <div class="mt-4 flex gap-3">
                    <x-filament::button color="success" wire:click="confirmImport" wire:loading.attr="disabled" :disabled="$preview['valid_rows'] === 0">
                        确认导入
                    </x-filament::button>
                    <x-filament::button color="gray" wire:click="resetImport" wire:loading.attr="disabled">
                        重新选择
                    </x-filament::button>
                </div>

                <div class="mt-6 overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                    <table class="w-full min-w-[860px] text-left text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th class="px-3 py-2">行号</th>
                                <th class="px-3 py-2">序号</th>
                                <th class="px-3 py-2">棚号</th>
                                <th class="px-3 py-2">参赛名</th>
                                <th class="px-3 py-2">手机号</th>
                                <th class="px-3 py-2">密码</th>
                                <th class="px-3 py-2">动作</th>
                                <th class="px-3 py-2">错误</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach (array_slice($preview['rows'], 0, 50) as $row)
                                <tr class="border-t border-gray-200 dark:border-gray-700">
                                    <td class="px-3 py-2">{{ $row['line'] }}</td>
                                    <td class="px-3 py-2">{{ $row['data']['sequence'] }}</td>
                                    <td class="px-3 py-2">{{ $row['data']['loft_number'] }}</td>
                                    <td class="px-3 py-2">{{ $row['data']['participant_name'] }}</td>
                                    <td class="px-3 py-2">{{ $row['data']['phone'] }}</td>
                                    <td class="px-3 py-2">{{ $row['data']['password'] === '' ? '留空' : '已填写' }}</td>
                                    <td class="px-3 py-2">
                                        @if ($row['errors'])
                                            跳过
                                        @elseif ($row['will_create_member'])
                                            新建会员
                                        @elseif ($row['will_reset_password'])
                                            更新并重置密码
                                        @else
                                            更新会员
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
