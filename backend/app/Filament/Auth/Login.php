<?php

// [IN]: Administrator account identifier and password / 管理员账号标识与密码
// [OUT]: Phone-or-email password-only Filament login with persistent sign-in enabled by default / 默认启用持久登录的手机号或邮箱纯密码 Filament 登录
// [POS]: Backend administrator login page / 后台管理员登录页
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Filament\Auth;

use App\Models\User;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Auth\Http\Responses\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Facades\Filament;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class Login extends BaseLogin
{
    public function authenticate(): ?LoginResponse
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return null;
        }

        $data = $this->form->getState();
        $account = trim((string) $data['account']);
        $user = User::query()
            ->where('email', $account)
            ->orWhere('phone', $account)
            ->first();

        if (! $user || ! Hash::check((string) $data['password'], $user->password) || ! $user->canAccessPanel(Filament::getCurrentOrDefaultPanel())) {
            $this->throwFailureValidationException();
        }

        Filament::auth()->login($user, (bool) ($data['remember'] ?? true));
        session()->regenerate();
        session()->put('password_hash_'.Filament::getAuthGuard(), $user->getAuthPassword());

        return app(LoginResponse::class);
    }

    protected function getEmailFormComponent(): TextInput
    {
        return TextInput::make('account')
            ->label('手机号或邮箱')
            ->required()
            ->autocomplete('username')
            ->autofocus();
    }

    protected function getRememberFormComponent(): Checkbox
    {
        return Checkbox::make('remember')
            ->label('保持登录')
            ->default(true);
    }

    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.account' => '账号或密码错误。',
        ]);
    }
}
