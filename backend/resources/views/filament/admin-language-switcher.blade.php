{{-- [IN]: Current request locale and shared app_locale cookie / 当前请求语言与共享 app_locale Cookie --}}
{{-- [OUT]: Admin login and authenticated topbar language selector / 后台登录页与已登录顶部栏语言切换控件 --}}
{{-- [POS]: Filament global language switcher / Filament 全局语言切换控件 --}}
{{-- Protocol: When updating me, sync this header + parent folder's .folder.md --}}
{{-- 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md --}}

@php
    $currentLocale = app()->getLocale() === 'zh_TW' ? 'zh_TW' : 'zh_CN';
@endphp

<div class="app-language-switcher" data-app-language-switcher>
    <label class="sr-only" for="app-language-switcher-select">{{ __('语言') }}</label>
    <select id="app-language-switcher-select" aria-label="{{ __('语言') }}" data-app-language-select>
        <option value="zh_CN" @selected($currentLocale === 'zh_CN')>{{ __('简体中文') }}</option>
        <option value="zh_TW" @selected($currentLocale === 'zh_TW')>{{ __('台湾繁体中文') }}</option>
    </select>
</div>

@once
    <style>
        .app-language-switcher {
            position: relative;
            z-index: 20;
            display: inline-flex;
            align-items: center;
        }

        .app-language-switcher select {
            min-height: 2.25rem;
            border: 1px solid rgb(148 163 184 / 35%);
            border-radius: .6rem;
            background: rgb(255 255 255 / 90%);
            color: rgb(51 65 85);
            padding: .35rem 2rem .35rem .65rem;
            font-size: .78rem;
            font-weight: 650;
            line-height: 1.2;
        }

        .dark .app-language-switcher select {
            border-color: rgb(255 255 255 / 16%);
            background: rgb(24 24 27 / 90%);
            color: rgb(228 228 231);
        }

        .fi-topbar .app-language-switcher {
            margin-inline: .5rem;
        }

        .fi-simple-layout .app-language-switcher {
            position: fixed;
            top: max(1rem, env(safe-area-inset-top));
            right: max(1rem, env(safe-area-inset-right));
        }

        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }
    </style>

    <script>
        (() => {
            document.querySelectorAll('[data-app-language-select]').forEach((select) => {
                select.addEventListener('change', (event) => {
                    const value = event.target.value === 'zh_TW' ? 'zh_TW' : 'zh_CN';
                    const secure = window.location.protocol === 'https:' ? '; Secure' : '';
                    document.cookie = 'app_locale=' + encodeURIComponent(value) + '; Max-Age=31536000; Path=/; SameSite=Lax' + secure;
                    window.location.reload();
                });
            });
        })();
    </script>
@endonce
