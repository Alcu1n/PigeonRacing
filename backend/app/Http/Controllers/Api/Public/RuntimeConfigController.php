<?php

namespace App\Http\Controllers\Api\Public;

use App\Services\LocaleResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class RuntimeConfigController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $locale = (string) ($request->attributes->get('resolved_locale') ?: LocaleResolver::ZH_CN);
        $source = (string) ($request->attributes->get('resolved_locale_source') ?: 'fallback');

        return response()->json([
            'locale' => LocaleResolver::frontendLocale($locale),
            'source' => in_array($source, ['manual', 'ip', 'fallback'], true) ? $source : 'fallback',
        ]);
    }
}
