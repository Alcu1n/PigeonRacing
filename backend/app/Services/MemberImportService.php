<?php

// [IN]: Spreadsheet rows, shared member import lock, members, batches, and admin id / 电子表格行、共享会员导入锁、会员、批次与管理员 ID
// [OUT]: Serialized member import preview, committed rows, and error report / 串行化的会员导入预览、已写入行与错误报告
// [POS]: Backend Excel member import rule service / 后端 Excel 会员导入规则服务
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Services;

use App\Exports\MemberImportErrorExport;
use App\Imports\SpreadsheetArrayImport;
use App\Models\ImportBatch;
use App\Models\Member;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class MemberImportService
{
    public const HEADERS = ['序号', '棚号', '参赛名', '手机号', '密码'];

    public const ACCOUNT_IMPORT_LOCK = 'member_account_import_lock';

    public function rowsFromSpreadsheet(string $path): array
    {
        $sheets = Excel::toArray(new SpreadsheetArrayImport, $path);
        $rows = $sheets[0] ?? [];

        if ($rows === []) {
            throw ValidationException::withMessages(['upload' => 'Excel 文件为空。']);
        }

        $header = array_map(fn ($value): string => trim((string) $value), array_values(array_shift($rows)));
        if (array_slice($header, 0, 5) !== self::HEADERS) {
            throw ValidationException::withMessages(['upload' => 'Excel 表头必须为：序号，棚号，参赛名，手机号，密码。']);
        }

        $normalized = [];
        foreach ($rows as $index => $row) {
            $values = array_pad(array_values($row), 5, null);
            $item = [
                'line' => $index + 2,
                'sequence' => trim((string) $values[0]),
                'loft_number' => trim((string) $values[1]),
                'participant_name' => trim((string) $values[2]),
                'phone' => trim((string) $values[3]),
                'password' => trim((string) $values[4]),
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
        $membersByLoft = Member::query()
            ->whereIn('loft_number', collect($rows)->pluck('loft_number')->filter()->unique())
            ->get()
            ->keyBy('loft_number');
        $membersByPhone = Member::query()
            ->whereIn('phone', collect($rows)->pluck('phone')->filter()->unique())
            ->get()
            ->keyBy('phone');
        $seenLofts = [];
        $seenPhones = [];
        $rowsPreview = [];

        foreach ($rows as $row) {
            $errors = $this->validateRow($row);

            if ($row['loft_number'] !== '' && isset($seenLofts[$row['loft_number']])) {
                $errors[] = '本次文件内棚号重复';
            }

            if ($row['phone'] !== '' && isset($seenPhones[$row['phone']])) {
                $errors[] = '本次文件内手机号重复';
            }

            $member = $membersByLoft->get($row['loft_number']);
            $phoneOwner = $row['phone'] === '' ? null : $membersByPhone->get($row['phone']);
            if ($phoneOwner && (! $member || $phoneOwner->id !== $member->id)) {
                $errors[] = '手机号已属于其他棚号';
            }

            $seenLofts[$row['loft_number']] = true;
            if ($row['phone'] !== '') {
                $seenPhones[$row['phone']] = true;
            }

            $rowsPreview[] = [
                'line' => $row['line'],
                'data' => [
                    'sequence' => $row['sequence'],
                    'loft_number' => $row['loft_number'],
                    'participant_name' => $row['participant_name'],
                    'phone' => $row['phone'],
                    'password' => $row['password'],
                ],
                'member_id' => $member?->id,
                'will_create_member' => ! $member && $row['loft_number'] !== '',
                'will_update_member' => (bool) $member,
                'will_reset_password' => $row['password'] !== '',
                'errors' => array_values(array_unique($errors)),
            ];
        }

        $failedRows = collect($rowsPreview)->reject(fn (array $row): bool => $row['errors'] === []);

        return [
            'source_rows' => $rows,
            'total_rows' => count($rows),
            'valid_rows' => count($rowsPreview) - $failedRows->count(),
            'failed_rows' => $failedRows->count(),
            'duplicate_rows' => $failedRows->filter(fn (array $row): bool => collect($row['errors'])->contains(fn (string $error): bool => str_contains($error, '重复') || str_contains($error, '已属于')))->count(),
            'create_member_rows' => collect($rowsPreview)->where('errors', [])->where('will_create_member', true)->count(),
            'update_member_rows' => collect($rowsPreview)->where('errors', [])->where('will_update_member', true)->count(),
            'reset_password_rows' => collect($rowsPreview)->where('errors', [])->where('will_reset_password', true)->count(),
            'rows' => $rowsPreview,
        ];
    }

    public function commit(string $fileName, array $preview, ?int $adminId): ImportBatch
    {
        return self::withAccountImportLock(
            fn (): ImportBatch => $this->commitInTransaction($fileName, $preview, $adminId)
        );
    }

    public static function withAccountImportLock(Closure $callback): ImportBatch
    {
        DB::table('app_settings')->insertOrIgnore([
            'key' => self::ACCOUNT_IMPORT_LOCK,
            'value' => 'mutex',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            return DB::transaction(function () use ($callback): ImportBatch {
                DB::table('app_settings')
                    ->where('key', self::ACCOUNT_IMPORT_LOCK)
                    ->lockForUpdate()
                    ->first();

                return $callback();
            });
        } catch (QueryException $exception) {
            if (in_array((int) ($exception->errorInfo[1] ?? 0), [1205, 1213], true)) {
                throw ValidationException::withMessages(['upload' => '另一项会员导入正在执行，请稍后重试。']);
            }

            throw $exception;
        }
    }

    private function commitInTransaction(string $fileName, array $preview, ?int $adminId): ImportBatch
    {
        $preview = $this->preview($preview['source_rows'] ?? []);

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

            foreach ($preview['rows'] as $row) {
                if ($row['errors'] !== []) {
                    continue;
                }

                $data = $row['data'];
                $member = Member::query()->firstOrNew(['loft_number' => $data['loft_number']]);
                $member->participant_name = $data['participant_name'];
                $member->status ??= 'enabled';

                if ($data['phone'] !== '') {
                    $member->phone = $data['phone'];
                }

                if ($data['password'] !== '') {
                    $member->password = $data['password'];
                    $member->must_change_password = true;
                } elseif (! $member->exists) {
                    $member->password = null;
                    $member->must_change_password = false;
                }

                $member->save();
                $affectedMemberIds[] = $member->id;
                $success++;
            }

            $failed = collect($preview['rows'])->reject(fn (array $row): bool => $row['errors'] === [])->values()->all();
            $reportPath = null;

            if ($failed !== []) {
                $reportPath = "imports/reports/member-import-errors-{$batch->id}.xlsx";
                Excel::store(new MemberImportErrorExport($failed), $reportPath, 'local');
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

    private function validateRow(array $row): array
    {
        $errors = [];

        foreach (['loft_number' => '棚号为空', 'participant_name' => '参赛名为空'] as $field => $message) {
            if (($row[$field] ?? '') === '') {
                $errors[] = $message;
            }
        }

        if (($row['phone'] ?? '') !== '' && mb_strlen($row['phone']) > 32) {
            $errors[] = '手机号过长';
        }

        if (($row['password'] ?? '') !== '' && mb_strlen($row['password']) < 6) {
            $errors[] = '密码至少 6 位';
        }

        return $errors;
    }
}
