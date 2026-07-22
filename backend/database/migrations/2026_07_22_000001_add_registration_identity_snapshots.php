<?php

// [IN]: Existing registrations with related races and members / 含关联赛事与会员的既有报名记录
// [OUT]: Registration-time race and member identity snapshot columns / 报名时赛事与会员身份快照字段
// [POS]: Registration history snapshot schema migration / 报名历史快照结构迁移
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registrations', function (Blueprint $table): void {
            $table->string('race_name_snapshot')->nullable()->after('member_id');
            $table->string('loft_number_snapshot')->nullable()->after('race_name_snapshot');
            $table->string('participant_name_snapshot')->nullable()->after('loft_number_snapshot');
        });

        DB::table('registrations')
            ->join('races', 'races.id', '=', 'registrations.race_id')
            ->join('members', 'members.id', '=', 'registrations.member_id')
            ->select([
                'registrations.id as registration_id',
                'races.name as race_name',
                'members.loft_number',
                'members.participant_name',
            ])
            ->orderBy('registrations.id')
            ->chunk(500, function ($registrations): void {
                foreach ($registrations as $registration) {
                    DB::table('registrations')
                        ->where('id', $registration->registration_id)
                        ->update([
                            'race_name_snapshot' => $registration->race_name,
                            'loft_number_snapshot' => $registration->loft_number,
                            'participant_name_snapshot' => $registration->participant_name,
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table): void {
            $table->dropColumn([
                'race_name_snapshot',
                'loft_number_snapshot',
                'participant_name_snapshot',
            ]);
        });
    }
};
