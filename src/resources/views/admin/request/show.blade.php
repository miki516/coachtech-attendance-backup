@extends('layouts.app')

@section('css')
    <link rel="stylesheet" href="{{ asset('css/mypage/index.css') }}">
@endsection

@section('content')
    <h1>修正申請詳細（管理者）</h1>

    @if (session('status'))
        <div class="form-success">{{ session('status') }}</div>
    @endif

    {{-- 対象日 --}}
    <p>{{ $date->format('Y年 n月 j日') }}</p>

    {{-- 名前 --}}
    <div>
        <label>名前</label>
        <div>{{ $requestRec->user?->name ?? '—' }}</div>
    </div>

    {{-- 出勤・退勤 --}}
    <div>
        <label>出勤・退勤</label>
        <div>
            <input type="time" value="{{ $requestRec->clock_in_time?->format('H:i') ?? '' }}" disabled>
            〜
            <input type="time" value="{{ $requestRec->clock_out_time?->format('H:i') ?? '' }}" disabled>
        </div>
    </div>

    {{-- 休憩（1行余分に出ないよう修正） --}}
    @php
        $validBreaks = collect($requestRec->break_times ?? [])->filter(fn($b) => $b['start'] || $b['end']);
    @endphp

    @forelse ($validBreaks as $index => $b)
        <div>
            <label>休憩{{ $index + 1 }}</label>
            <div>
                <input type="time" value="{{ $b['start']?->format('H:i') ?? '' }}" disabled>
                〜
                <input type="time" value="{{ $b['end']?->format('H:i') ?? '' }}" disabled>
            </div>
        </div>
    @empty
        <div>
            <label>休憩</label>
            <div>—</div>
        </div>
    @endforelse

    {{-- 理由 --}}
    <div>
        <label>理由</label>
        <textarea rows="3" disabled>{{ $requestRec->reason ?? '' }}</textarea>
    </div>

    {{-- 承認ボタン --}}
    @if ($requestRec->status === 'pending')
        <div class="form-button">
            <form method="POST" action="{{ route('admin.request.approve', $requestRec->id) }}">
                @csrf
                <button type="submit">承認する</button>
            </form>
        </div>
    @else
        <div class="form-button">
            <span style="padding:6px 10px; background:#eef; border:1px solid #99f; border-radius:4px;">
                承認済み
            </span>
        </div>
    @endif

    {{-- 戻るリンク --}}
    <div style="margin-top:16px;">
        <a href="{{ route('admin.request.index') }}">← 一覧に戻る</a>
    </div>
@endsection
