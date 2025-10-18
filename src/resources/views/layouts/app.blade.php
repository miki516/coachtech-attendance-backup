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
            <a href="{{ route('user.attendance.clockin') }}">
                <img src="{{ asset('images/logo.svg') }}" alt="COACHTECH">
            </a>
        </div>

        <nav class="site-header-nav">
            {{-- 会員登録・ログイン・メール認証誘導では非表示 --}}
            @unless (request()->routeIs(['register', 'login', 'verification.notice', 'admin.login']))
                @auth
                    @if (auth()->user()?->role === 'admin')
                        {{-- 管理者用メニュー --}}
                        <a href="{{ route('admin.attendance.index') }}" class="site-header-link">勤怠一覧</a>
                        <a href="{{ route('admin.staff.index') }}" class="site-header-link site-header-link-sell">スタッフ一覧</a>
                        <a href="{{ route('request.list') }}" class="site-header-link site-header-link-sell">申請一覧</a>
                    @else
                        {{-- 一般ユーザーメニュー --}}
                        <a href="{{ route('user.attendance.punch') }}" class="site-header-link">勤怠</a>
                        <a href="{{ route('user.attendance.index') }}" class="site-header-link site-header-link-sell">勤怠一覧</a>
                        <a href="{{ route('request.list') }}" class="site-header-link site-header-link-sell">申請</a>
                    @endif
                    {{-- ログアウトボタン --}}
                    @if (auth()->user()?->role === 'admin')
                        <form method="POST" action="{{ route('admin.logout') }}" class="site-header-logout-form">
                            @csrf
                            <button type="submit" class="site-header-logout-button">ログアウト</button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('logout') }}" class="site-header-logout-form">
                            @csrf
                            <button type="submit" class="site-header-logout-button">ログアウト</button>
                        </form>
                    @endif
                @endauth
            @endunless
        </nav>

    </header>

    <main class="site-main">
        @yield('content')
    </main>

    @yield('scripts')

    @stack('scripts')
</body>

</html>
