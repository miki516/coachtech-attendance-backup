@extends('layouts.app')

@section('content')
    <div class="page" style="max-width: 700px; margin: 0 auto;">
        <h1>修正申請詳細</h1>

        <div style="margin-bottom:16px;">
            <p><strong>申請者：</strong>{{ $requestRec->user?->name }}</p>
            <p><strong>対象日：</strong>{{ $date->format('Y年m月d日') }}</p>
            <p><strong>理由：</strong>{{ $requestRec->reason }}</p>
        </div>

        <h2>申請内容</h2>
        <table border="1" cellpadding="8" cellspacing="0" style="width:100%; text-align:center;">
            <thead style="background:#f2f2f2;">
                <tr>
                    <th>出勤</th>
                    <th>退勤</th>
                    <th>休憩</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>{{ $requestRec->clock_in_time?->format('H:i') ?? '—' }}</td>
                    <td>{{ $requestRec->clock_out_time?->format('H:i') ?? '—' }}</td>
                    <td>
                        @forelse ($requestRec->break_times as $b)
                            {{ $b['start']?->format('H:i') }}〜{{ $b['end']?->format('H:i') }}<br>
                        @empty
                            —
                        @endforelse
                    </td>
                </tr>
            </tbody>
        </table>

        <form method="POST" action="{{ route('admin.request.approve', $requestRec->id) }}"
            style="margin-top: 20px; text-align:right;">
            @csrf
            <button type="submit" style="padding:8px 16px;">承認する</button>
        </form>

        <div style="margin-top:16px;">
            <a href="{{ route('admin.request.index') }}">← 一覧に戻る</a>
        </div>
    </div>
@endsection
