<?php
// [IN]: Member registration submit HTTP payload / 会员报名提交 HTTP 数据
// [OUT]: Validated config version, idempotency key, and entries / 已校验配置版本、幂等键与报名项目
// [POS]: Backend registration submit request contract / 后端报名提交请求契约
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Http\Requests\Member;

use Illuminate\Foundation\Http\FormRequest;

class SubmitRegistrationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'config_version' => ['required', 'integer', 'min:1'],
            'idempotency_key' => ['required', 'uuid'],
            'entries' => ['required', 'array', 'min:1'],
            'entries.*.project_id' => ['required', 'integer', 'min:1'],
            'entries.*.pigeon_ids' => ['required', 'array', 'min:1'],
            'entries.*.pigeon_ids.*' => ['required', 'integer', 'min:1'],
        ];
    }
}
