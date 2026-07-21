<?php

// [IN]: Registration record and admin-edited group form state / 报名记录与后台编辑的分组表单状态
// [OUT]: Registration data edit page backed by AdminRegistrationEditService / 由后台编辑服务支撑的报名数据编辑页面
// [POS]: Backend admin registration data edit route / 后端后台报名数据编辑路由
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Filament\Resources\RegistrationResource\Pages;

use App\Filament\Resources\RegistrationResource;
use App\Models\Registration;
use App\Services\AdminRegistrationEditService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;

class EditRegistrationData extends Page
{
    use InteractsWithRecord;

    protected static string $resource = RegistrationResource::class;

    protected string $view = 'filament.resources.registration-resource.pages.edit-registration-data';

    public array $pigeons = [];

    public array $singleProjects = [];

    public array $multiProjects = [];

    public array $progressiveCategories = [];

    public array $singleSelected = [];

    public array $multiGroups = [];

    public array $progressiveGroups = [];

    public static function canAccess(array $parameters = []): bool
    {
        return RegistrationResource::hasModulePermission('update');
    }

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $this->fillFromRecord();
    }

    public function getTitle(): string
    {
        return '修改报名数据';
    }

    public function addMultiGroup(int $projectId): void
    {
        $this->multiGroups[$projectId] ??= [];
        $this->multiGroups[$projectId][] = ['pigeon_ids' => []];
    }

    public function removeMultiGroup(int $projectId, int $index): void
    {
        unset($this->multiGroups[$projectId][$index]);
        $this->multiGroups[$projectId] = array_values($this->multiGroups[$projectId] ?? []);
    }

    public function addProgressiveGroup(int $categoryId, int $projectId): void
    {
        $this->progressiveGroups[$categoryId][$projectId] ??= [];
        $this->progressiveGroups[$categoryId][$projectId][] = ['pigeon_ids' => []];
    }

    public function removeProgressiveGroup(int $categoryId, int $projectId, int $index): void
    {
        unset($this->progressiveGroups[$categoryId][$projectId][$index]);
        $this->progressiveGroups[$categoryId][$projectId] = array_values($this->progressiveGroups[$categoryId][$projectId] ?? []);
    }

    public function save(AdminRegistrationEditService $service): void
    {
        $result = $service->updateRegistration($this->registration(), [
            'standard_groups' => $this->standardGroupsPayload(),
            'progressive_groups' => $this->progressiveGroups,
        ], auth()->id(), request()->ip());

        $removedCount = count($result['removed_groups'] ?? []);
        Notification::make()
            ->title($removedCount > 0 ? "已保存，并移除 {$removedCount} 个无效后续阶段组" : '报名数据已保存')
            ->success()
            ->send();

        $this->fillFromRecord();
    }

    private function fillFromRecord(): void
    {
        $data = app(AdminRegistrationEditService::class)->registrationFormData($this->registration());

        $this->pigeons = $data['pigeons']->map(fn ($pigeon): array => [
            'id' => (int) $pigeon->id,
            'ring_number' => $pigeon->ring_number,
        ])->all();

        $projects = $data['standard_projects'];
        $this->singleProjects = $projects->where('group_size', 1)->map(fn ($project): array => $this->projectArray($project))->values()->all();
        $this->multiProjects = $projects->where('group_size', '>', 1)->map(fn ($project): array => $this->projectArray($project))->values()->all();
        $this->progressiveCategories = $data['progressive_categories']->map(fn ($category): array => [
            'id' => (int) $category->id,
            'name' => $category->name,
            'stages' => $category->stageProjects->map(fn ($project): array => $this->projectArray($project))->values()->all(),
        ])->values()->all();

        $this->singleSelected = [];
        $this->multiGroups = [];
        foreach ($data['standard_groups'] as $projectId => $groups) {
            $project = $projects->firstWhere('id', (int) $projectId);
            if ($project && (int) $project->group_size === 1) {
                foreach ($groups as $group) {
                    $pigeonId = (int) (($group['pigeon_ids'][0] ?? 0));
                    if ($pigeonId > 0) {
                        $this->singleSelected[(int) $projectId][$pigeonId] = true;
                    }
                }
            } else {
                $this->multiGroups[(int) $projectId] = $groups;
            }
        }

        foreach ($this->multiProjects as $project) {
            $this->multiGroups[$project['id']] ??= [];
        }

        $this->progressiveGroups = $data['progressive_groups'];
        foreach ($this->progressiveCategories as $category) {
            foreach ($category['stages'] as $stage) {
                $this->progressiveGroups[$category['id']][$stage['id']] ??= [];
            }
        }
    }

    private function standardGroupsPayload(): array
    {
        $payload = [];

        foreach ($this->singleSelected as $projectId => $pigeonStates) {
            foreach ($pigeonStates as $pigeonId => $selected) {
                if ($selected) {
                    $payload[(int) $projectId][] = ['pigeon_ids' => [(int) $pigeonId]];
                }
            }
        }

        foreach ($this->multiGroups as $projectId => $groups) {
            $payload[(int) $projectId] = array_values($groups);
        }

        return $payload;
    }

    private function projectArray($project): array
    {
        return [
            'id' => (int) $project->id,
            'name' => $project->name,
            'group_size' => (int) $project->group_size,
            'price_cent' => (int) $project->price_cent,
            'stage_order' => $project->stage_order ? (int) $project->stage_order : null,
        ];
    }

    private function registration(): Registration
    {
        $record = $this->getRecord();
        abort_unless($record instanceof Registration, 404);

        return $record;
    }
}
