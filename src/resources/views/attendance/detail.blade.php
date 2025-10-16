@extends('layouts.app')

@section('css')
    <link rel="stylesheet" href="{{ asset('css/mypage/index.css') }}">
@endsection

@section('content')
    <h1>勤怠詳細</h1>
    <form action="{{ route('stamp_correction_request') }}" method="POST">
        @csrf
        <div>
            <label>名前</label>
            <div>{{ $attendance?->user?->name ?? '—' }}</div>
        </div>

        <div>
            <label>日付</label>
            <div>{{ $date->format('Y年 n月 j日') }}</div>
        </div>

        <div>
            <label>出勤・退勤</label>
            <div>
                <input type="time" name="clock_in" value="{{ $attendance?->clock_in?->format('H:i') ?? '' }}">
                〜
                <input type="time" name="clock_out" value="{{ $attendance?->clock_out?->format('H:i') ?? '' }}">
            </div>
        </div>

        {{-- 休憩があれば表示（0件なら非表示） --}}
        @foreach ($breaks as $i => $br)
            <div>
                <label>休憩{{ $i + 1 }}</label>
                <div>
                    <input type="time" name="breaks[{{ $i }}][start]"
                        value="{{ $br->break_start?->format('H:i') ?? '' }}">
                    〜
                    <input type="time" name="breaks[{{ $i }}][end]"
                        value="{{ $br->break_end?->format('H:i') ?? '' }}">
                </div>
            </div>
        @endforeach
        <div class="form-button">
            <button class="" type="submit">修正</button>
        </div>
    </form>
@endsection
