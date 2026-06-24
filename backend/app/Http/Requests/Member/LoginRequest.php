<?php
// [IN]: Member login HTTP payload / 会员登录 HTTP 数据
// [OUT]: Validated phone and password credentials / 已校验手机号与密码凭据
// [POS]: Backend member login request contract / 后端会员登录请求契约
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Http\Requests\Member;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'max:32'],
            'password' => ['required', 'string', 'max:128'],
        ];
    }
}
