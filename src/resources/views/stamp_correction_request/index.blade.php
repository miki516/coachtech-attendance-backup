@extends('layouts.app')

@section('content')
    <div class="page">

        <h2>申請一覧</h2>

        {{-- タブ切り替え --}}
        <div class="tab-area">
            <a href="#pending" class="tab tab--active">承認待ち</a>
            <a href="#approved" class="tab">承認済み</a>
        </div>

        {{-- テーブル --}}
        <table class="request-table">
            <thead>
                <tr>
                    <th>状態</th>
                    <th>名前</th>
                    <th>対象日時</th>
                    <th>申請理由</th>
                    <th>申請日時</th>
                    <th>詳細</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($requests as $req)
                    <tr>
                        <td>
                            @if ($req->status === 'pending')
                                <span class="status status--pending">承認待ち</span>
                            @elseif ($req->status === 'approved')
                                <span class="status status--approved">承認済み</span>
                            @else
                                <span class="status status--rejected">却下</span>
                            @endif
                        </td>
                        <td>{{ $req->user->name }}</td>
                        <td>{{ $req->attendance?->clock_in?->format('Y/m/d') ?? '-' }}</td>
                        <td>{{ $req->reason }}</td>
                        <td>{{ $req->created_at->format('Y/m/d') }}</td>
                        <td>
                            <a
                                href="{{ route('user.attendance.show', ['date' => $req->attendance?->clock_in?->format('Y-m-d')]) }}">詳細</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">申請はありません。</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

    </div>
@endsection
