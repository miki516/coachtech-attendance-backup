@extends('layouts.app')

@section('content')
    <div class="page">
        <h2>申請一覧</h2>

        {{-- タブ切り替え --}}
        <div class="tab-area">
            <button class="tab tab--active" data-target="pending">承認待ち</button>
            <button class="tab" data-target="approved">承認済み</button>
        </div>

        {{-- 承認待ち --}}
        <div class="tab-content tab-content--active" id="pending">
            <table class="request-table">
                <thead>
                    <tr>
                        <th>状態</th>
                        <th>名前</th>
                        <th>対象日時</th>
                        <th>申請理由</th>
                        <th>申請日時</th>
                        <th>詳細</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($pending as $req)
                        <tr>
                            <td><span class="status status--pending">承認待ち</span></td>
                            <td>{{ $req->user->name }}</td>
                            <td>{{ $req->display_date?->format('Y/m/d') ?? '-' }}</td>
                            <td>{{ $req->reason }}</td>
                            <td>{{ $req->created_at->format('Y/m/d') }}</td>
                            <td>
                                @if ($req->attendance_id)
                                    {{-- 勤怠IDがある：ID版詳細へ --}}
                                    <a
                                        href="{{ route('user.attendance.show', ['attendance' => $req->attendance_id]) }}">詳細</a>
                                @elseif ($req->display_date)
                                    {{-- 勤怠IDがない：日付入口から（空レコード作成→IDへリダイレクト） --}}
                                    <a
                                        href="{{ route('user.attendance.show.by_date', ['date' => $req->display_date->toDateString()]) }}">詳細</a>
                                @else
                                    <span style="opacity:.6;">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">承認待ちはありません。</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- 承認済み --}}
        <div class="tab-content" id="approved">
            <table class="request-table">
                <thead>
                    <tr>
                        <th>状態</th>
                        <th>名前</th>
                        <th>対象日時</th>
                        <th>申請理由</th>
                        <th>申請日時</th>
                        <th>詳細</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($approved as $req)
                        <tr>
                            <td><span class="status status--approved">承認済み</span></td>
                            <td>{{ $req->user->name }}</td>
                            <td>{{ $req->display_date?->format('Y/m/d') ?? '-' }}</td>
                            <td>{{ $req->reason }}</td>
                            <td>{{ $req->created_at->format('Y/m/d') }}</td>
                            <td>
                                @if ($req->attendance_id)
                                    <a
                                        href="{{ route('user.attendance.show', ['attendance' => $req->attendance_id]) }}">詳細</a>
                                @elseif ($req->display_date)
                                    <a
                                        href="{{ route('user.attendance.show.by_date', ['date' => $req->display_date->toDateString()]) }}">詳細</a>
                                @else
                                    <span style="opacity:.6;">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">承認済みはありません。</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const tabs = document.querySelectorAll('.tab');
            const contents = document.querySelectorAll('.tab-content');

            tabs.forEach(tab => {
                tab.addEventListener('click', () => {
                    tabs.forEach(t => t.classList.remove('tab--active'));
                    contents.forEach(c => c.classList.remove('tab-content--active'));

                    tab.classList.add('tab--active');
                    document.getElementById(tab.dataset.target).classList.add(
                    'tab-content--active');
                });
            });
        });
    </script>
@endpush
