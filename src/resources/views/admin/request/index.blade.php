@extends('layouts.app')

@section('content')
    <div class="page" style="max-width: 900px; margin: 0 auto;">
        <h1>修正申請一覧</h1>

        {{-- タブ切り替え --}}
        <div style="display: flex; gap: 16px; margin-bottom: 16px;">
            <button onclick="showTab('pending')" class="tab-btn">承認待ち</button>
            <button onclick="showTab('approved')" class="tab-btn">承認済み</button>
        </div>

        {{-- 承認待ち --}}
        <div id="tab-pending" class="tab-content">
            <h2 style="font-size: 1.1em;">承認待ち</h2>
            <table border="1" cellpadding="8" cellspacing="0" style="width:100%; text-align:center;">
                <thead style="background:#f2f2f2;">
                    <tr>
                        <th>申請者</th>
                        <th>対象日</th>
                        <th>申請内容</th>
                        <th>申請日時</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($pending as $r)
                        <tr>
                            <td>{{ $r->user?->name }}</td>
                            <td>{{ $r->display_date?->format('Y/m/d') ?? '—' }}</td>
                            <td>{{ $r->reason }}</td>
                            <td>{{ $r->created_at->format('Y/m/d H:i') }}</td>
                            <td><a href="{{ route('admin.request.show', $r->id) }}">詳細</a></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">承認待ちはありません。</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- 承認済み --}}
        <div id="tab-approved" class="tab-content" style="display:none;">
            <h2 style="font-size: 1.1em;">承認済み</h2>
            <table border="1" cellpadding="8" cellspacing="0" style="width:100%; text-align:center;">
                <thead style="background:#f2f2f2;">
                    <tr>
                        <th>申請者</th>
                        <th>対象日</th>
                        <th>備考</th>
                        <th>承認日時</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($approved as $r)
                        <tr>
                            <td>{{ $r->user?->name }}</td>
                            <td>{{ $r->display_date?->format('Y/m/d') ?? '—' }}</td>
                            <td>{{ $r->reason }}</td>
                            <td>{{ $r->approved_at?->format('Y/m/d H:i') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">承認済みの申請はありません。</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <script>
        function showTab(tab) {
            document.querySelectorAll('.tab-content').forEach(c => c.style.display = 'none');
            document.getElementById('tab-' + tab).style.display = 'block';
        }
    </script>
@endsection
