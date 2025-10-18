@extends('layouts.app')

@section('css')
    <link rel="stylesheet" href="{{ asset('css/mypage/index.css') }}">
@endsection

@section('content')
    <h1>勤怠詳細（管理者）</h1>

    {{-- 対象日 --}}
    <p>{{ $date->format('Y年 n月 j日') }}</p>

    <form action="#" method="POST">
        @csrf

        {{-- 名前 --}}
        <div>
            <label>名前</label>
            <div>{{ $attendance?->user?->name ?? '—' }}</div>
        </div>

        {{-- 出勤・退勤 --}}
        <div>
            <label>出勤・退勤</label>
            <div>
                <input type="time" name="clock_in" value="{{ $attendance?->clock_in?->format('H:i') ?? '' }}">
                〜
                <input type="time" name="clock_out" value="{{ $attendance?->clock_out?->format('H:i') ?? '' }}">
            </div>
        </div>

        {{-- 休憩（既存分＋空1行） --}}
        @php $nextBreakIndex = $breaks->count(); @endphp

        @foreach ($breaks as $index => $break)
            <div>
                <label>休憩{{ $index + 1 }}</label>
                <div>
                    <input type="time" name="breaks[{{ $index }}][start]"
                        value="{{ $break->break_start?->format('H:i') ?? '' }}">
                    〜
                    <input type="time" name="breaks[{{ $index }}][end]"
                        value="{{ $break->break_end?->format('H:i') ?? '' }}">
                </div>
            </div>
        @endforeach

        {{-- 空行 --}}
        <div>
            <label>休憩{{ $nextBreakIndex + 1 }}</label>
            <div>
                <input type="time" name="breaks[{{ $nextBreakIndex }}][start]" value="">
                〜
                <input type="time" name="breaks[{{ $nextBreakIndex }}][end]" value="">
            </div>
        </div>

        {{-- 備考 --}}
        <div>
            <label>備考</label>
            <textarea name="note" rows="3">{{ $attendance?->note ?? '' }}</textarea>
        </div>

        {{-- 修正ボタン --}}
        <div class="form-button">
            <button type="submit">修正</button>
        </div>
    </form>
@endsection
