<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Keep valid user input after validation without echoing unexpected arrays into HTML fields. */
class SanitizeFlashedInput
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        if (!$request->hasSession() || !$request->session()->has('_old_input')) return $response;
        $input = $request->session()->get('_old_input', []);
        foreach ($input as $key => $value) {
            $list = in_array($key, ['kelas_ids', 'prasyarat_ids'], true);
            $map = $key === 'nilai' || ($key === 'status' && $request->is('mengajar/*/presensi'));
            if ($list || $map) {
                $input[$key] = is_array($value) ? array_filter($value, fn ($item) => is_scalar($item) || $item === null) : [];
                if ($list) $input[$key] = array_values($input[$key]);
            } elseif (!is_scalar($value) && $value !== null) {
                $input[$key] = '';
            }
        }
        unset($input['password'], $input['password_confirmation'], $input['current_password'], $input['_token']);
        $request->session()->put('_old_input', $input);
        return $response;
    }
}
