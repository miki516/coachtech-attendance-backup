<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;

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
        // ユーザー作成アクション
        Fortify::createUsersUsing(CreateNewUser::class);

        // ビュー
        Fortify::registerView(fn () => view('auth.register'));
        Fortify::loginView(fn () => view('auth.login'));

        // ログインの試行回数制限
        RateLimiter::for('login', function (Request $request) {
         $email = (string) $request->email;

        return Limit::perMinute(10)->by($email . $request->ip());
        });
    }
}
