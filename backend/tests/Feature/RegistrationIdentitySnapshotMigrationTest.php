<?php

// [IN]: Legacy registration rows and the identity snapshot migration / 旧报名记录与身份快照迁移
// [OUT]: Assertions that legacy race/member names are backfilled / 旧赛事与会员名称完成回填的断言
// [POS]: Backend registration identity migration feature test / 后端报名身份迁移功能测试
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RegistrationIdentitySnapshotMigrationTest extends TestCase
{
    private string $originalConnection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalConnection = (string) config('database.default');
        config()->set('database.connections.snapshot_test', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        DB::setDefaultConnection('snapshot_test');
        DB::purge('snapshot_test');

        Schema::create('members', function (Blueprint $table): void {
            $table->id();
            $table->string('loft_number');
            $table->string('participant_name');
        });
        Schema::create('races', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });
        Schema::create('registrations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('race_id');
            $table->unsignedBigInteger('member_id');
        });
    }

    protected function tearDown(): void
    {
        DB::purge('snapshot_test');
        DB::setDefaultConnection($this->originalConnection);
        parent::tearDown();
    }

    public function test_migration_backfills_existing_registration_identity(): void
    {
        DB::table('members')->insert(['id' => 1, 'loft_number' => 'Z999', 'participant_name' => '历史鸽舍']);
        DB::table('races')->insert(['id' => 1, 'name' => '历史赛事']);
        DB::table('registrations')->insert(['id' => 1, 'race_id' => 1, 'member_id' => 1]);

        $migration = require database_path('migrations/2026_07_22_000001_add_registration_identity_snapshots.php');
        $migration->up();

        $this->assertDatabaseHas('registrations', [
            'id' => 1,
            'race_name_snapshot' => '历史赛事',
            'loft_number_snapshot' => 'Z999',
            'participant_name_snapshot' => '历史鸽舍',
        ], 'snapshot_test');
    }
}
