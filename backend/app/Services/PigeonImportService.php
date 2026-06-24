<?php
// [IN]: Parsed Excel rows and admin user id / 已解析 Excel 行与管理员 ID
// [OUT]: Import preview and committed pigeon rows / 导入预览与已写入足环行
// [POS]: Backend Excel pigeon import rule service / 后端 Excel 足环导入规则服务
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Services;

use App\Models\ImportBatch;
use App\Models\Member;
use App\Models\Pigeon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PigeonImportService
{
    private const HEADER_ALIASES = [
        'loft_number' => ['会员棚号', '棚号', '编号', '会员编号', '会员棚号（编号）'],
        'participant_name' => ['参赛名', '鸽舍名', '姓名'],
        'ring_number' => ['足环号码', '足环号', '环号'],
    ];

    public function preview(array $rows): array
    {
        $normalized = [];
        $seen = [];

        foreach ($rows as $line => $row) {
            $item = $this->normalizeRow($row);
            $errors = $this->validateRow($item, $seen);
            $seen[$item['ring_number'] ?? ''] = true;
            $normalized[] = [
                'line' => $line + 1,
                'data' => $item,
                'errors' => $errors,
            ];
        }

        return [
            'total_rows' => count($rows),
            'valid_rows' => collect($normalized)->where('errors', [])->count(),
            'failed_rows' => collect($normalized)->reject(fn ($row): bool => $row['errors'] === [])->count(),
            'rows' => $normalized,
        ];
    }

    public function commit(string $fileName, array $rows, ?int $adminId): ImportBatch
    {
        $preview = $this->preview($rows);

        return DB::transaction(function () use ($fileName, $adminId, $preview): ImportBatch {
            $batch = ImportBatch::query()->create([
                'file_name' => $fileName,
                'total_rows' => $preview['total_rows'],
                'success_rows' => 0,
                'failed_rows' => $preview['failed_rows'],
                'duplicate_rows' => 0,
                'uploaded_by' => $adminId,
                'status' => 'processing',
            ]);

            $members = Member::query()
                ->whereIn('loft_number', collect($preview['rows'])->pluck('data.loft_number')->filter()->unique())
                ->get()
                ->keyBy('loft_number');
            $existingRings = Pigeon::query()
                ->whereIn('ring_number', collect($preview['rows'])->pluck('data.ring_number')->filter()->unique())
                ->pluck('ring_number')
                ->flip();

            $success = 0;
            $duplicate = 0;
            $insertRows = [];

            foreach ($preview['rows'] as $row) {
                if ($row['errors'] !== []) {
                    continue;
                }

                $data = $row['data'];
                $member = $members->get($data['loft_number']);
                if (! $member) {
                    continue;
                }

                if ($existingRings->has($data['ring_number'])) {
                    $duplicate++;
                    continue;
                }

                $insertRows[] = [
                    'member_id' => $member->id,
                    'loft_number' => $member->loft_number,
                    'participant_name' => $data['participant_name'],
                    'ring_number' => $data['ring_number'],
                    'import_batch_id' => $batch->id,
                    'status' => 'normal',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                $success++;
            }

            if ($insertRows !== []) {
                Pigeon::query()->insert($insertRows);
            }

            $batch->forceFill([
                'success_rows' => $success,
                'duplicate_rows' => $duplicate,
                'failed_rows' => $preview['failed_rows'] + $duplicate,
                'status' => 'completed',
            ])->save();

            return $batch;
        });
    }

    private function normalizeRow(array $row): array
    {
        $mapped = [];
        foreach (self::HEADER_ALIASES as $field => $headers) {
            $mapped[$field] = '';
            foreach ($headers as $header) {
                if (array_key_exists($header, $row)) {
                    $mapped[$field] = trim((string) $row[$header]);
                    break;
                }
            }
        }

        return $mapped;
    }

    private function validateRow(array $row, array $seen): array
    {
        $errors = [];
        foreach (['loft_number' => '会员棚号为空', 'participant_name' => '参赛名为空', 'ring_number' => '足环号码为空'] as $field => $message) {
            if (($row[$field] ?? '') === '') {
                $errors[] = $message;
            }
        }

        if (($row['ring_number'] ?? '') !== '' && isset($seen[$row['ring_number']])) {
            $errors[] = '本次文件内足环号码重复';
        }

        return $errors;
    }
}
