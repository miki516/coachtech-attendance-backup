@extends('layouts.app')

@section('css')
    <link rel="stylesheet" href="{{ asset('css/auth/form.css') }}">
@endsection

@section('content')
    <h1 class="sr-only">メール認証</h1>
    <div class="site-main-center">
        <section class="verify-card">
            <p class="verify-card-lead">
                登録していただいたメールアドレスに認証メールを送付しました。<br>
                メール認証を完了してください。
            </p>

            <a href="{{ config('app.mail_client_url') }}" class="btn btn-verify">
                認証はこちらから
            </a>

            <form method="POST" action="{{ route('verification.send') }}">
                @csrf
                <button type="submit" class="form-footer-link">認証メールを再送する</button>
            </form>
        </section>
    </div>
@endsection
