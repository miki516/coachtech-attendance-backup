<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Models\User;
use App\Http\Requests\LoginRequest;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Contracts\LogoutResponse;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // ビュー
        Fortify::registerView(fn () => view('auth.register'));
        Fortify::loginView(fn () => view('auth.login'));

        Fortify::createUsersUsing(CreateNewUser::class);

        Fortify::authenticateUsing(function (Request $request) {
            $form = app(LoginRequest::class);

            // LoginRequest に書いたルールとメッセージを使用
            $request->validate(
                $form->rules(),
                $form->messages()
            );

            $user = User::where('email', $request->email)->first();

            // 一般ログイン(/login)は "user" だけ通す
            if ($user && $user->role === 'user' && Hash::check($request->password, $user->password)) {
                return $user;
            }

            return null;
        });

        // ログインの試行回数制限
        RateLimiter::for('login', function (Request $request) {
            $email = (string) $request->email;
            return Limit::perMinute(10)->by($email . $request->ip());
        });

        // ログイン後の遷移（役割で分岐）
        $this->app->singleton(LoginResponse::class, function () {
            return new class implements LoginResponse {
                public function toResponse($request)
                {
                    $role = $request->user()?->role;
                    return $role === 'admin'
                        ? redirect()->intended(route('admin.attendance.index'))
                        : redirect()->intended(route('user.attendance.punch'));
                }
            };
        });

        // ログアウト後の遷移
        $this->app->singleton(LogoutResponse::class, function () {
            return new class implements LogoutResponse {
                public function toResponse($request)
                {
                    return redirect()->route('login');
                }
            };
        });
    }
}
