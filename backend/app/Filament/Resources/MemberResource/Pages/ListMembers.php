<?php

// [IN]: MemberResource table definition and member/credential import exports / MemberResource 表格定义与会员/登录凭据导入导出
// [OUT]: Filament member list with import, export, and deletion actions / 提供导入、导出与删除动作的 Filament 会员列表
// [POS]: Backend admin member index route / 后端后台会员索引路由
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Filament\Resources\MemberResource\Pages;

use App\Exports\MemberCredentialImportTemplateExport;
use App\Exports\MemberExport;
use App\Exports\MemberImportTemplateExport;
use App\Filament\Resources\MemberResource;
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
                ->action(fn () => Excel::download(new MemberImportTemplateExport, '会员导入模板.xlsx')),
            Action::make('exportExcel')
                ->label('导出 Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(fn () => Excel::download(new MemberExport, '会员列表.xlsx')),
            Action::make('importCredentials')
                ->label('导入手机号密码')
                ->url(MemberResource::getUrl('import-credentials')),
            Action::make('downloadCredentialTemplate')
                ->label('下载手机号密码模板')
                ->action(fn () => Excel::download(new MemberCredentialImportTemplateExport, '会员手机号密码导入模板.xlsx')),
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
