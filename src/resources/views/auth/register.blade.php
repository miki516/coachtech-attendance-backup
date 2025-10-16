@extends('layouts.app')

@section('css')
    <link rel="stylesheet" href="{{ asset('css/auth/form.css') }}">
@endsection

@section('content')
    <div class="register-page">

        <h1 class="page-title">会員登録</h1>

        <div class="form-content">
            <div class="form-body">
                <form class="form" method="POST" action="{{ route('register') }}" novalidate>
                    @csrf
                    <!-- 名前 -->
                    <div class="form-group">
                        <div class="form-group-title">
                            <label class="form-label-item" for="name">ユーザー名</label>
                        </div>
                        <div>
                            <input class="field-control" id="name" type="text" name="name"
                                value="{{ old('name') }}" required autofocus />
                        </div>
                        <div class="form-error">
                            @error('name')
                                {{ $message }}
                            @enderror
                        </div>
                    </div>

                    <!-- メールアドレス -->
                    <div class="form-group">
                        <div class="form-group-title">
                            <label class="form-label-item" for="email">メールアドレス</label>
                        </div>
                        <div>
                            <input class="field-control" id="email" type="email" name="email"
                                value="{{ old('email') }}" required>
                            @error('email')
                                <div class="form-error">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <!-- パスワード -->
                    <div class="form-group">
                        <div class="form-group-title">
                            <label class="form-label-item" for="password">パスワード</label>
                        </div>
                        <div>
                            <input class="field-control" id="password" type="password" name="password" required
                                autocomplete="new-password">
                            @error('password')
                                @if ($message !== 'パスワードと一致しません')
                                    <div class="form-error">{{ $message }}</div>
                                @endif
                            @enderror
                        </div>
                    </div>

                    <!-- パスワード確認 -->
                    <div class="form-group">
                        <div class="form-group-title">
                            <label class="form-label-item" for="password_confirmation">確認用パスワード</label>
                        </div>
                        <div>
                            <input class="field-control" id="password_confirmation" type="password"
                                name="password_confirmation" required autocomplete="new-password">
                            @error('password_confirmation')
                                <div class="form-error">{{ $message }}</div>
                            @enderror
                            @error('password')
                                @if ($message === 'パスワードと一致しません')
                                    <div class="form-error">{{ $message }}</div>
                                @endif
                            @enderror
                        </div>
                    </div>
            </div>
            <div class="form-actions">
                <div class="form-button">
                    <button class="btn btn-solid auth-form-submit" type="submit">登録する</button>
                </div>
            </div>
            </form>
            <div class="form-footer">
                <a href="{{ route('login') }}" class="form-footer-link">ログインはこちら</a>
            </div>
        </div>
    </div>
@endsection
