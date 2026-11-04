<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Everything is English unless the person chooses Hindi (admin: /lang/hi; member app: header "X-Lang: hi" or the saved profile language).
 * For Hindi, fixed messages in JSON replies are looked up in lang/hi.json (exact match; anything not listed stays English).
 */
class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        $l = $request->header('X-Lang') ?: session('locale', $request->user()?->locale ?? 'en');
        $l = strtolower(substr((string) $l, 0, 2));
        app()->setLocale(in_array($l, ['en', 'hi'], true) ? $l : 'en');
        $res = $next($request);
        if (app()->getLocale() !== 'en' && $res instanceof JsonResponse) {
            $data = $res->getData(true);
            if (is_array($data) && strlen((string) $res->getContent()) < 300000) $res->setData($this->tr($data));
        }
        return $res;
    }

    private function tr(array $a): array
    {
        foreach ($a as $k => $v) {
            if (is_array($v)) $a[$k] = $this->tr($v);
            elseif (is_string($v) && strlen($v) > 3 && strlen($v) < 400) $a[$k] = __($v);
        }
        return $a;
    }
}
