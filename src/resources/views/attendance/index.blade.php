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
                        if ($r['rec']) {
                            $bh = intdiv($r['break_min'], 60);
                            $bm = $r['break_min'] % 60;
                            $wh = intdiv($r['work_min'], 60);
                            $wm = $r['work_min'] % 60;
                        } else {
                            $bh = $bm = $wh = $wm = null;
                        }
                    @endphp
                    <tr>
                        <td>{{ $r['day']->isoFormat('MM/DD(ddd)') }}</td>
                        <td>{{ $r['rec']?->clock_in?->format('H:i') ?? '' }}</td>
                        <td>{{ $r['rec']?->clock_out?->format('H:i') ?? '' }}</td>
                        <td>{{ $bh !== null ? sprintf('%d:%02d', $bh, $bm) : '' }}</td>
                        <td>{{ $wh !== null ? sprintf('%d:%02d', $wh, $wm) : '' }}</td>
                        <td>
                            <a href="{{ route('user.attendance.show', ['date' => $r['day']->toDateString()]) }}">詳細</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
