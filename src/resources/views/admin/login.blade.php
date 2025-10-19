@extends('layouts.app')

@section('css')
    <link rel="stylesheet" href="{{ asset('css/auth/form.css') }}">
@endsection

@section('content')
    <div class="login-page">
        <h1 class="page-title">管理者ログイン</h1>
        <div class="form-content">
            <form class="form" method="POST" action="{{ route('admin.login.post') }}" novalidate autocomplete="on">
                <div class="form-body">
                    @csrf
                    <!-- メールアドレス -->
                    <div class="form-group">
                        <div class="form-group-title">
                            <label class="form-label-item" for="email">メールアドレス</label>
                        </div>
                        <div>
                            <input class="field-control" id="email" type="email" name="email"
                                value="{{ old('email') }}" autocomplete="username" inputmode="email" required />
                        </div>
                        <div class="form-error">
                            @error('email')
                                {{ $message }}
                            @enderror
                        </div>
                    </div>

                    <!-- パスワード -->
                    <div class="form-group">
                        <div class="form-group-title">
                            <label class="form-label-item" for="password">パスワード</label>
                        </div>
                        <div class="form-group-content">
                            <div class="field">
                                <input class="field-control" id="password" type="password" name="password"
                                    autocomplete="current-password" required />
                            </div>
                            <div class="form-error">
                                @error('password')
                                    {{ $message }}
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>
                <div class="form-button">
                    <button class="btn btn-solid auth-form-submit" type="submit">管理者ログインする</button>
                </div>
            </form>
        </div>
    </div>
@endsection
