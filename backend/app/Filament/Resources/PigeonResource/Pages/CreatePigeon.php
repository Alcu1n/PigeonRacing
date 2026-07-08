<?php

// [IN]: PigeonResource form data, Member/Pigeon models, and DB transaction / PigeonResource 表单数据、会员/足环模型与数据库事务
// [OUT]: Filament pigeon create page with single and range creation / 支持单个与范围创建的 Filament 足环创建页面
// [POS]: Backend admin pigeon create route / 后端后台足环创建路由
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Filament\Resources\PigeonResource\Pages;

use App\Filament\Resources\PigeonResource;
use App\Models\Member;
use App\Models\Pigeon;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreatePigeon extends CreateRecord
{
    protected static string $resource = PigeonResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $data = $this->withMemberSnapshot($data);
        $ringNumbers = $this->ringNumbers($data);
        $baseData = $this->stripBatchFields($data);
        unset($baseData['ring_number']);

        return DB::transaction(function () use ($baseData, $ringNumbers): Model {
            $this->guardUniqueRings($ringNumbers, (int) $baseData['pigeon_library_id']);

            $firstRecord = null;

            foreach ($ringNumbers as $ringNumber) {
                $record = new Pigeon([...$baseData, 'ring_number' => $ringNumber, 'status' => 'normal']);
                $record->save();

                $firstRecord ??= $record;
            }

            return $firstRecord;
        });
    }

    protected function preserveFormDataWhenCreatingAnother(array $data): array
    {
        return [
            'pigeon_library_id' => $data['pigeon_library_id'] ?? null,
            'member_id' => $data['member_id'] ?? null,
            'loft_number' => $data['loft_number'] ?? null,
            'participant_name' => $data['participant_name'] ?? null,
        ];
    }

    private function withMemberSnapshot(array $data): array
    {
        $memberId = $data['member_id'] ?? null;
        $member = $memberId ? Member::query()->find($memberId) : null;

        if (! $member) {
            throw ValidationException::withMessages(['member_id' => '请选择有效的会员棚号。']);
        }

        return [
            ...$data,
            'loft_number' => $member->loft_number,
            'participant_name' => $member->participant_name,
        ];
    }

    private function ringNumbers(array $data): array
    {
        $start = trim((string) ($data['batch_start_ring'] ?? ''));
        $end = trim((string) ($data['batch_end_ring'] ?? ''));

        if ($start !== '' || $end !== '') {
            return $this->ringNumbersFromRange($start, $end);
        }

        $ringNumber = trim((string) ($data['ring_number'] ?? ''));

        if ($ringNumber === '') {
            throw ValidationException::withMessages(['ring_number' => '请输入足环号码。']);
        }

        return [$ringNumber];
    }

    private function ringNumbersFromRange(string $start, string $end): array
    {
        if ($start === '' || $end === '') {
            throw ValidationException::withMessages(['batch_start_ring' => '批量增加必须同时填写起始和结束足环号。']);
        }

        [$startPrefix, $startNumber, $startWidth] = $this->splitRing($start, 'batch_start_ring');
        [$endPrefix, $endNumber, $endWidth] = $this->splitRing($end, 'batch_end_ring');

        if ($startPrefix !== $endPrefix || $startWidth !== $endWidth) {
            throw ValidationException::withMessages(['batch_end_ring' => '批量足环号只能改变末尾数字，前缀和数字位数必须一致。']);
        }

        if ($startNumber > $endNumber) {
            throw ValidationException::withMessages(['batch_end_ring' => '结束足环号必须大于或等于起始足环号。']);
        }

        if (($endNumber - $startNumber + 1) > 500) {
            throw ValidationException::withMessages(['batch_end_ring' => '单次批量增加最多支持 500 个足环。']);
        }

        $ringNumbers = [];

        for ($number = $startNumber; $number <= $endNumber; $number++) {
            $ringNumbers[] = $startPrefix.str_pad((string) $number, $startWidth, '0', STR_PAD_LEFT);
        }

        return $ringNumbers;
    }

    private function splitRing(string $ringNumber, string $field): array
    {
        if (! preg_match('/^(.*?)(\d+)$/', $ringNumber, $matches)) {
            throw ValidationException::withMessages([$field => '足环号必须以数字结尾，例如 2025-13-0001。']);
        }

        return [$matches[1], (int) $matches[2], strlen($matches[2])];
    }

    private function guardUniqueRings(array $ringNumbers, int $libraryId): void
    {
        $existing = Pigeon::query()
            ->where('pigeon_library_id', $libraryId)
            ->whereIn('ring_number', $ringNumbers)
            ->pluck('ring_number')
            ->all();

        if ($existing === []) {
            return;
        }

        throw ValidationException::withMessages([
            'ring_number' => '以下足环号已存在：'.implode('、', array_slice($existing, 0, 10)),
        ]);
    }

    private function stripBatchFields(array $data): array
    {
        unset($data['batch_start_ring'], $data['batch_end_ring']);

        return $data;
    }
}
