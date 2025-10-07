<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>COACHTECH</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/sanitize.css') }}">
    <link rel="stylesheet" href="{{ asset('css/common.css') }}">

    @yield('css')
</head>

<body class="site">
    <header class="site-header">
        <div class="site-header-logo">
            <a href="{{ url('/') }}">
                <img src="{{ asset('images/logo.svg') }}" alt="COACHTECH">
            </a>
        </div>

        <nav class="site-header-nav">
            {{-- 会員登録・ログイン・メール認証誘導では非表示 --}}
            @unless (Route::is('register') || Route::is('login') || Route::is('verification.notice'))
                @auth

                    {{-- 一般ユーザーメニュー --}}
                    @unless (auth()->user()->is_admin)
                        {{-- 出勤登録 --}}
                        <a href="{{ route('user.attendance.register') }}" class="site-header-link">勤怠</a>

                        {{-- 勤怠一覧 --}}
                        <a href="{{ route('user.attendance.index') }}" class="site-header-link site-header-link-sell">勤怠一覧</a>

                        {{-- 申請詳細 --}}
                        <a href="{{ route('user.request.index') }}" class="site-header-link site-header-link-sell">申請</a>
                    @endunless

                    {{-- 管理者用メニュー --}}
                    @if (auth()->user()->is_admin)
                        {{-- 勤怠一覧 --}}
                        <a href="{{ route('admin.attendance.index') }}" class="site-header-link">勤怠一覧</a>

                        {{-- スタッフ一覧 --}}
                        <a href="{{ route('admin.staff.index') }}" class="site-header-link site-header-link-sell">スタッフ一覧</a>

                        {{-- 申請一覧 --}}
                        <a href="{{ route('admin.request.index') }}" class="site-header-link site-header-link-sell">申請一覧</a>
                    @endif

                @endauth

                {{-- ログイン or ログアウト --}}
                @auth
                    <form method="POST" action="{{ route('logout') }}" class="site-header-logout-form">
                        @csrf
                        <button type="submit" class="site-header-logout-button">ログアウト</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="site-header-link">ログイン</a>
                @endauth
            @endunless
        </nav>

    </header>

    <main class="site-main">
        @yield('content')
    </main>

    @yield('scripts')
</body>

</html>
