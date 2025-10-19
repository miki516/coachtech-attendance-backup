<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStampCorrectionRequest;
use App\Models\Attendance;
use App\Models\StampCorrectionRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class StampCorrectionRequestController extends Controller
{
    // 修正申請を保存
    public function store(StoreStampCorrectionRequest $request)
    {
        $validated = $request->validated(); // バリデーション済みデータ
        $user = Auth::user();

        // 勤怠データ（あれば取得）
        $attendance = null;
        if (!empty($validated['attendance_id'])) {
            $attendance = Attendance::where('user_id', $user->id)
            ->where('id', $validated['attendance_id'])
            ->first();
        }

        // 時刻→DateTime文字列
        $toDateTime = function (?string $time, Carbon $baseDate) {
            if (!$time) return null;
            [$h, $m] = explode(':', $time);
            return $baseDate->copy()->setTime((int)$h, (int)$m)->toDateTimeString();
        };

        $baseDate = Carbon::parse($validated['date']);

        // 休憩の申請値をJSON用に整形
        $requestedBreaks = [];
        foreach ($validated['breaks'] ?? [] as $b) {
            $requestedBreaks[] = [
                'start' => $toDateTime($b['start'] ?? null, $baseDate),
                'end'   => $toDateTime($b['end']   ?? null, $baseDate),
            ];
        }

        // 修正申請を登録
        StampCorrectionRequest::create([
            'user_id'             => $user->id,
            'attendance_id'       => $attendance?->id,
            'target_date'         => $baseDate->toDateString(),
            'requested_clock_in'  => $toDateTime($validated['clock_in'] ?? null, $baseDate),
            'requested_clock_out' => $toDateTime($validated['clock_out'] ?? null, $baseDate),
            'requested_breaks'    => $requestedBreaks,
            'reason'              => $validated['note'], // フォームの「備考」欄
            'status'              => 'pending', // 初期状態：承認待ち
        ]);

        return redirect()->route('request.list');
    }

    // 申請一覧
    public function index(Request $request)
    {
        // ログイン中のユーザーを取得
        $user = Auth::user();

        // ユーザー自身の修正申請を取得し、勤怠データも一緒に読み込む
        $allRequests = StampCorrectionRequest::with('attendance')
            ->where('user_id', $user->id)
            ->latest('created_at')
            ->get()
            ->map(function ($r) {
                // 対象日を取得
                $source = $r->target_date
                    ?: ($r->attendance?->clock_in);   // 勤怠に紐づく場合の保険

                $date = $source ? Carbon::parse($source) : null;

                // 画面表示用（Carbon）
                $r->display_date = $date;

                // リンク用（YYYY-MM-DD 文字列）
                $r->link_date = $date?->format('Y-m-d');

                return $r;
            });

        // 状態ごとに分類
        $pendingRequests  = $allRequests->where('status', 'pending');   // 承認待ち
        $approvedRequests = $allRequests->where('status', 'approved');  // 承認済み
        $rejectedRequests = $allRequests->where('status', 'rejected');  // 却下

        return view('user.stamp_correction_request.index', [
            'requests' => $allRequests,
            'pending'  => $pendingRequests,
            'approved' => $approvedRequests,
            'rejected' => $rejectedRequests,
        ]);
    }
}
