@extends('layouts.app')

@section('css')
    <link rel="stylesheet" href="{{ asset('css/mypage/index.css') }}">
@endsection

@section('content')
    <div class="now-wrap">
        {{-- 日付 --}}
        <div id="now-date">
            {{ $now->locale('ja')->isoFormat('YYYY年M月D日（ddd）') }}
        </div>

        {{-- 時刻（リアルタイム更新） --}}
        <div id="now-time">
            {{ $now->format('H:i') }}
        </div>

        <span id="now-start" data-start="{{ $now->toIso8601String() }}" hidden></span>
    </div>

    {{-- 出勤ボタン（勤務外 or 翌日になって出勤可能なとき） --}}
    @if ($canClockIn)
        <form method="POST" action="{{ route('user.attendance.clockin') }}">
            @csrf
            <button type="submit">出勤</button>
        </form>
    @elseif ($status === '退勤済')
        {{-- 当日は出勤できない --}}
        <p>お疲れ様でした。</p>
    @endif

    {{-- 退勤ボタン --}}
    @if ($canClockOut)
        <form method="POST" action="{{ route('user.attendance.clockout', $open->id) }}">
            @csrf
            <button type="submit">退勤</button>
        </form>
    @endif

    {{-- 休憩入 --}}
    @if ($canBreakIn)
        <form method="POST" action="{{ route('user.attendance.break.in', $open->id) }}">
            @csrf
            <button type="submit">休憩入</button>
        </form>
    @endif

    {{-- 休憩戻 --}}
    @if ($canBreakOut)
        <form method="POST" action="{{ route('user.attendance.break.out', $open->id) }}">
            @csrf
            <button type="submit">休憩戻</button>
        </form>
    @endif
@endsection

@push('scripts')
    <script>
        (() => {
            const startEl = document.getElementById('now-start');
            const dateEl = document.getElementById('now-date');
            const timeEl = document.getElementById('now-time');
            if (!startEl || !timeEl) return;

            const serverStart = new Date(startEl.dataset.start).getTime();
            const offset = Date.now() - serverStart;

            const wdays = ['日', '月', '火', '水', '木', '金', '土'];
            const pad2 = n => String(n).padStart(2, '0');

            function render() {
                const t = new Date(Date.now() - offset);
                const y = t.getFullYear();
                const m = t.getMonth() + 1;
                const d = t.getDate();
                const w = wdays[t.getDay()];
                const hh = pad2(t.getHours());
                const mm = pad2(t.getMinutes());

                // 日付は1分ごとに更新
                dateEl.textContent = `${y}年${m}月${d}日（${w}）`;
                timeEl.textContent = `${hh}:${mm}`;
            }

            render();
            setInterval(render, 1000);
        })();
    </script>
@endpush
