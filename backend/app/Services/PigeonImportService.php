<?php
// [IN]: Spreadsheet rows, server-side preview tokens, members, pigeons, and admin id / 电子表格行、服务端预览令牌、会员、足环与管理员 ID
// [OUT]: Small browser preview, committed pigeon rows, and error report / 小体积浏览器预览、已写入足环与错误报告
// [POS]: Backend Excel pigeon import rule service / 后端 Excel 足环导入规则服务
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Services;

use App\Exports\PigeonImportErrorExport;
use App\Imports\SpreadsheetArrayImport;
use App\Models\ImportBatch;
use App\Models\Member;
use App\Models\Pigeon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class PigeonImportService
{
    public const HEADERS = ['序号', '会员棚号', '会员参赛名', '足环号码'];
    public const MAX_UPLOAD_KB = 51200;
    public const PREVIEW_SAMPLE_LIMIT = 50;
    private const PREVIEW_DIR = 'imports/previews';
    private const QUERY_CHUNK_SIZE = 1000;
    private const INSERT_CHUNK_SIZE = 1000;

    public function rowsFromSpreadsheet(string $path): array
    {
        $sheets = Excel::toArray(new SpreadsheetArrayImport(), $path);
        $rows = $sheets[0] ?? [];

        if ($rows === []) {
            throw ValidationException::withMessages(['upload' => 'Excel 文件为空。']);
        }

        $header = array_map(fn ($value): string => trim((string) $value), array_values(array_shift($rows)));

        if (array_slice($header, 0, 4) !== self::HEADERS) {
            throw ValidationException::withMessages(['upload' => 'Excel 表头必须为：序号，会员棚号，会员参赛名，足环号码。']);
        }

        $normalized = [];

        foreach ($rows as $index => $row) {
            $values = array_pad(array_values($row), 4, null);
            $item = [
                'line' => $index + 2,
                'sequence' => trim((string) $values[0]),
                'loft_number' => trim((string) $values[1]),
                'participant_name' => trim((string) $values[2]),
                'ring_number' => trim((string) $values[3]),
            ];

            if (implode('', $item) === (string) $item['line']) {
                continue;
            }

            $normalized[] = $item;
        }

        return $normalized;
    }

    public function preview(array $rows): array
    {
        $members = $this->membersByLoft($rows);
        $existingRings = $this->existingRingSet($rows);
        $seenRings = [];
        $rowsPreview = [];

        foreach ($rows as $row) {
            $errors = $this->validateRow($row);

            if ($row['ring_number'] !== '' && isset($seenRings[$row['ring_number']])) {
                $errors[] = '本次文件内足环号码重复';
            }

            if ($row['ring_number'] !== '' && $existingRings->has($row['ring_number'])) {
                $errors[] = '足环号码已存在';
            }

            $member = $members->get($row['loft_number']);
            $willUpdateName = $member && $member->participant_name !== $row['participant_name'];

            $seenRings[$row['ring_number']] = true;
            $rowsPreview[] = [
                'line' => $row['line'],
                'data' => [
                    'sequence' => $row['sequence'],
                    'loft_number' => $row['loft_number'],
                    'participant_name' => $row['participant_name'],
                    'ring_number' => $row['ring_number'],
                ],
                'member_id' => $member?->id,
                'will_create_member' => ! $member && $row['loft_number'] !== '',
                'will_update_member_name' => (bool) $willUpdateName,
                'errors' => array_values(array_unique($errors)),
            ];
        }

        $failedRows = collect($rowsPreview)->reject(fn (array $row): bool => $row['errors'] === []);

        return [
            'source_rows' => $rows,
            'total_rows' => count($rows),
            'valid_rows' => count($rowsPreview) - $failedRows->count(),
            'failed_rows' => $failedRows->count(),
            'duplicate_rows' => $failedRows->filter(fn (array $row): bool => collect($row['errors'])->contains(fn (string $error): bool => str_contains($error, '重复') || str_contains($error, '已存在')))->count(),
            'create_member_rows' => collect($rowsPreview)->where('errors', [])->where('will_create_member', true)->pluck('data.loft_number')->unique()->count(),
            'update_member_name_rows' => collect($rowsPreview)->where('errors', [])->where('will_update_member_name', true)->pluck('data.loft_number')->unique()->count(),
            'rows' => $rowsPreview,
        ];
    }

    public function browserPreview(array $preview): array
    {
        return [
            'token' => $preview['token'] ?? null,
            'total_rows' => $preview['total_rows'],
            'valid_rows' => $preview['valid_rows'],
            'failed_rows' => $preview['failed_rows'],
            'duplicate_rows' => $preview['duplicate_rows'],
            'create_member_rows' => $preview['create_member_rows'],
            'update_member_name_rows' => $preview['update_member_name_rows'],
            'rows' => array_slice($preview['rows'], 0, self::PREVIEW_SAMPLE_LIMIT),
            'sample_limit' => self::PREVIEW_SAMPLE_LIMIT,
        ];
    }

    public function storeRowsForConfirmation(array $rows): string
    {
        $token = (string) Str::uuid();
        $path = $this->previewPath($token);
        $payload = json_encode(['rows' => $rows], JSON_UNESCAPED_UNICODE);

        if ($payload === false || ! Storage::disk('local')->put($path, $payload)) {
            throw ValidationException::withMessages(['upload' => '导入预览缓存保存失败，请重新上传。']);
        }

        return $token;
    }

    public function commit(string $fileName, array $preview, ?int $adminId): ImportBatch
    {
        return $this->commitRows($fileName, $preview['source_rows'] ?? [], $adminId);
    }

    public function commitStoredPreview(string $fileName, string $token, ?int $adminId): ImportBatch
    {
        try {
            return $this->commitRows($fileName, $this->rowsFromStoredPreview($token), $adminId);
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

    private function commitRows(string $fileName, array $rows, ?int $adminId): ImportBatch
    {
        $preview = $this->preview($rows);

        return DB::transaction(function () use ($fileName, $adminId, $preview): ImportBatch {
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
            $affectedMemberIds = [];
            $insertRows = [];
            $memberCache = [];

            foreach ($preview['rows'] as $row) {
                if ($row['errors'] !== []) {
                    continue;
                }

                $data = $row['data'];
                $member = $memberCache[$data['loft_number']] ?? Member::query()->firstOrNew(['loft_number' => $data['loft_number']]);
                $isNewMember = ! $member->exists;
                $member->phone ??= null;
                $member->password ??= null;
                if ($isNewMember) {
                    $member->must_change_password = true;
                }
                $member->participant_name = $data['participant_name'];
                $member->status ??= 'enabled';
                $member->save();
                $memberCache[$member->loft_number] = $member;

                $affectedMemberIds[] = $member->id;
                $insertRows[] = [
                    'member_id' => $member->id,
                    'loft_number' => $member->loft_number,
                    'participant_name' => $member->participant_name,
                    'ring_number' => $data['ring_number'],
                    'import_batch_id' => $batch->id,
                    'status' => 'normal',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                $success++;

                if (count($insertRows) >= self::INSERT_CHUNK_SIZE) {
                    Pigeon::query()->insert($insertRows);
                    $insertRows = [];
                }
            }

            if ($insertRows !== []) {
                Pigeon::query()->insert($insertRows);
            }

            $failed = collect($preview['rows'])->reject(fn (array $row): bool => $row['errors'] === [])->values()->all();
            $reportPath = null;

            if ($failed !== []) {
                $reportPath = "imports/reports/pigeon-import-errors-{$batch->id}.xlsx";
                Excel::store(new PigeonImportErrorExport($failed), $reportPath, 'local');
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
                ->each(fn (int $memberId) => app(RaceCacheService::class)->forgetMemberPigeonsById($memberId));

            return $batch;
        });
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
        if (! is_array($payload) || ! is_array($payload['rows'] ?? null)) {
            throw ValidationException::withMessages(['upload' => '导入预览数据损坏，请重新上传。']);
        }

        return $payload['rows'];
    }

    private function forgetStoredPreview(string $token): void
    {
        if (Str::isUuid($token)) {
            Storage::disk('local')->delete($this->previewPath($token));
        }
    }

    private function previewPath(string $token): string
    {
        return self::PREVIEW_DIR.'/'.$token.'.json';
    }

    private function membersByLoft(array $rows): \Illuminate\Support\Collection
    {
        return collect($rows)
            ->pluck('loft_number')
            ->filter()
            ->unique()
            ->values()
            ->chunk(self::QUERY_CHUNK_SIZE)
            ->flatMap(fn ($lofts) => Member::query()->whereIn('loft_number', $lofts->all())->get())
            ->keyBy('loft_number');
    }

    private function existingRingSet(array $rows): \Illuminate\Support\Collection
    {
        return collect($rows)
            ->pluck('ring_number')
            ->filter()
            ->unique()
            ->values()
            ->chunk(self::QUERY_CHUNK_SIZE)
            ->flatMap(fn ($rings) => Pigeon::query()->whereIn('ring_number', $rings->all())->pluck('ring_number'))
            ->flip();
    }

    private function validateRow(array $row): array
    {
        $errors = [];

        foreach (['loft_number' => '会员棚号为空', 'participant_name' => '会员参赛名为空', 'ring_number' => '足环号码为空'] as $field => $message) {
            if (($row[$field] ?? '') === '') {
                $errors[] = $message;
            }
        }

        return $errors;
    }
}
