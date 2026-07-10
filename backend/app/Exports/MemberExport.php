<?php

// [IN]: Member model records and related pigeon counts / 会员模型记录与关联足环数量
// [OUT]: Downloadable member management workbook / 可下载的会员管理表格
// [POS]: Backend member Excel export / 后端会员 Excel 导出
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Exports;

use App\Models\Member;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class MemberExport implements FromCollection, ShouldAutoSize, WithColumnFormatting, WithHeadings
{
    public function headings(): array
    {
        return ['序号', '手机号', '会员棚号', '会员参赛名', '足环数量', '状态', '最近登录', '备注', '创建时间'];
    }

    public function collection(): Collection
    {
        return Member::query()
            ->withCount('pigeons')
            ->orderBy('loft_number')
            ->orderBy('id')
            ->get(['id', 'phone', 'loft_number', 'participant_name', 'status', 'last_login_at', 'remark', 'created_at'])
            ->values()
            ->map(fn (Member $member, int $index): array => [
                $index + 1,
                $member->phone ?? '',
                $member->loft_number,
                $member->participant_name,
                $member->pigeons_count,
                $member->status,
                $member->last_login_at?->format('Y-m-d H:i:s') ?? '',
                $member->remark ?? '',
                $member->created_at?->format('Y-m-d H:i:s') ?? '',
            ]);
    }

    public function columnFormats(): array
    {
        return [
            'B' => NumberFormat::FORMAT_TEXT,
            'C' => NumberFormat::FORMAT_TEXT,
        ];
    }
}
