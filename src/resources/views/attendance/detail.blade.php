@extends('layouts.app')

@section('css')
    <link rel="stylesheet" href="{{ asset('css/mypage/index.css') }}">
@endsection

@section('content')
    <h1>勤怠詳細</h1>

    <form action="{{ route('user.request.store') }}" method="POST">
        @csrf

        {{-- 対象特定 --}}
        <input type="hidden" name="date" value="{{ $date->toDateString() }}">
        <input type="hidden" name="attendance_id" value="{{ $attendance?->id }}">

        {{-- 名前 --}}
        <div>
            <label>名前</label>
            <div>{{ $attendance?->user?->name ?? '—' }}</div>
        </div>

        {{-- 日付 --}}
        <div>
            <label>日付</label>
            <div>{{ $date->format('Y年 n月 j日') }}</div>
        </div>

        {{-- 出勤・退勤 --}}
        <div>
            <label>出勤・退勤</label>
            <div>
                <input type="time" name="clock_in" value="{{ $attendance?->clock_in?->format('H:i') ?? '' }}"
                    @if ($isPending) disabled @endif>
                〜
                <input type="time" name="clock_out" value="{{ $attendance?->clock_out?->format('H:i') ?? '' }}"
                    @if ($isPending) disabled @endif>
            </div>
            @error('clock_in')
                <div class="form-error">{{ $message }}</div>
            @enderror
            @error('clock_out')
                <div class="form-error">{{ $message }}</div>
            @enderror
        </div>

        {{-- 休憩（既存回数分＋空1行） --}}
        @php $nextBreakIndex = $breaks->count(); @endphp

        @foreach ($breaks as $index => $break)
            <div>
                <label>休憩{{ $index + 1 }}</label>
                <div>
                    <input type="time" name="breaks[{{ $index }}][start]"
                        value="{{ $break->break_start?->format('H:i') ?? '' }}"
                        @if ($isPending) disabled @endif>
                    〜
                    <input type="time" name="breaks[{{ $index }}][end]"
                        value="{{ $break->break_end?->format('H:i') ?? '' }}"
                        @if ($isPending) disabled @endif>
                </div>
            </div>
        @endforeach

        {{-- 空行 --}}
        <div>
            <label>休憩{{ $nextBreakIndex + 1 }}</label>
            <div>
                <input type="time" name="breaks[{{ $nextBreakIndex }}][start]" value=""
                    @if ($isPending) disabled @endif>
                〜
                <input type="time" name="breaks[{{ $nextBreakIndex }}][end]" value=""
                    @if ($isPending) disabled @endif>
            </div>
        </div>

        @error('breaks.*.start')
            <div class="form-error">{{ $message }}</div>
        @enderror
        @error('breaks.*.end')
            <div class="form-error">{{ $message }}</div>
        @enderror

        {{-- 備考 --}}
        <div>
            <label>備考</label>
            <textarea name="note" rows="3" @if ($isPending) disabled @endif>{{ old('note') }}</textarea>
            @error('note')
                <div class="form-error">{{ $message }}</div>
            @enderror
        </div>

        {{-- 修正ボタン or メッセージ --}}
        <div class="form-button">
            @if ($isPending)
                <div class="form-error" role="alert">承認待ちのため修正はできません。</div>
            @else
                <button type="submit">修正</button>
            @endif
        </div>
    </form>
@endsection
