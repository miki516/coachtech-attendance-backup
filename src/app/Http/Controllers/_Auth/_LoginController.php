<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function login(LoginRequest $request)
    {
        $credentials = $request->only('email', 'password');

        // 一般ユーザーだけ通す
        if (Auth::attempt(array_merge($credentials, ['role' => 'user']))) {
            $request->session()->regenerate(); // セッション固定攻撃対策
            return redirect()->intended('/attendance');
        }

        // 失敗時
        return back()->withErrors([
            'email' => 'ログイン情報が登録されていません',
        ])->onlyInput('email');
    }

    public function logout()
    {
        Auth::logout();
        return redirect()->route('login');
    }
}
