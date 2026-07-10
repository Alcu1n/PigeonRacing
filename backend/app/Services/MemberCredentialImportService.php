<?php

// [IN]: Credential spreadsheet rows, existing members, and admin id / 登录凭据表格行、已有会员与管理员 ID
// [OUT]: Safe member credential import preview, commit, and error report / 安全的会员登录凭据导入预览、写入与错误报告
// [POS]: Backend Excel member credential import rule service / 后端 Excel 会员登录凭据导入规则服务
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Services;

use App\Exports\MemberCredentialImportErrorExport;
use App\Imports\SpreadsheetArrayImport;
use App\Models\ImportBatch;
use App\Models\Member;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class MemberCredentialImportService
{
    public const HEADERS = ['会员棚号', '手机号', '密码'];

    private const QUERY_CHUNK_SIZE = 500;

    public function rowsFromSpreadsheet(string $path): array
    {
        $sheets = Excel::toArray(new SpreadsheetArrayImport, $path);
        $rows = $sheets[0] ?? [];

        if ($rows === []) {
            throw ValidationException::withMessages(['upload' => 'Excel 文件为空。']);
        }

        $header = array_map(fn ($value): string => trim((string) $value), array_values(array_shift($rows)));

        if ($header !== self::HEADERS) {
            throw ValidationException::withMessages(['upload' => 'Excel 表头必须严格为：会员棚号，手机号，密码。']);
        }

        $normalized = [];

        foreach ($rows as $index => $row) {
            $values = array_pad(array_values($row), 3, null);
            $item = [
                'line' => $index + 2,
                'loft_number' => trim((string) $values[0]),
                'phone' => trim((string) $values[1]),
                'password' => trim((string) $values[2]),
            ];

            if ($item['loft_number'] === '' && $item['phone'] === '' && $item['password'] === '') {
                continue;
            }

            $normalized[] = $item;
        }

        if ($normalized === []) {
            throw ValidationException::withMessages(['upload' => 'Excel 文件没有有效数据行。']);
        }

        return $normalized;
    }

    public function preview(array $rows): array
    {
        return $this->preparePreview($rows, false);
    }

    private function preparePreview(array $rows, bool $lockForUpdate): array
    {
        $membersByLoft = $this->membersByLoft($rows, $lockForUpdate);
        $membersByPhone = $this->membersByPhone($rows, $lockForUpdate);
        $loftCounts = collect($rows)->pluck('loft_number')->filter()->countBy();
        $phoneCounts = collect($rows)->pluck('phone')->filter()->countBy();
        $rowsPreview = [];

        foreach ($rows as $row) {
            $formatErrors = $this->validateRow($row);
            $businessErrors = [];
            $member = $membersByLoft->get($row['loft_number']);
            $phoneOwner = $membersByPhone->get($row['phone']);

            if (($loftCounts->get($row['loft_number'], 0)) > 1) {
                $businessErrors[] = '本次文件内会员棚号重复';
            }

            if (($phoneCounts->get($row['phone'], 0)) > 1) {
                $businessErrors[] = '本次文件内手机号重复';
            }

            if (! $member && ($row['loft_number'] ?? '') !== '') {
                $businessErrors[] = '会员棚号不存在';
            } elseif ($member && filled($member->phone)) {
                $businessErrors[] = '该会员已有手机号';
            }

            if ($phoneOwner && (! $member || $phoneOwner->id !== $member->id)) {
                $businessErrors[] = '手机号已属于其他会员';
            }

            $errors = array_values(array_unique([...$formatErrors, ...$businessErrors]));
            $status = $businessErrors !== [] ? 'skipped' : ($formatErrors !== [] ? 'invalid' : 'ready');

            $rowsPreview[] = [
                'line' => $row['line'],
                'data' => [
                    'loft_number' => $row['loft_number'],
                    'phone' => $row['phone'],
                ],
                'password_filled' => ($row['password'] ?? '') !== '',
                'status' => $status,
                'errors' => $errors,
            ];
        }

        $previewRows = collect($rowsPreview);

        return [
            'total_rows' => count($rows),
            'valid_rows' => $previewRows->where('status', 'ready')->count(),
            'skipped_rows' => $previewRows->where('status', 'skipped')->count(),
            'invalid_rows' => $previewRows->where('status', 'invalid')->count(),
            'failed_rows' => $previewRows->whereIn('status', ['skipped', 'invalid'])->count(),
            'duplicate_rows' => $previewRows->filter(fn (array $row): bool => collect($row['errors'])->contains(
                fn (string $error): bool => str_contains($error, '重复') || str_contains($error, '已属于')
            ))->count(),
            'rows' => $rowsPreview,
        ];
    }

    public function commit(string $fileName, array $rows, ?int $adminId): ImportBatch
    {
        return MemberImportService::withAccountImportLock(
            fn (): ImportBatch => $this->commitInTransaction($fileName, $rows, $adminId)
        );
    }

    private function commitInTransaction(string $fileName, array $rows, ?int $adminId): ImportBatch
    {
        return DB::transaction(function () use ($fileName, $rows, $adminId): ImportBatch {
            $preview = $this->preparePreview($rows, true);
            $batch = ImportBatch::query()->create([
                'file_name' => $fileName,
                'total_rows' => $preview['total_rows'],
                'success_rows' => 0,
                'failed_rows' => $preview['failed_rows'],
                'duplicate_rows' => $preview['duplicate_rows'],
                'uploaded_by' => $adminId,
                'status' => 'processing',
            ]);
            $readyLines = collect($preview['rows'])
                ->where('status', 'ready')
                ->pluck('line')
                ->flip();
            $success = 0;

            foreach ($rows as $row) {
                if (! $readyLines->has($row['line'])) {
                    continue;
                }

                $member = Member::query()->where('loft_number', $row['loft_number'])->firstOrFail();
                $member->phone = $row['phone'];
                $member->password = $row['password'];
                $member->must_change_password = true;
                $member->save();
                $success++;
            }

            $failed = collect($preview['rows'])
                ->reject(fn (array $row): bool => $row['status'] === 'ready')
                ->values()
                ->all();
            $reportPath = null;

            if ($failed !== []) {
                $reportPath = "imports/reports/member-credential-import-errors-{$batch->id}.xlsx";
                Excel::store(new MemberCredentialImportErrorExport($failed), $reportPath, 'local');
            }

            $batch->forceFill([
                'success_rows' => $success,
                'failed_rows' => count($failed),
                'duplicate_rows' => $preview['duplicate_rows'],
                'error_report_path' => $reportPath,
                'status' => 'completed',
            ])->save();

            return $batch;
        });
    }

    private function membersByLoft(array $rows, bool $lockForUpdate): Collection
    {
        return collect($rows)
            ->pluck('loft_number')
            ->filter()
            ->unique()
            ->values()
            ->chunk(self::QUERY_CHUNK_SIZE)
            ->flatMap(function (Collection $lofts) use ($lockForUpdate): Collection {
                $query = Member::query()->whereIn('loft_number', $lofts->all())->orderBy('id');

                if ($lockForUpdate) {
                    $query->lockForUpdate();
                }

                return $query->get();
            })
            ->keyBy('loft_number');
    }

    private function membersByPhone(array $rows, bool $lockForUpdate): Collection
    {
        return collect($rows)
            ->pluck('phone')
            ->filter()
            ->unique()
            ->values()
            ->chunk(self::QUERY_CHUNK_SIZE)
            ->flatMap(function (Collection $phones) use ($lockForUpdate): Collection {
                $query = Member::query()->whereIn('phone', $phones->all())->orderBy('id');

                if ($lockForUpdate) {
                    $query->lockForUpdate();
                }

                return $query->get();
            })
            ->keyBy('phone');
    }

    private function validateRow(array $row): array
    {
        $errors = [];

        if (($row['loft_number'] ?? '') === '') {
            $errors[] = '会员棚号为空';
        } elseif (mb_strlen($row['loft_number']) > 64) {
            $errors[] = '会员棚号过长';
        }

        if (($row['phone'] ?? '') === '') {
            $errors[] = '手机号为空';
        } elseif (! preg_match('/^1[3-9]\d{9}$/', $row['phone'])) {
            $errors[] = '手机号格式不正确';
        }

        $passwordLength = mb_strlen($row['password'] ?? '');
        if ($passwordLength < 6 || $passwordLength > 128) {
            $errors[] = '密码长度必须为 6–128 个字符';
        }

        return $errors;
    }
}
