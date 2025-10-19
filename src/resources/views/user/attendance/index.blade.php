@extends('layouts.app')

@section('content')
    <div class="page">
        <h1>勤怠一覧</h1>

        <div class="nav">
            <a href="{{ route('user.attendance.index', ['month' => $prevMonth]) }}">← 前月</a>
            <div>{{ $cursor->format('Y/m') }}</div>
            @if ($nextDisabled)
                <span>翌月 →</span>
            @else
                <a href="{{ route('user.attendance.index', ['month' => $nextMonth]) }}">翌月 →</a>
            @endif
        </div>

        <table style="">
            <thead>
                <tr style="">
                    <th>日付</th>
                    <th>出勤</th>
                    <th>退勤</th>
                    <th>休憩</th>
                    <th>合計</th>
                    <th>詳細</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $r)
                    @php
                        // 表示用フラグ（既存ロジック踏襲）
                        $showBreak = $r['rec'] && $r['break_min'] > 0;
                        $showWork = $r['rec'] && $r['rec']->clock_out !== null;
                    @endphp
                    <tr>
                        <td>{{ $r['day']->isoFormat('MM/DD(ddd)') }}</td>
                        <td>{{ $r['rec']?->clock_in?->format('H:i') ?? '' }}</td>
                        <td>{{ $r['rec']?->clock_out?->format('H:i') ?? '' }}</td>
                        <td>{{ $r['break_str'] ?? '' }}</td>
                        <td>{{ $r['work_str'] ?? '' }}</td>
                        <td>
                            @if ($r['rec'])
                                {{-- 勤務あり：ID で詳細へ --}}
                                <a
                                    href="{{ route('user.attendance.show', [
                                        'attendance' => $r['rec']->id,
                                    ]) }}">詳細</a>
                            @else
                                {{-- 勤務なし：日付入口（入った瞬間に空レコード発行→IDにリダイレクト） --}}
                                <a
                                    href="{{ route('user.attendance.show.by_date', [
                                        'date' => $r['day']->toDateString(),
                                    ]) }}">詳細</a>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
