<?php

namespace App\Http\Middleware;

use App\Models\SemesterAkademik;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class EnsureActiveTerm
{
    public function handle(Request $request, Closure $next)
    {
        if (SemesterAkademik::where('aktif', true)->count() !== 1) {
            if ($request->isMethod('GET') && !$request->expectsJson()) {
                return response()->view('academic.unavailable');
            }
            throw ValidationException::withMessages(['semester' => 'Semester akademik belum siap. Admin perlu menetapkan tepat satu semester aktif.']);
        }
        return $next($request);
    }
}
