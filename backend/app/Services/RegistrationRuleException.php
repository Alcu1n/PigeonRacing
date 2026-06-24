<?php
// [IN]: Invalid registration rule state / 非法报名规则状态
// [OUT]: API-safe rule error code and message / API 安全规则错误码与消息
// [POS]: Backend fail-fast registration exception / 后端快速失败报名异常
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Services;

use RuntimeException;

class RegistrationRuleException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $httpStatus = 422,
    ) {
        parent::__construct($message);
    }
}
