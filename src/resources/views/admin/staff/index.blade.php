@extends('layouts.app')

@section('content')
    <div class="page">
        <h1>スタッフ一覧</h1>

        @if (session('status'))
            <div class="alert alert-success" style="margin: 12px 0;">
                {{ session('status') }}
            </div>
        @endif

        @if ($staff->isEmpty())
            <p>一般ユーザーがいません。</p>
        @else
            <table style="width:100%; text-align:left; border-collapse:collapse;">
                <thead>
                    <tr>
                        <th style="padding:8px; border-bottom:1px solid #ddd;">名前</th>
                        <th style="padding:8px; border-bottom:1px solid #ddd;">メールアドレス</th>
                        <th style="padding:8px; border-bottom:1px solid #ddd; width: 180px;">操作</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($staff as $s)
                        <tr>
                            <td style="padding:8px; border-bottom:1px solid #f0f0f0;">{{ $s->name }}</td>
                            <td style="padding:8px; border-bottom:1px solid #f0f0f0;">{{ $s->email }}</td>
                            <td style="padding:8px; border-bottom:1px solid #f0f0f0;">
                                <a href="{{ route('admin.staff.show', ['staff' => $s->id]) }}">詳細</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
