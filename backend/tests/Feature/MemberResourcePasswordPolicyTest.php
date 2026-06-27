<?php
// [IN]: Filament member create/edit pages / Filament 会员创建与编辑页面
// [OUT]: First-login password policy hook assertions / 首次登录改密策略钩子断言
// [POS]: Backend member resource password policy test / 后端会员资源密码策略测试
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace Tests\Feature;

use App\Filament\Resources\MemberResource\Pages\CreateMember;
use App\Filament\Resources\MemberResource\Pages\EditMember;
use ReflectionClass;
use Tests\TestCase;

class MemberResourcePasswordPolicyTest extends TestCase
{
    public function test_create_page_marks_first_login_password_change_when_password_is_filled(): void
    {
        $data = $this->callProtected(new CreateMember(), 'mutateFormDataBeforeCreate', [[
            'phone' => '13800000000',
            'password' => 'secret1',
            'loft_number' => 'A001',
            'participant_name' => '张三鸽舍',
        ]]);

        $this->assertTrue($data['must_change_password']);
    }

    public function test_edit_page_marks_first_login_password_change_only_when_password_is_filled(): void
    {
        $page = new EditMember();

        $withoutPassword = $this->callProtected($page, 'mutateFormDataBeforeSave', [[
            'phone' => '13800000000',
            'loft_number' => 'A001',
            'participant_name' => '张三鸽舍',
        ]]);
        $this->assertArrayNotHasKey('must_change_password', $withoutPassword);

        $withPassword = $this->callProtected($page, 'mutateFormDataBeforeSave', [[
            'phone' => '13800000000',
            'password' => 'secret1',
            'loft_number' => 'A001',
            'participant_name' => '张三鸽舍',
        ]]);
        $this->assertTrue($withPassword['must_change_password']);
    }

    private function callProtected(object $object, string $method, array $arguments): array
    {
        $reflection = new ReflectionClass($object);
        $method = $reflection->getMethod($method);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $arguments);
    }
}
