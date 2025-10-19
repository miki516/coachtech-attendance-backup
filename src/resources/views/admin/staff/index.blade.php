@extends('layouts.app')

@section('content')
    <div class="page">
        <h1>スタッフ一覧</h1>

        <table class="table" style="width:100%; text-align:center;">
            <thead>
                <tr>
                    <th>名前</th>
                    <th>メールアドレス</th>
                    <th>月次勤怠</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($staff as $s)
                    <tr>
                        <td>{{ $s->name }}</td>
                        <td>{{ $s->email }}</td>
                        <td>
                            <a
                                href="{{ route('admin.staff.show', [
                                    'staff' => $s->id,
                                    'month' => now()->format('Y-m'),
                                ]) }}">詳細</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3">該当するスタッフはいません。</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
