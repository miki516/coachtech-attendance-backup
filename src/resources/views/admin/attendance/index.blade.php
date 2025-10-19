@extends('layouts.app')

@section('content')
    <h2>{{ $date->format('Y年n月j日') }}の勤怠</h2>

    <div style="display:flex;justify-content:center;align-items:center;margin-bottom:16px;">
        <a href="{{ route('admin.attendance.index', ['date' => $prevDate]) }}">← 前日</a>
        <span style="margin:0 12px;">{{ $date->format('Y/m/d') }}</span>
        <a href="{{ route('admin.attendance.index', ['date' => $nextDate]) }}">翌日 →</a>
    </div>

    <table border="1" cellspacing="0" cellpadding="8" style="width:100%;text-align:center;">
        <thead style="background:#f2f2f2;">
            <tr>
                <th>名前</th>
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
                    <td>{{ $r['name'] }}</td>
                    <td>{{ $r['clock_in'] }}</td>
                    <td>{{ $r['clock_out'] }}</td>
                    <td>{{ $r['break'] }}</td>
                    <td>{{ $r['total'] }}</td>
                    <td>
                        @if ($r['attendance_id'])
                            <a
                                href="{{ route('admin.attendance.show', [
                                    'attendance' => $r['attendance_id'],
                                    'return' => 'list',
                                    'date' => $date->toDateString(),
                                ]) }}">詳細</a>
                        @else
                            -
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
