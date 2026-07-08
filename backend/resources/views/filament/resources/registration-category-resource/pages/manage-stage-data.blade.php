{{-- [IN]: Progressive category stage data Livewire state / 递进类别阶段数据 Livewire 状态 --}}
{{-- [OUT]: Member-scoped stage group editor for progressive categories / 按会员编辑递进类别阶段组的后台 UI --}}
{{-- [POS]: Backend admin progressive stage data management Blade view / 后端后台递进阶段数据管理视图 --}}
{{-- Protocol: When updating me, sync this header + parent folder's .folder.md --}}
{{-- 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md --}}
<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section heading="选择会员">
            <div class="grid gap-3 md:grid-cols-[1fr_auto]">
                <select class="rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900" wire:model.live="memberId">
                    <option value="">请选择会员棚号 / 参赛名</option>
                    @foreach ($memberOptions as $id => $label)
                        <option value="{{ $id }}">{{ $label }}</option>
                    @endforeach
                </select>
                <x-filament::button color="gray" tag="a" href="{{ \App\Filament\Resources\RegistrationCategoryResource::getUrl('index') }}">
                    返回列表
                </x-filament::button>
            </div>
            <div class="mt-3 text-xs leading-5 text-gray-500 dark:text-gray-400">
                本页用于直接维护某个会员在“{{ $record->name }}”下的全部阶段数据。第一阶段仍作为资格基准，不计入报名金额。
            </div>
        </x-filament::section>

        @if ($memberId)
            <x-filament::section heading="阶段数据">
                @if ($pigeons === [])
                    <div class="rounded-lg bg-warning-50 px-4 py-3 text-sm text-warning-700 dark:bg-warning-500/10 dark:text-warning-300">
                        当前会员名下没有正常状态足环，请先到足环管理中维护足环。
                    </div>
                @endif

                <div class="space-y-5">
                    @foreach (($categoryData['stages'] ?? []) as $stage)
                        <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                                <div>
                                    <div class="font-medium text-gray-950 dark:text-white">第 {{ $stage['stage_order'] }} 阶段：{{ $stage['name'] }}</div>
                                    <div class="text-xs text-gray-500">每组 {{ $stage['group_size'] }} 羽，{{ rtrim(rtrim(number_format($stage['price_cent'] / 100, 2), '0'), '.') }} 元/组</div>
                                </div>
                                <x-filament::button size="sm" color="gray" wire:click="addStageGroup({{ $stage['id'] }})">
                                    添加一组
                                </x-filament::button>
                            </div>

                            <div class="space-y-3">
                                @forelse (($stageGroups[$stage['id']] ?? []) as $groupIndex => $group)
                                    <div class="grid gap-2 rounded-md bg-gray-50 p-3 dark:bg-gray-800 md:grid-cols-[1fr_auto]">
                                        <select
                                            multiple
                                            size="{{ min(max($stage['group_size'] + 2, 4), 8) }}"
                                            class="w-full rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900"
                                            wire:model="stageGroups.{{ $stage['id'] }}.{{ $groupIndex }}.pigeon_ids"
                                        >
                                            @foreach ($pigeons as $pigeon)
                                                <option value="{{ $pigeon['id'] }}">{{ $pigeon['ring_number'] }}</option>
                                            @endforeach
                                        </select>
                                        <x-filament::button color="danger" outlined size="sm" wire:click="removeStageGroup({{ $stage['id'] }}, {{ $groupIndex }})">
                                            删除
                                        </x-filament::button>
                                    </div>
                                @empty
                                    <div class="rounded-md bg-gray-50 px-3 py-2 text-sm text-gray-500 dark:bg-gray-800">暂无阶段数据。</div>
                                @endforelse
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>

            <div class="sticky bottom-4 z-20 flex justify-end rounded-xl border border-gray-200 bg-white/95 p-3 shadow-lg backdrop-blur dark:border-gray-700 dark:bg-gray-900/95">
                <x-filament::button color="success" wire:click="save" wire:loading.attr="disabled">
                    保存阶段数据
                </x-filament::button>
            </div>
        @else
            <x-filament::section heading="阶段数据">
                <div class="rounded-lg bg-gray-50 px-4 py-8 text-center text-sm text-gray-500 dark:bg-gray-800">
                    请先选择会员。
                </div>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
