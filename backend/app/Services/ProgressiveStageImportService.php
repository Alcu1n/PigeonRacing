<?php
// [IN]: Progressive category first-stage spreadsheets, members, pigeons, and preview tokens / 递进类别第一阶段表格、会员、足环与预览令牌
// [OUT]: Server-side preview, confirmed first-stage entries, and error report / 服务端预览、已确认第一阶段结果与错误报告
// [POS]: Backend progressive first-stage import service / 后端递进第一阶段导入服务
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Services;

use App\Enums\RegistrationStatus;
use App\Exports\ProgressiveStageImportErrorExport;
use App\Imports\SpreadsheetArrayImport;
use App\Models\ImportBatch;
use App\Models\Member;
use App\Models\Pigeon;
use App\Models\ProgressiveStageEntry;
use App\Models\RaceProject;
use App\Models\RegistrationCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class ProgressiveStageImportService
{
    public const MAX_UPLOAD_KB = 51200;
    public const PREVIEW_SAMPLE_LIMIT = 50;
    private const PREVIEW_DIR = 'imports/previews';
    private const INSERT_CHUNK_SIZE = 1000;

    public function firstStage(RegistrationCategory $category): RaceProject
    {
        $stage = $category->stageProjects()
            ->where('stage_order', 1)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();

        if (! $stage instanceof RaceProject) {
            throw ValidationException::withMessages(['upload' => '请先为该类别配置阶段顺序为 1 的第一阶段项目。']);
        }

        return $stage;
    }

    public function rowsFromSpreadsheet(string $path, RegistrationCategory $category): array
    {
        $stage = $this->firstStage($category);
        $sheets = Excel::toArray(new SpreadsheetArrayImport(), $path);
        $rows = $sheets[0] ?? [];

        if ($rows === []) {
            throw ValidationException::withMessages(['upload' => 'Excel 文件为空。']);
        }

        $header = array_map(fn ($value): string => trim((string) $value), array_values(array_shift($rows)));
        $expected = ['序号', '会员棚号', '会员参赛名', '足环号码', $stage->name];

        if (array_slice($header, 0, 5) !== $expected) {
            throw ValidationException::withMessages(['upload' => 'Excel 表头必须为：'.implode('，', $expected).'。']);
        }

        $normalized = [];
        foreach ($rows as $index => $row) {
            $values = array_pad(array_values($row), 5, null);
            $item = [
                'line' => $index + 2,
                'sequence' => trim((string) $values[0]),
                'loft_number' => trim((string) $values[1]),
                'participant_name' => trim((string) $values[2]),
                'ring_number' => trim((string) $values[3]),
                'stage_marker' => trim((string) $values[4]),
            ];

            if (implode('', $item) === (string) $item['line']) {
                continue;
            }

            $normalized[] = $item;
        }

        return $normalized;
    }

    public function preview(array $rows, ?RegistrationCategory $category = null): array
    {
        $stage = $category instanceof RegistrationCategory ? $this->firstStage($category) : null;
        $groupSize = max(1, (int) ($stage?->group_size ?? 1));
        $ringNumbers = collect($rows)
            ->flatMap(fn (array $row): array => $this->splitRingNumbers((string) ($row['ring_number'] ?? '')))
            ->filter()
            ->unique()
            ->values();
        $members = Member::query()
            ->whereIn('loft_number', collect($rows)->pluck('loft_number')->filter()->unique())
            ->get()
            ->keyBy('loft_number');
        $pigeons = Pigeon::query()
            ->whereIn('ring_number', $ringNumbers)
            ->get()
            ->keyBy('ring_number');
        $seenSelectedGroups = [];
        $selectedRingUsage = [];
        $rowsPreview = [];

        foreach ($rows as $row) {
            $rowRingNumbers = $this->splitRingNumbers($row['ring_number']);
            $errors = $this->validateRow($row, $rowRingNumbers, $groupSize);
            $selected = $this->isSelectedMarker($row['stage_marker']);
            $member = $members->get($row['loft_number']);

            if ($row['stage_marker'] !== '' && ! $selected && ! $this->isUnselectedMarker($row['stage_marker'])) {
                $errors[] = '阶段标记只能为 ✓、√、1、是、yes、空值、×、x、0、否、no';
            }

            if ($selected && $rowRingNumbers !== []) {
                $groupSignature = $this->groupSignatureFromRingNumbers($rowRingNumbers);
                if (isset($seenSelectedGroups[$row['loft_number']][$groupSignature])) {
                    $errors[] = '本次文件内相同足环组重复';
                }

                $rowUsage = array_count_values($rowRingNumbers);
                foreach ($rowUsage as $ringNumber => $usage) {
                    if ($usage > 1) {
                        $errors[] = '同一组内足环号码不能重复';
                        break;
                    }

                    if ($stage?->max_usage_per_pigeon !== null && (($selectedRingUsage[$row['loft_number']][$ringNumber] ?? 0) + 1) > (int) $stage->max_usage_per_pigeon) {
                        $errors[] = "足环 {$ringNumber} 超过每足环最大使用次数";
                    }
                }
            }

            if ($selected) {
                foreach ($rowRingNumbers as $ringNumber) {
                    $pigeon = $pigeons->get($ringNumber);
                    if ($pigeon instanceof Pigeon && $member instanceof Member && $pigeon->member_id !== $member->id) {
                        $errors[] = "足环 {$ringNumber} 已属于其他会员棚号";
                    }

                    if ($pigeon instanceof Pigeon && ! ($member instanceof Member) && $pigeon->loft_number !== $row['loft_number']) {
                        $errors[] = "足环 {$ringNumber} 已属于其他会员棚号";
                    }
                }
            }

            if ($selected && $errors === [] && $rowRingNumbers !== []) {
                $seenSelectedGroups[$row['loft_number']][$this->groupSignatureFromRingNumbers($rowRingNumbers)] = true;
                foreach ($rowRingNumbers as $ringNumber) {
                    $selectedRingUsage[$row['loft_number']][$ringNumber] = ($selectedRingUsage[$row['loft_number']][$ringNumber] ?? 0) + 1;
                }
            }

            $rowsPreview[] = [
                'line' => $row['line'],
                'data' => [
                    'sequence' => $row['sequence'],
                    'loft_number' => $row['loft_number'],
                    'participant_name' => $row['participant_name'],
                    'ring_number' => $row['ring_number'],
                    'ring_numbers' => $rowRingNumbers,
                    'stage_marker' => $row['stage_marker'],
                ],
                'is_selected' => $selected,
                'member_id' => $member?->id,
                'system_participant_name' => $member?->participant_name,
                'will_create_member' => ! $member && $row['loft_number'] !== '',
                'will_create_pigeon' => $selected ? collect($rowRingNumbers)->filter(fn (string $ringNumber): bool => ! $pigeons->has($ringNumber))->count() : 0,
                'errors' => array_values(array_unique($errors)),
            ];
        }

        $failedRows = collect($rowsPreview)->reject(fn (array $row): bool => $row['errors'] === []);

        return [
            'source_rows' => $rows,
            'total_rows' => count($rows),
            'selected_rows' => collect($rowsPreview)->where('is_selected', true)->count(),
            'valid_rows' => collect($rowsPreview)->where('is_selected', true)->where('errors', [])->count(),
            'failed_rows' => $failedRows->count(),
            'duplicate_rows' => $failedRows->filter(fn (array $row): bool => collect($row['errors'])->contains(fn (string $error): bool => str_contains($error, '重复')))->count(),
            'create_member_rows' => collect($rowsPreview)->where('is_selected', true)->where('errors', [])->where('will_create_member', true)->pluck('data.loft_number')->unique()->count(),
            'create_pigeon_rows' => collect($rowsPreview)->where('is_selected', true)->where('errors', [])->sum('will_create_pigeon'),
            'rows' => $rowsPreview,
        ];
    }

    public function browserPreview(array $preview): array
    {
        return [
            'token' => $preview['token'] ?? null,
            'total_rows' => $preview['total_rows'],
            'selected_rows' => $preview['selected_rows'],
            'valid_rows' => $preview['valid_rows'],
            'failed_rows' => $preview['failed_rows'],
            'duplicate_rows' => $preview['duplicate_rows'],
            'create_member_rows' => $preview['create_member_rows'],
            'create_pigeon_rows' => $preview['create_pigeon_rows'],
            'rows' => array_slice($preview['rows'], 0, self::PREVIEW_SAMPLE_LIMIT),
            'sample_limit' => self::PREVIEW_SAMPLE_LIMIT,
        ];
    }

    public function storeRowsForConfirmation(array $rows): string
    {
        $token = (string) Str::uuid();
        $payload = json_encode(['rows' => $rows], JSON_UNESCAPED_UNICODE);
        if ($payload === false || ! Storage::disk('local')->put($this->previewPath($token), $payload)) {
            throw ValidationException::withMessages(['upload' => '导入预览缓存保存失败，请重新上传。']);
        }

        return $token;
    }

    public function commitStoredPreview(RegistrationCategory $category, string $fileName, string $token, ?int $adminId): ImportBatch
    {
        try {
            return $this->commitRows($category, $fileName, $this->rowsFromStoredPreview($token), $adminId);
        } finally {
            $this->forgetStoredPreview($token);
        }
    }

    public function discardStoredPreview(?string $token): void
    {
        if ($token !== null) {
            $this->forgetStoredPreview($token);
        }
    }

    private function commitRows(RegistrationCategory $category, string $fileName, array $rows, ?int $adminId): ImportBatch
    {
        $stage = $this->firstStage($category);
        $preview = $this->preview($rows, $category);

        return DB::transaction(function () use ($category, $stage, $fileName, $adminId, $preview): ImportBatch {
            $batch = ImportBatch::query()->create([
                'file_name' => $fileName,
                'total_rows' => $preview['total_rows'],
                'success_rows' => 0,
                'failed_rows' => $preview['failed_rows'],
                'duplicate_rows' => $preview['duplicate_rows'],
                'uploaded_by' => $adminId,
                'status' => 'processing',
            ]);

            $success = 0;
            $memberCache = [];
            $pigeonCache = [];
            $entryRows = [];
            $nextGroupIndexByMember = [];
            $affectedMemberIds = ProgressiveStageEntry::query()
                ->where('registration_category_id', $category->id)
                ->where('race_project_id', $stage->id)
                ->where('source', ProgressiveStageEntry::SOURCE_IMPORT)
                ->pluck('member_id')
                ->all();

            ProgressiveStageEntry::query()
                ->where('registration_category_id', $category->id)
                ->where('race_project_id', $stage->id)
                ->where('source', ProgressiveStageEntry::SOURCE_IMPORT)
                ->delete();

            foreach ($preview['rows'] as $row) {
                if (! $row['is_selected'] || $row['errors'] !== []) {
                    continue;
                }

                $data = $row['data'];
                $member = $memberCache[$data['loft_number']] ?? Member::query()->firstOrNew(['loft_number' => $data['loft_number']]);
                if (! $member->exists) {
                    $member->forceFill([
                        'phone' => null,
                        'password' => null,
                        'must_change_password' => true,
                        'participant_name' => $data['participant_name'],
                        'status' => 'enabled',
                    ]);
                    $member->save();
                }
                $memberCache[$member->loft_number] = $member;

                $groupPigeons = [];
                foreach ($data['ring_numbers'] as $ringNumber) {
                    $pigeon = $pigeonCache[$ringNumber] ?? Pigeon::query()->firstOrNew(['ring_number' => $ringNumber]);
                    if (! $pigeon->exists) {
                        $pigeon->forceFill([
                            'member_id' => $member->id,
                            'loft_number' => $member->loft_number,
                            'participant_name' => $member->participant_name,
                            'import_batch_id' => $batch->id,
                            'status' => 'normal',
                        ]);
                        $pigeon->save();
                    }
                    $pigeonCache[$pigeon->ring_number] = $pigeon;
                    $groupPigeons[] = $pigeon;
                }

                $affectedMemberIds[] = $member->id;
                $groupIndex = $nextGroupIndexByMember[$member->id] ?? 1;
                $nextGroupIndexByMember[$member->id] = $groupIndex + 1;
                $groupKey = $this->groupSignatureFromPigeonIds(collect($groupPigeons)->pluck('id')->all());

                foreach ($groupPigeons as $order => $pigeon) {
                    $entryRows[] = [
                        'registration_id' => null,
                        'race_id' => $category->race_id,
                        'registration_category_id' => $category->id,
                        'race_project_id' => $stage->id,
                        'member_id' => $member->id,
                        'group_key' => $groupKey,
                        'group_index' => $groupIndex,
                        'group_size_snapshot' => $stage->group_size,
                        'pigeon_id' => $pigeon->id,
                        'pigeon_sort_order' => $order + 1,
                        'loft_number_snapshot' => $member->loft_number,
                        'participant_name_snapshot' => $member->participant_name,
                        'ring_number_snapshot' => $pigeon->ring_number,
                        'project_name_snapshot' => $stage->name,
                        'price_cent_snapshot' => $stage->price_cent,
                        'status' => RegistrationStatus::Confirmed->value,
                        'source' => ProgressiveStageEntry::SOURCE_IMPORT,
                        'submitted_at' => now(),
                        'confirmed_at' => now(),
                        'confirmed_by' => $adminId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                $success++;

                if (count($entryRows) >= self::INSERT_CHUNK_SIZE) {
                    $this->insertEntries($entryRows);
                    $entryRows = [];
                }
            }

            if ($entryRows !== []) {
                $this->insertEntries($entryRows);
            }

            $failed = collect($preview['rows'])->reject(fn (array $row): bool => $row['errors'] === [])->values()->all();
            $reportPath = null;
            if ($failed !== []) {
                $reportPath = "imports/reports/progressive-import-errors-{$batch->id}.xlsx";
                Excel::store(new ProgressiveStageImportErrorExport($failed), $reportPath, 'local');
            }

            $batch->forceFill([
                'success_rows' => $success,
                'failed_rows' => count($failed),
                'duplicate_rows' => $preview['duplicate_rows'],
                'error_report_path' => $reportPath,
                'status' => 'completed',
            ])->save();

            collect($affectedMemberIds)
                ->unique()
                ->each(fn (int $memberId) => app(RaceCacheService::class)->forgetBootstrap($category->race, Member::query()->findOrFail($memberId)));

            return $batch;
        });
    }

    private function insertEntries(array $rows): void
    {
        ProgressiveStageEntry::query()->insert($rows);
    }

    private function validateRow(array $row, array $ringNumbers, int $groupSize): array
    {
        $errors = [];
        if ($row['loft_number'] === '') {
            $errors[] = '会员棚号不能为空';
        }
        if ($row['participant_name'] === '') {
            $errors[] = '会员参赛名不能为空';
        }
        if ($row['ring_number'] === '') {
            $errors[] = '足环号码不能为空';
        }
        if ($row['ring_number'] !== '' && count($ringNumbers) !== $groupSize) {
            $errors[] = "该阶段项目必须填写 {$groupSize} 羽足环";
        }

        return $errors;
    }

    private function splitRingNumbers(string $value): array
    {
        return collect(preg_split('/[，,]+/u', $value) ?: [])
            ->map(fn (string $ringNumber): string => trim($ringNumber))
            ->filter()
            ->values()
            ->all();
    }

    private function groupSignatureFromRingNumbers(array $ringNumbers): string
    {
        sort($ringNumbers, SORT_NATURAL);

        return implode('|', $ringNumbers);
    }

    private function groupSignatureFromPigeonIds(array $pigeonIds): string
    {
        $ids = array_map('intval', $pigeonIds);
        sort($ids, SORT_NUMERIC);

        return implode(':', $ids);
    }

    private function isSelectedMarker(string $value): bool
    {
        return in_array(mb_strtolower(trim($value)), ['✓', '√', '1', '是', 'yes'], true);
    }

    private function isUnselectedMarker(string $value): bool
    {
        return trim($value) === '' || in_array(mb_strtolower(trim($value)), ['×', 'x', '0', '否', 'no'], true);
    }

    private function rowsFromStoredPreview(string $token): array
    {
        if (! Str::isUuid($token)) {
            throw ValidationException::withMessages(['upload' => '导入预览已失效，请重新上传。']);
        }

        $path = $this->previewPath($token);
        if (! Storage::disk('local')->exists($path)) {
            throw ValidationException::withMessages(['upload' => '导入预览已失效，请重新上传。']);
        }

        $payload = json_decode((string) Storage::disk('local')->get($path), true);

        return is_array($payload) && isset($payload['rows']) && is_array($payload['rows']) ? $payload['rows'] : [];
    }

    private function forgetStoredPreview(string $token): void
    {
        Storage::disk('local')->delete($this->previewPath($token));
    }

    private function previewPath(string $token): string
    {
        return self::PREVIEW_DIR.'/'.$token.'.json';
    }
}
