<?php
// [IN]: Member registration submit HTTP payload / 会员报名提交 HTTP 数据
// [OUT]: Validated config version, idempotency key, standard entries, and progressive entries / 已校验配置版本、幂等键、普通报名与递进报名项目
// [POS]: Backend registration submit request contract / 后端报名提交请求契约
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Http\Requests\Member;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SubmitRegistrationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'config_version' => ['required', 'integer', 'min:1'],
            'idempotency_key' => ['required', 'uuid'],
            'entries' => ['nullable', 'array'],
            'entries.*.project_id' => ['required', 'integer', 'min:1'],
            'entries.*.pigeon_ids' => ['required', 'array', 'min:1'],
            'entries.*.pigeon_ids.*' => ['required', 'integer', 'min:1'],
            'progressive_entries' => ['nullable', 'array'],
            'progressive_entries.*.category_id' => ['required', 'integer', 'min:1'],
            'progressive_entries.*.stage_project_id' => ['required', 'integer', 'min:1'],
            'progressive_entries.*.pigeon_ids' => ['required', 'array', 'min:1'],
            'progressive_entries.*.pigeon_ids.*' => ['required', 'integer', 'min:1'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $entries = $this->input('entries', []);
                $progressiveEntries = $this->input('progressive_entries', []);

                if ($entries === [] && $progressiveEntries === []) {
                    $validator->errors()->add('entries', '请至少选择一项报名项目。');
                }
            },
        ];
    }
}
