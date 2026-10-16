<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Rules\PasswordBytes;
use App\Support\Audit;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB, Hash, Log, Password, RateLimiter};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    private function normalizeEmail(Request $request): void
    {
        if (is_string($request->input('email'))) {
            $request->merge(['email' => Str::lower(trim($request->input('email')))]);
        }
    }

    private function storedEmail(string $email): string
    {
        // Preserve access for legacy mixed-case addresses without rewriting historical data.
        $users = User::whereRaw('LOWER(email) = ?', [$email])->limit(2)->get(['email']);
        return $users->count() === 1 ? $users->first()->email : $email;
    }

    public function login(Request $request)
    {
        $this->normalizeEmail($request);
        $data = $request->validate(['email' => 'required|string|email|max:150', 'password' => 'required|string|max:1024', 'remember' => 'sometimes|boolean']);
        $key = 'login:'.hash('sha256', $data['email'].'|'.$request->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => 'Terlalu banyak percobaan. Coba lagi dalam '.RateLimiter::availableIn($key).' detik.']);
        }
        $credentials = ['email' => $this->storedEmail($data['email']), 'password' => $data['password']];
        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['email' => 'Email atau kata sandi tidak sesuai.']);
        }
        RateLimiter::clear($key);
        $request->session()->regenerate();
        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }

    public function forgot(Request $request)
    {
        $this->normalizeEmail($request);
        $data = $request->validate(['email' => 'required|string|email|max:150']);
        try {
            Password::sendResetLink(['email' => $this->storedEmail($data['email'])]);
        } catch (\Symfony\Component\Mailer\Exception\TransportExceptionInterface $e) {
            // Do not expose the recipient, credentials, or SMTP response to the browser/log.
            Log::error('Password reset mail transport unavailable.', ['exception' => $e::class]);
        }
        return back()->with('success', 'Jika email terdaftar, tautan reset akan dikirim. Periksa kotak masuk Anda. Jika tidak diterima, hubungi admin.');
    }

    public function reset(Request $request)
    {
        $this->normalizeEmail($request);
        $data = $request->validate([
            'token' => 'required|string|max:255', 'email' => 'required|string|email|max:150',
            'password' => ['bail', 'required', 'string', 'confirmed', 'min:12', new PasswordBytes],
        ]);
        $data['email'] = $this->storedEmail($data['email']);
        $data['password_confirmation'] = $request->input('password_confirmation');
        $status = Password::reset($data, function (User $user, string $password) {
            DB::transaction(function () use ($user, $password) {
                $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
                $user->tokens()->delete();
                Audit::record('account.password.reset', $user);
            });
            event(new PasswordReset($user));
        });
        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => 'Tautan reset tidak valid atau sudah kedaluwarsa.']);
        }
        return redirect()->route('login')->with('success', 'Kata sandi berhasil diubah. Silakan masuk.');
    }

    public function changePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => 'bail|required|string|current_password:web',
            'password' => ['bail', 'required', 'string', 'confirmed', 'min:12', 'different:current_password', new PasswordBytes],
        ]);
        DB::transaction(function () use ($request, $data) {
            $user = User::lockForUpdate()->findOrFail($request->user()->id);
            if (! Hash::check($data['current_password'], $user->password)) {
                throw ValidationException::withMessages(['current_password' => 'Kata sandi saat ini tidak sesuai.']);
            }
            $user->forceFill(['password' => Hash::make($data['password']), 'remember_token' => Str::random(60)])->save();
            $user->tokens()->delete();
            Audit::record('account.password.changed', $user);
            Auth::guard('web')->setUser($user);
        });
        $request->session()->regenerate();
        // AuthenticateSession stores the new hash on this session; older sessions fail its hash check.
        return redirect()->route('profile')->with('success', 'Kata sandi diperbarui. Sesi lain dan token API lama tidak dapat digunakan kembali.');
    }
}
