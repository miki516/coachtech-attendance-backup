@extends('layouts.app')

@section('content')
    <div class="page">
        <h1>{{ $staff->name }} さんの勤怠</h1>

        <div class="nav" style="display:flex;gap:12px;align-items:center;margin-bottom:12px;">
            <a href="{{ route('admin.staff.show', ['staff' => $staff->id, 'month' => $prevMonth]) }}">← 前月</a>
            <div>{{ $cursor->format('Y/m') }}</div>
            @if ($nextDisabled)
                <span>翌月 →</span>
            @else
                <a href="{{ route('admin.staff.show', ['staff' => $staff->id, 'month' => $nextMonth]) }}">翌月 →</a>
            @endif
        </div>

        <table style="width:100%;text-align:center;">
            <thead>
                <tr>
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
                    <tr>
                        <td>{{ $r['day']->isoFormat('MM/DD(ddd)') }}</td>
                        <td>{{ $r['rec']?->clock_in?->format('H:i') ?? '' }}</td>
                        <td>{{ $r['rec']?->clock_out?->format('H:i') ?? '' }}</td>
                        <td>{{ $r['break_str'] ?? '' }}</td>
                        <td>{{ $r['work_str'] ?? '' }}</td>
                        <td>
                            @if ($r['rec'])
                                {{-- 勤務あり：IDで管理者詳細へ --}}
                                <a href="{{ route('admin.attendance.show', ['attendance' => $r['rec']->id]) }}">詳細</a>
                            @else
                                {{-- 勤務なし：管理者用 by-date 入口（空レコード作成→ID詳細へ） --}}
                                <a
                                    href="{{ route('admin.attendance.show.by_date', [
                                        'staff' => $staff->id,
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
