<?php
// [IN]: MemberResource table definition and member import exports / MemberResource 表格定义与会员导入导出
// [OUT]: Filament member list page with import/template actions / 带导入与模板动作的 Filament 会员列表页面
// [POS]: Backend admin member index route / 后端后台会员索引路由
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Filament\Resources\MemberResource\Pages;

use App\Filament\Resources\MemberResource;
use App\Exports\MemberImportTemplateExport;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Maatwebsite\Excel\Facades\Excel;

class ListMembers extends ListRecords
{
    protected static string $resource = MemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('importExcel')
                ->label('导入 Excel')
                ->url(MemberResource::getUrl('import')),
            Action::make('downloadTemplate')
                ->label('下载模板')
                ->action(fn () => Excel::download(new MemberImportTemplateExport(), '会员导入模板.xlsx')),
        ];
    }
}
