<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('admin.auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (!Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Email atau kata sandi salah.'])->withInput();
        }

        $request->session()->regenerate();

        if (!$request->user()->isAdmin()) {
            Auth::logout();
            return back()->withErrors(['email' => 'Akun ini tidak memiliki akses admin.'])->withInput();
        }

        AuditService::log('auth.login.web', $request->user());

        return redirect()->intended(route('admin.dashboard'));
    }

    public function logout(Request $request)
    {
        AuditService::log('auth.logout.web', $request->user());
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
