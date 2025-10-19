<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StampCorrectionRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class StampCorrectionRequestController extends Controller
{
    // ============================
    // 承認一覧画面
    // ============================
    public function index(Request $request)
    {
        // 全申請を取得（勤怠データ・ユーザーも読み込む）
        $allRequests = StampCorrectionRequest::with(['attendance', 'user'])
            ->latest('created_at')
            ->get()
            ->map(function ($r) {
                // 表示用の日付を整形
                $source = $r->target_date ?: ($r->attendance?->clock_in);
                $r->display_date = $source ? Carbon::parse($source) : null;
                return $r;
            });

        // 承認待ち／承認済みを分類
        $pending  = $allRequests->where('status', 'pending');
        $approved = $allRequests->where('status', 'approved');

        // ※管理者自身の修正は一覧に含めない場合
        $approved = $approved->filter(fn($r) => !$r->approved_by);

        return view('admin.request.index', compact('pending', 'approved'));
    }

    // 詳細表示
    public function show($attendance_correct_request_id)
    {
        $requestRec = StampCorrectionRequest::with(['attendance', 'user'])
            ->findOrFail($attendance_correct_request_id);

        // ここでCarbonインスタンスに変換しておく
        $requestRec->clock_in_time  = $requestRec->requested_clock_in
            ? Carbon::parse($requestRec->requested_clock_in)
            : null;

        $requestRec->clock_out_time = $requestRec->requested_clock_out
            ? Carbon::parse($requestRec->requested_clock_out)
            : null;

        // 休憩配列もCarbon化
        $requestRec->break_times = collect($requestRec->requested_breaks ?? [])->map(function ($b) {
            return [
                'start' => $b['start'] ? Carbon::parse($b['start']) : null,
                'end'   => $b['end']   ? Carbon::parse($b['end'])   : null,
            ];
        });

        $date = $requestRec->target_date
            ? Carbon::parse($requestRec->target_date)
            : ($requestRec->attendance?->clock_in?->copy()->startOfDay() ?? now()->startOfDay());

        return view('admin.request.show', compact('requestRec', 'date'));
    }

    // 承認処理
    public function approve(Request $request, $attendance_correct_request_id)
    {
        $rec = StampCorrectionRequest::findOrFail($attendance_correct_request_id);

        // 承認済みに更新
        $rec->update([
            'status'       => 'approved',
            'approved_by'  => Auth::id(),
            'approved_at'  => now(),
        ]);

        return redirect()->route('admin.request.index')
            ->with('status', '申請を承認しました');
    }
}
