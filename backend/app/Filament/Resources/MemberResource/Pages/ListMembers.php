<?php
// [IN]: MemberResource table definition and member import exports / MemberResource 表格定义与会员导入导出
// [OUT]: Filament member list page with import/template/delete-all actions / 带导入、模板与全部删除动作的 Filament 会员列表页面
// [POS]: Backend admin member index route / 后端后台会员索引路由
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Filament\Resources\MemberResource\Pages;

use App\Filament\Resources\MemberResource;
use App\Exports\MemberImportTemplateExport;
use App\Models\Member;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
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
            Action::make('deleteAllMembers')
                ->label('删除所有会员')
                ->color('danger')
                ->icon('heroicon-o-trash')
                ->requiresConfirmation()
                ->modalHeading('删除所有会员')
                ->modalDescription('此操作会删除全部会员，并同步删除所有会员的足环和报名记录。')
                ->modalSubmitActionLabel('确认删除')
                ->action(fn () => $this->deleteAllMembers()),
        ];
    }

    private function deleteAllMembers(): void
    {
        $deleted = MemberResource::deleteMembers(Member::query()->get());

        Notification::make()
            ->title("已删除 {$deleted} 个会员")
            ->success()
            ->send();
    }
}
