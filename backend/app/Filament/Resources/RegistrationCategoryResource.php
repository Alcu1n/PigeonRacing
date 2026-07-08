<?php
// [IN]: Progressive registration category records / 递进报名类别记录
// [OUT]: Filament category management with current stage, import entry, stage data management, and template download / 带当前阶段、导入入口、阶段数据管理与模板下载的 Filament 类别管理
// [POS]: Backend admin progressive category resource / 后端后台递进类别资源
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Filament\Resources;

use App\Exports\ProgressiveStageImportTemplateExport;
use App\Filament\Resources\RegistrationCategoryResource\Pages;
use App\Models\RegistrationCategory;
use App\Services\ProgressiveStageImportService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Maatwebsite\Excel\Facades\Excel;

class RegistrationCategoryResource extends Resource
{
    protected static ?string $model = RegistrationCategory::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-path-rounded-square';
    protected static ?string $navigationLabel = '递进报名类别';
    protected static ?string $modelLabel = '递进报名类别';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('race_id')->label('赛事')->relationship('race', 'name')->required(),
            TextInput::make('name')->label('类别名称')->placeholder('站站赛 / 月月赛')->required()->maxLength(128),
            TextInput::make('sort_order')->label('排序')->numeric()->default(0),
            Toggle::make('is_enabled')->label('启用')->default(true),
            Select::make('current_stage_project_id')
                ->label('当前开放阶段')
                ->options(fn (?RegistrationCategory $record): array => $record?->stageProjects()->pluck('name', 'id')->all() ?? [])
                ->searchable()
                ->helperText('先在“报名项目”中为本类别创建递进阶段项目，再回到这里选择当前开放阶段。'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('race.name')->label('赛事')->searchable(),
            TextColumn::make('name')->label('类别')->searchable(),
            TextColumn::make('currentStage.name')->label('当前开放阶段')->placeholder('未设置'),
            TextColumn::make('stage_projects_count')->label('阶段数')->counts('stageProjects'),
            TextColumn::make('sort_order')->label('排序'),
            IconColumn::make('is_enabled')->label('启用')->boolean(),
        ])->recordActions([
            Action::make('importFirstStage')
                ->label('导入第一阶段')
                ->icon('heroicon-o-arrow-up-tray')
                ->url(fn (RegistrationCategory $record): string => self::getUrl('import-first-stage', ['record' => $record->getKey()])),
            Action::make('downloadFirstStageTemplate')
                ->label('下载模板')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(function (RegistrationCategory $record) {
                    $stage = app(ProgressiveStageImportService::class)->firstStage($record);

                    return Excel::download(new ProgressiveStageImportTemplateExport($stage->name, (int) $stage->group_size), "递进第一阶段导入模板-{$record->name}.xlsx");
                }),
            Action::make('manageStageData')
                ->label('阶段数据管理')
                ->icon('heroicon-o-table-cells')
                ->url(fn (RegistrationCategory $record): string => self::getUrl('stage-data', ['record' => $record->getKey()])),
            EditAction::make(),
            DeleteAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRegistrationCategories::route('/'),
            'create' => Pages\CreateRegistrationCategory::route('/create'),
            'edit' => Pages\EditRegistrationCategory::route('/{record}/edit'),
            'import-first-stage' => Pages\ImportFirstStage::route('/{record}/import-first-stage'),
            'stage-data' => Pages\ManageStageData::route('/{record}/stage-data'),
        ];
    }
}
