<?php
// [IN]: Race status strings from database / 数据库赛事状态字符串
// [OUT]: Typed race lifecycle values / 类型化赛事生命周期值
// [POS]: Backend race status enum / 后端赛事状态枚举
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Enums;

enum RaceStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Closed = 'closed';
    case Archived = 'archived';
}
