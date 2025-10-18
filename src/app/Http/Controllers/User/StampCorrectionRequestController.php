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

        // 勤怠データの取得
        $attendance = Attendance::where('user_id', $user->id)
            ->find($validated['attendance_id']);

        if (!$attendance) {
            abort(404, '勤怠データが見つかりません');
        }

        // 時刻（例：08:30）をDateTime形式に変換
        $convertToDateTime = function (?string $timeString, Carbon $baseDate) {
            if (!$timeString) return null;
            [$hour, $minute] = explode(':', $timeString);
            return $baseDate->copy()->setTime((int)$hour, (int)$minute)->toDateTimeString();
        };

        $baseDate = Carbon::parse($attendance->clock_in ?? now());

        // 休憩時間の変換
        $formattedBreaks = [];
        foreach ($validated['breaks'] ?? [] as $break) {
            $formattedBreaks[] = [
                'start' => $convertToDateTime($break['start'] ?? null, $baseDate),
                'end'   => $convertToDateTime($break['end'] ?? null, $baseDate),
            ];
        }

        // 修正申請を登録
        StampCorrectionRequest::create([
            'user_id'             => $user->id,
            'attendance_id'       => $attendance->id,
            'requested_clock_in'  => $convertToDateTime($validated['clock_in'] ?? null, $baseDate),
            'requested_clock_out' => $convertToDateTime($validated['clock_out'] ?? null, $baseDate),
            'reason'              => $validated['note'], // フォームの「備考」欄
            'status'              => 'pending', // 初期状態：承認待ち
        ]);

        return redirect()->route('request.list')
            ->with('status', '修正申請を送信しました（承認待ち）');
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
            ->map(function ($requestRecord) {
                // 勤怠データの日付を取得
                $targetDate = $requestRecord->attendance?->clock_in?->format('Y-m-d');

                // 表示用の仮プロパティを追加
                $requestRecord->target_date = $targetDate;

                return $requestRecord;
            });

        // 状態ごとに分類
        $pendingRequests  = $allRequests->where('status', 'pending');   // 承認待ち
        $approvedRequests = $allRequests->where('status', 'approved');  // 承認済み
        $rejectedRequests = $allRequests->where('status', 'rejected');  // 却下

        return view('stamp_correction_request.index', [
            'requests' => $allRequests,
            'pending'  => $pendingRequests,
            'approved' => $approvedRequests,
            'rejected' => $rejectedRequests,
        ]);
    }
}
