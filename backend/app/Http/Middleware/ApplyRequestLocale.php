<?php

namespace App\Http\Middleware;

use App\Services\LocaleResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class ApplyRequestLocale
{
    public function __construct(private readonly LocaleResolver $resolver) {}

    public function handle(Request $request, Closure $next): Response
    {
        $resolved = $this->resolver->resolve($request);
        App::setLocale($resolved['locale']);
        $request->attributes->set('resolved_locale', $resolved['locale']);
        $request->attributes->set('resolved_locale_source', $resolved['source']);

        return $next($request);
    }
}
