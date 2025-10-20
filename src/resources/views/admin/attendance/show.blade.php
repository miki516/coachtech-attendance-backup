@extends('layouts.app')

@section('css')
    <link rel="stylesheet" href="{{ asset('css/mypage/index.css') }}">
@endsection

@section('content')
    <h1>勤怠詳細（管理者）</h1>

    @if (session('status'))
        <div class="form-success">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('admin.attendance.update', ['attendance' => $attendance->id]) }}">
        @csrf
        @method('PATCH')

        {{-- どこから来たかを持ち回す --}}
        <input type="hidden" name="return_to" value="{{ request('return') }}">
        <input type="hidden" name="context_date" value="{{ request('date') }}">
        <input type="hidden" name="context_staff" value="{{ request('staff') }}">
        <input type="hidden" name="context_month" value="{{ request('month') }}">

        {{-- 対象日（更新の基準日として使用） --}}
        <p>{{ $date->format('Y年 n月 j日') }}</p>
        <input type="hidden" name="date" value="{{ $date->toDateString() }}">

        {{-- 名前（表示のみ） --}}
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
            @error('clock_in')
                <div class="form-error">{{ $message }}</div>
            @enderror
            @error('clock_out')
                <div class="form-error">{{ $message }}</div>
            @enderror
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
                @error("breaks.$index.start")
                    <div class="form-error">{{ $message }}</div>
                @enderror
                @error("breaks.$index.end")
                    <div class="form-error">{{ $message }}</div>
                @enderror
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
            @error("breaks.$nextBreakIndex.start")
                <div class="form-error">{{ $message }}</div>
            @enderror
            @error("breaks.$nextBreakIndex.end")
                <div class="form-error">{{ $message }}</div>
            @enderror
        </div>

        {{-- 備考 --}}
        <div>
            <label>備考</label>
            <textarea name="note" rows="3">{{ old('note', $attendance?->note ?? '') }}</textarea>
            @error('note')
                <div class="form-error">{{ $message }}</div>
            @enderror
        </div>

        {{-- 修正ボタン --}}
        <div class="form-button">
            @if ($isPending)
                <div class="form-error" role="alert">承認待ちのため修正はできません。</div>
            @else
                <button type="submit">修正</button>
            @endif
        </div>
    </form>
@endsection
