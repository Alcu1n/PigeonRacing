{{-- [IN]: UsageTutorial static page / UsageTutorial 静态页面 --}}
{{-- [OUT]: Admin usage tutorial content / 后台使用教程内容 --}}
{{-- [POS]: Admin usage tutorial Blade view / 后台使用教程 Blade 视图 --}}
{{-- Protocol: When updating me, sync this header + parent folder's .folder.md --}}
{{-- 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md --}}

<x-filament-panels::page>
    <style>
        .usage-tutorial-shell {
            max-width: 880px;
            display: grid;
            gap: 1rem;
        }

        .usage-tutorial-section {
            border: 1px solid color-mix(in oklch, currentColor 12%, transparent);
            border-radius: 1rem;
            background: color-mix(in oklch, currentColor 3%, transparent);
            padding: 1.1rem 1.25rem;
        }

        .usage-tutorial-heading {
            margin: 0 0 .55rem;
            font-size: 1.08rem;
            font-weight: 720;
        }

        .usage-tutorial-list {
            margin: 0;
            padding-left: 1.2rem;
            display: grid;
            gap: .35rem;
            color: color-mix(in oklch, currentColor 72%, transparent);
            font-size: .93rem;
            line-height: 1.6;
        }

        .usage-tutorial-steps {
            counter-reset: tutorial-step;
            margin: 0;
            padding: 0;
            list-style: none;
            display: grid;
            gap: .5rem;
        }

        .usage-tutorial-steps li {
            counter-increment: tutorial-step;
            display: flex;
            gap: .65rem;
            align-items: baseline;
            color: color-mix(in oklch, currentColor 72%, transparent);
            font-size: .93rem;
            line-height: 1.6;
        }

        .usage-tutorial-steps li::before {
            content: counter(tutorial-step);
            flex: none;
            display: grid;
            width: 1.45rem;
            height: 1.45rem;
            place-items: center;
            border-radius: 999px;
            background: color-mix(in oklch, currentColor 10%, transparent);
            font-size: .8rem;
            font-weight: 700;
        }
    </style>

    <div class="usage-tutorial-shell">
        <section class="usage-tutorial-section">
            <h2 class="usage-tutorial-heading">{{ __('快速上手流程') }}</h2>
            <ol class="usage-tutorial-steps">
                <li>{{ __('在「会员管理」中录入会员档案，维护登录手机号、棚号与参赛名。') }}</li>
                <li>{{ __('在「足环库管理」中维护全局足环库并设置启用状态。') }}</li>
                <li>{{ __('在「足环管理」中录入、批量导入并核对会员名下足环。') }}</li>
                <li>{{ __('在「售环记录」中完成售环快速录入、收款登记与台账管理。') }}</li>
                <li>{{ __('在「赛事管理」中创建赛事并设置报名时间。') }}</li>
                <li>{{ __('在「报名项目」中配置单羽、多羽组、阶段项目、金额与规则。') }}</li>
                <li>{{ __('在「递进报名类别」中配置站站赛、月月赛与当前开放阶段。') }}</li>
                <li>{{ __('在「报名记录」中查看报名明细、确认报名并导出 Excel。') }}</li>
                <li>{{ __('在「信息发布」中发布赛事规程、成绩与通知公告。') }}</li>
            </ol>
        </section>

        <section class="usage-tutorial-section">
            <h2 class="usage-tutorial-heading">{{ __('会员与足环') }}</h2>
            <ul class="usage-tutorial-list">
                <li>{{ __('会员管理：会员凭手机号登录会员端，棚号与参赛名用于报名与成绩展示。') }}</li>
                <li>{{ __('足环库管理：全局足环库决定哪些足环号码可被录入使用，注意启用状态。') }}</li>
                <li>{{ __('足环管理：支持逐只录入与批量导入，录入后可核对会员名下的足环清单。') }}</li>
                <li>{{ __('售环记录：记录售环明细与收款进度，支持筛选查看与台账管理。') }}</li>
            </ul>
        </section>

        <section class="usage-tutorial-section">
            <h2 class="usage-tutorial-heading">{{ __('赛事与报名') }}</h2>
            <ul class="usage-tutorial-list">
                <li>{{ __('赛事管理：先创建赛事并设置报名起止时间，会员端按时间开放报名。') }}</li>
                <li>{{ __('报名项目：在赛事下配置项目类型（单羽、多羽组、阶段项目）、金额与规则。') }}</li>
                <li>{{ __('递进报名类别：用于站站赛、月月赛等递进玩法，需设置当前开放阶段。') }}</li>
                <li>{{ __('报名记录：会员提交报名后在此确认，支持按赛事筛选并导出 Excel。') }}</li>
            </ul>
        </section>

        <section class="usage-tutorial-section">
            <h2 class="usage-tutorial-heading">{{ __('内容与设置') }}</h2>
            <ul class="usage-tutorial-list">
                <li>{{ __('信息发布：发布赛事规程、成绩与通知公告，会员端实时可见。') }}</li>
                <li>{{ __('权限管理（仅超级管理员）：维护管理员账号，并按模块分配查看、新增、编辑、删除权限。') }}</li>
                <li>{{ __('品牌设置：上传会员端登录页品牌 Logo，支持 PNG、JPG、WebP、GIF、AVIF、SVG。') }}</li>
            </ul>
        </section>
    </div>
</x-filament-panels::page>
