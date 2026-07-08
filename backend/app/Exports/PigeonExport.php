<?php

// [IN]: Pigeon model records / 足环模型记录
// [OUT]: Downloadable pigeon management workbook / 可下载的足环管理表格
// [POS]: Backend pigeon Excel export / 后端足环 Excel 导出
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Exports;

use App\Models\Pigeon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class PigeonExport implements FromCollection, ShouldAutoSize, WithHeadings
{
    public function headings(): array
    {
        return ['序号', '会员棚号', '会员参赛名', '足环号码', '状态', '创建时间'];
    }

    public function collection(): Collection
    {
        return Pigeon::query()
            ->orderBy('loft_number')
            ->orderBy('ring_number')
            ->orderBy('id')
            ->get(['loft_number', 'participant_name', 'ring_number', 'status', 'created_at'])
            ->values()
            ->map(fn (Pigeon $pigeon, int $index): array => [
                $index + 1,
                $pigeon->loft_number,
                $pigeon->participant_name,
                $pigeon->ring_number,
                $pigeon->status,
                $pigeon->created_at?->format('Y-m-d H:i:s') ?? '',
            ]);
    }
}
