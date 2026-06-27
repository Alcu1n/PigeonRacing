<?php
// [IN]: Member password-change HTTP payload / 会员改密 HTTP 数据
// [OUT]: Validated current and new password fields / 已校验当前密码与新密码字段
// [POS]: Backend member password request contract / 后端会员改密请求契约
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Http\Requests\Member;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePasswordRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'max:128'],
            'password' => ['required', 'string', 'min:6', 'max:128', 'confirmed'],
        ];
    }
}
