<?php

// [IN]: Progressive category, selected member, and stage group form state / 递进类别、所选会员与阶段组表单状态
// [OUT]: Admin page for editing progressive stage baseline and later-stage data / 用于编辑递进阶段基准与后续阶段数据的后台页面
// [POS]: Backend admin progressive stage data management route / 后端后台递进阶段数据管理路由
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Filament\Resources\RegistrationCategoryResource\Pages;

use App\Filament\Resources\RegistrationCategoryResource;
use App\Models\Member;
use App\Models\RegistrationCategory;
use App\Services\AdminRegistrationEditService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;

class ManageStageData extends Page
{
    use InteractsWithRecord;

    protected static string $resource = RegistrationCategoryResource::class;

    protected string $view = 'filament.resources.registration-category-resource.pages.manage-stage-data';

    public ?int $memberId = null;

    public array $memberOptions = [];

    public array $pigeons = [];

    public array $categoryData = [];

    public array $stageGroups = [];

    public static function canAccess(array $parameters = []): bool
    {
        return RegistrationCategoryResource::hasModulePermission('update');
    }

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $this->category()->load(['race', 'stageProjects']);
        $this->memberOptions = Member::query()
            ->orderBy('loft_number')
            ->limit(1000)
            ->get(['id', 'loft_number', 'participant_name'])
            ->mapWithKeys(fn (Member $member): array => [
                $member->id => "{$member->loft_number} {$member->participant_name}",
            ])
            ->all();
        $this->memberId = request()->integer('member_id') ?: null;
        $this->loadMemberData();
    }

    public function getTitle(): string
    {
        return "{$this->category()->name} 阶段数据管理";
    }

    public function updatedMemberId(): void
    {
        $this->loadMemberData();
    }

    public function addStageGroup(int $projectId): void
    {
        $this->stageGroups[$projectId] ??= [];
        $this->stageGroups[$projectId][] = ['pigeon_ids' => []];
    }

    public function removeStageGroup(int $projectId, int $index): void
    {
        unset($this->stageGroups[$projectId][$index]);
        $this->stageGroups[$projectId] = array_values($this->stageGroups[$projectId] ?? []);
    }

    public function save(AdminRegistrationEditService $service): void
    {
        $member = $this->member();
        if (! $member) {
            Notification::make()->title('请先选择会员')->warning()->send();

            return;
        }

        $result = $service->updateCategoryMember($this->category(), $member, [
            'stage_groups' => $this->stageGroups,
        ], auth()->id(), request()->ip());

        $removedCount = count($result['removed_groups'] ?? []);
        Notification::make()
            ->title($removedCount > 0 ? "已保存，并移除 {$removedCount} 个无效后续阶段组" : '阶段数据已保存')
            ->success()
            ->send();

        $this->loadMemberData();
    }

    private function loadMemberData(): void
    {
        $member = $this->member();
        $data = app(AdminRegistrationEditService::class)->categoryMemberFormData($this->category(), $member);

        $this->pigeons = $data['pigeons']->map(fn ($pigeon): array => [
            'id' => (int) $pigeon->id,
            'ring_number' => $pigeon->ring_number,
        ])->all();

        $this->categoryData = [
            'id' => (int) $this->category()->id,
            'name' => $this->category()->name,
            'stages' => $this->category()->stageProjects->map(fn ($project): array => [
                'id' => (int) $project->id,
                'name' => $project->name,
                'group_size' => (int) $project->group_size,
                'price_cent' => (int) $project->price_cent,
                'stage_order' => (int) $project->stage_order,
            ])->values()->all(),
        ];

        $this->stageGroups = $member ? ($data['progressive_groups'][$this->category()->id] ?? []) : [];
        foreach ($this->categoryData['stages'] as $stage) {
            $this->stageGroups[$stage['id']] ??= [];
        }
    }

    private function category(): RegistrationCategory
    {
        $record = $this->getRecord();
        abort_unless($record instanceof RegistrationCategory, 404);

        return $record;
    }

    private function member(): ?Member
    {
        return $this->memberId ? Member::query()->find($this->memberId) : null;
    }
}
