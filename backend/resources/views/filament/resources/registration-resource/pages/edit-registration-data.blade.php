{{-- [IN]: Registration data edit Livewire state / 报名数据编辑 Livewire 状态 --}}
{{-- [OUT]: Dense admin UI for editing standard and progressive registration groups / 用于编辑普通与递进报名组的高密度后台 UI --}}
{{-- [POS]: Backend admin registration data edit Blade view / 后端后台报名数据编辑视图 --}}
{{-- Protocol: When updating me, sync this header + parent folder's .folder.md --}}
{{-- 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md --}}
<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section heading="编辑说明">
            <div class="grid gap-3 text-sm text-gray-700 dark:text-gray-200 md:grid-cols-3">
                <div>赛事：<strong>{{ $record->race?->name }}</strong></div>
                <div>棚号：<strong>{{ $record->member?->loft_number }}</strong></div>
                <div>参赛名：<strong>{{ $record->member?->participant_name }}</strong></div>
            </div>
            <div class="mt-3 text-xs leading-5 text-gray-500 dark:text-gray-400">
                保存后数据默认已确认；递进阶段会按阶段顺序重新校验，无法匹配上一阶段确认组的后续数据会被自动移除。
            </div>
        </x-filament::section>

        @if ($singleProjects !== [])
            <x-filament::section heading="单羽组">
                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                    <table class="w-full min-w-[760px] text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th class="sticky left-0 z-10 w-52 bg-gray-50 px-3 py-2 text-left dark:bg-gray-800">足环号码</th>
                                @foreach ($singleProjects as $project)
                                    <th class="px-3 py-2 text-center">{{ $project['name'] }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($pigeons as $pigeon)
                                <tr class="border-t border-gray-200 dark:border-gray-700">
                                    <td class="sticky left-0 z-10 bg-white px-3 py-2 font-mono dark:bg-gray-900">{{ $pigeon['ring_number'] }}</td>
                                    @foreach ($singleProjects as $project)
                                        <td class="px-3 py-2 text-center">
                                            <input
                                                type="checkbox"
                                                class="rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500"
                                                wire:model="singleSelected.{{ $project['id'] }}.{{ $pigeon['id'] }}"
                                            />
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @endif

        @if ($multiProjects !== [])
            <x-filament::section heading="多羽组">
                <div class="space-y-5">
                    @foreach ($multiProjects as $project)
                        <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                                <div>
                                    <div class="font-semibold text-gray-950 dark:text-white">{{ $project['name'] }}</div>
                                    <div class="text-xs text-gray-500">每组 {{ $project['group_size'] }} 羽，{{ rtrim(rtrim(number_format($project['price_cent'] / 100, 2), '0'), '.') }} 元/组</div>
                                </div>
                                <x-filament::button size="sm" color="gray" wire:click="addMultiGroup({{ $project['id'] }})">
                                    添加一组
                                </x-filament::button>
                            </div>
                            <div class="space-y-3">
                                @forelse (($multiGroups[$project['id']] ?? []) as $groupIndex => $group)
                                    <div class="grid gap-2 rounded-md bg-gray-50 p-3 dark:bg-gray-800 md:grid-cols-[1fr_auto]">
                                        <select
                                            multiple
                                            size="{{ min(max($project['group_size'] + 2, 4), 8) }}"
                                            class="w-full rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900"
                                            wire:model="multiGroups.{{ $project['id'] }}.{{ $groupIndex }}.pigeon_ids"
                                        >
                                            @foreach ($pigeons as $pigeon)
                                                <option value="{{ $pigeon['id'] }}">{{ $pigeon['ring_number'] }}</option>
                                            @endforeach
                                        </select>
                                        <x-filament::button color="danger" outlined size="sm" wire:click="removeMultiGroup({{ $project['id'] }}, {{ $groupIndex }})">
                                            删除
                                        </x-filament::button>
                                    </div>
                                @empty
                                    <div class="rounded-md bg-gray-50 px-3 py-2 text-sm text-gray-500 dark:bg-gray-800">暂无报名组。</div>
                                @endforelse
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>
        @endif

        @if ($progressiveCategories !== [])
            <x-filament::section heading="递进阶段项目">
                <div class="space-y-6">
                    @foreach ($progressiveCategories as $category)
                        <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                            <div class="mb-4 text-base font-semibold text-gray-950 dark:text-white">{{ $category['name'] }}</div>
                            <div class="space-y-5">
                                @foreach ($category['stages'] as $stage)
                                    <div class="rounded-lg bg-gray-50 p-4 dark:bg-gray-800/70">
                                        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                                            <div>
                                                <div class="font-medium text-gray-950 dark:text-white">第 {{ $stage['stage_order'] }} 阶段：{{ $stage['name'] }}</div>
                                                <div class="text-xs text-gray-500">每组 {{ $stage['group_size'] }} 羽，{{ rtrim(rtrim(number_format($stage['price_cent'] / 100, 2), '0'), '.') }} 元/组</div>
                                            </div>
                                            <x-filament::button size="sm" color="gray" wire:click="addProgressiveGroup({{ $category['id'] }}, {{ $stage['id'] }})">
                                                添加一组
                                            </x-filament::button>
                                        </div>
                                        <div class="space-y-3">
                                            @forelse (($progressiveGroups[$category['id']][$stage['id']] ?? []) as $groupIndex => $group)
                                                <div class="grid gap-2 rounded-md bg-white p-3 dark:bg-gray-900 md:grid-cols-[1fr_auto]">
                                                    <select
                                                        multiple
                                                        size="{{ min(max($stage['group_size'] + 2, 4), 8) }}"
                                                        class="w-full rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900"
                                                        wire:model="progressiveGroups.{{ $category['id'] }}.{{ $stage['id'] }}.{{ $groupIndex }}.pigeon_ids"
                                                    >
                                                        @foreach ($pigeons as $pigeon)
                                                            <option value="{{ $pigeon['id'] }}">{{ $pigeon['ring_number'] }}</option>
                                                        @endforeach
                                                    </select>
                                                    <x-filament::button color="danger" outlined size="sm" wire:click="removeProgressiveGroup({{ $category['id'] }}, {{ $stage['id'] }}, {{ $groupIndex }})">
                                                        删除
                                                    </x-filament::button>
                                                </div>
                                            @empty
                                                <div class="rounded-md bg-white px-3 py-2 text-sm text-gray-500 dark:bg-gray-900">暂无阶段数据。</div>
                                            @endforelse
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>
        @endif

        <div class="sticky bottom-4 z-20 flex flex-wrap justify-end gap-3 rounded-xl border border-gray-200 bg-white/95 p-3 shadow-lg backdrop-blur dark:border-gray-700 dark:bg-gray-900/95">
            <x-filament::button tag="a" color="gray" href="{{ \App\Filament\Resources\RegistrationResource::getUrl('view', ['record' => $record]) }}">
                返回详情
            </x-filament::button>
            <x-filament::button color="success" wire:click="save" wire:loading.attr="disabled">
                保存修改
            </x-filament::button>
        </div>
    </div>
</x-filament-panels::page>
