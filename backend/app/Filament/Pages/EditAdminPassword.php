<?php

// [IN]: Current authenticated administrator and password form state / 当前登录管理员与密码表单状态
// [OUT]: Current-password-verified administrator password change / 校验当前密码的管理员改密
// [POS]: Backend self-service password page / 后台自助修改密码页面
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Filament\Pages;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Auth\Pages\EditProfile;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class EditAdminPassword extends EditProfile
{
    protected static ?string $title = '修改密码';

    protected static bool $shouldRegisterNavigation = false;

    public ?array $data = [];

    public static function getLabel(): string
    {
        return '修改密码';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('current_password')->label('当前密码')->password()->required()->currentPassword(),
                TextInput::make('password')->label('新密码')->password()->required()->minLength(8)->same('password_confirmation'),
                TextInput::make('password_confirmation')->label('确认新密码')->password()->required(),
            ])
            ->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('修改密码')
                ->description('请先验证当前密码。新密码至少 8 位。')
                ->schema([
                    Form::make([EmbeddedSchema::make('form')])
                        ->id('form')
                        ->livewireSubmitHandler('save')
                        ->footer([
                            Actions::make([
                                Action::make('save')->label('保存新密码')->submit('save'),
                            ]),
                        ]),
                ]),
        ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $user = auth()->user();

        abort_unless($user instanceof User, 403);

        if (! Hash::check((string) $data['current_password'], $user->password)) {
            throw ValidationException::withMessages(['data.current_password' => '当前密码不正确。']);
        }

        $user->forceFill(['password' => $data['password']])->save();
        session()->put('password_hash_'.Filament::getAuthGuard(), $user->getAuthPassword());
        $this->form->fill();

        Notification::make()->title('密码已修改')->success()->send();
    }
}
