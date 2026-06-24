<?php
// [IN]: Registration status strings from database / 数据库报名状态字符串
// [OUT]: Typed registration lifecycle values / 类型化报名生命周期值
// [POS]: Backend registration status enum / 后端报名状态枚举
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Enums;

enum RegistrationStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case PendingConfirmation = 'pending_confirmation';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
    case Voided = 'voided';
}
