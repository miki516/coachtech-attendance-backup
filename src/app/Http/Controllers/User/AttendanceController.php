<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\BreakTime;
use App\Models\StampCorrectionRequest;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class AttendanceController extends Controller
{
    // 打刻画面表示
    public function showPunch()
    {
        $user = Auth::user();
        $now  = Carbon::now();
        $today = $now->toDateString();

        // 未退勤レコード（出勤中）
        $open = Attendance::where('user_id', $user->id)
            ->whereNull('clock_out')
            ->latest('clock_in')
            ->first();

        // その日のレコードが既にあるか（work_date基準）
        $todayExists = Attendance::where('user_id', $user->id)
            ->whereDate('work_date', $today)
            ->exists();

        // 休憩中
        $break = null;
        if ($open) {
            $break = BreakTime::where('attendance_id', $open->id)
                ->whereNull('break_end')
                ->latest('break_start')
                ->first();
        }

        // ラベル
        $status = '勤務外';
        if     ($break)       $status = '休憩中';
        elseif ($open)        $status = '出勤中';
        elseif ($todayExists) $status = '退勤済';

        // Bladeフラグ
        $canClockIn  = !$open && !$todayExists;
        $canClockOut = (bool) $open;
        $canBreakIn  = (bool) $open && !$break;
        $canBreakOut = (bool) $break;

        return view('user.attendance.punch', compact(
            'now', 'status', 'open', 'canClockIn', 'canClockOut', 'canBreakIn', 'canBreakOut'
        ));
    }

    // 出勤
    public function storePunch()
    {
        $user  = Auth::user();
        $today = now()->toDateString();

        // すでに出勤中？
        $alreadyOpen = Attendance::where('user_id', $user->id)
            ->whereNull('clock_out')
            ->exists();
        if ($alreadyOpen) {
            return back()->with('error', 'すでに出勤中です');
        }

        // その日のレコードがある？
        $todayExists = Attendance::where('user_id', $user->id)
            ->whereDate('work_date', $today)
            ->exists();
        if ($todayExists) {
            return back();
        }

        Attendance::create([
            'user_id'   => $user->id,
            'work_date' => $today,     // ★ 未勤務日でも一意に持つキー
            'clock_in'  => now(),
            'clock_out' => null,
        ]);

        return redirect()->route('user.attendance.punch');
    }

    // 退勤
    public function clockOut(Attendance $attendance)
    {
        if ($attendance->user_id !== Auth::id()) {
            abort(403);
        }

        $attendance->update([
            'clock_out' => now(),
        ]);

        return back();
    }

    // 勤怠一覧
    public function index(Request $request)
    {
        $user = Auth::user();

        // 受け取り：YYYY-MM（無ければ今月）
        $monthStr = $request->query('month');
        $cursor   = $monthStr
            ? Carbon::createFromFormat('Y-m-d', "{$monthStr}-01")
            : now();

        $start = $cursor->copy()->startOfMonth();
        $end   = $cursor->copy()->endOfMonth();

        // 当月の自分の勤怠を work_date で取得
        $byDate = Attendance::with('breakTimes')
            ->where('user_id', $user->id)
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('work_date')
            ->get()
            ->keyBy(fn($a) => $a->work_date->toDateString());

        // 月内の全日を行データに整形
        $rows = [];
        foreach (CarbonPeriod::create($start, $end) as $day) {
            $key = $day->toDateString();
            $rec = $byDate[$key] ?? null;

            // 休憩合計（start/end 両方あるもののみ）
            $breakMin = 0;
            if ($rec && $rec->relationLoaded('breakTimes')) {
                $breakMin = $rec->breakTimes->sum(function ($b) {
                    return ($b->break_start && $b->break_end)
                        ? $b->break_end->diffInMinutes($b->break_start)
                        : 0;
                });
            }

            // 勤務合計（退勤があるときのみ）
            $workMin = ($rec && $rec->clock_in && $rec->clock_out)
                ? max($rec->clock_out->diffInMinutes($rec->clock_in) - $breakMin, 0)
                : 0;

            // 表示用
            $breakStr = $breakMin > 0
                ? sprintf('%d:%02d', intdiv($breakMin, 60), $breakMin % 60)
                : null;
            $workStr  = ($rec && $rec->clock_out)
                ? sprintf('%d:%02d', intdiv($workMin, 60), $workMin % 60)
                : null;

            $rows[] = [
                'day'        => $day->copy(),
                'rec'        => $rec,
                'break_min'  => $breakMin,
                'work_min'   => $workMin,
                'break_str'  => $breakStr,
                'work_str'   => $workStr,
            ];
        }

        // 前月・翌月
        $prev = $start->copy()->subMonth()->format('Y-m');
        $next = $end->copy()->addMonth()->format('Y-m');

        // 今月表示中なら翌月リンク無効
        $nextDisabled = $cursor->isSameMonth(now());

        return view('user.attendance.index', [
            'rows'         => $rows,
            'cursor'       => $cursor,
            'prevMonth'    => $prev,
            'nextMonth'    => $next,
            'nextDisabled' => $nextDisabled,
        ]);
    }

    // 勤怠詳細（ID受け取り）
    public function show(Attendance $attendance)
    {
        if ($attendance->user_id !== Auth::id()) {
            abort(403);
        }

        // 表示用“日付”は work_date 優先、無ければ clock_in 由来
        $day = $attendance->work_date
            ? $attendance->work_date->copy()->startOfDay()
            : ($attendance->clock_in?->copy()->startOfDay());

        if (!$day) {
            // 念のための保険
            $day = now()->startOfDay();
        }

        // 申請（attendance_id を優先、無ければ target_date で補完）
        $requestRec = StampCorrectionRequest::where('user_id', Auth::id())
            ->where('attendance_id', $attendance->id)
            ->latest('created_at')
            ->first();

        if (!$requestRec) {
            $requestRec = StampCorrectionRequest::where('user_id', Auth::id())
                ->whereDate('target_date', $day)
                ->latest('created_at')
                ->first();
        }

        $isPending = $requestRec?->status === 'pending';

        // 出退勤の表示（承認待ちは申請値を優先）
        $displayClockIn  = $attendance->clock_in;
        $displayClockOut = $attendance->clock_out;
        if ($isPending) {
            if ($requestRec?->requested_clock_in) {
                $displayClockIn = Carbon::parse($requestRec->requested_clock_in);
            }
            if ($requestRec?->requested_clock_out) {
                $displayClockOut = Carbon::parse($requestRec->requested_clock_out);
            }
        }

        // 休憩の表示（承認待ちは申請値を優先）
        if ($isPending && !empty($requestRec?->requested_breaks)) {
            $displayBreaks = collect($requestRec->requested_breaks)->map(function ($b) {
                return (object)[
                    'break_start' => $b['start'] ? Carbon::parse($b['start']) : null,
                    'break_end'   => $b['end']   ? Carbon::parse($b['end'])   : null,
                ];
            });
        } else {
            $displayBreaks = $attendance->breakTimes?->sortBy('break_start')->values() ?? collect();
        }

        $displayNote = $isPending ? ($requestRec->reason ?? '') : old('note', '');

        return view('user.attendance.show', [
            'date'            => $day,
            'attendance'      => $attendance,
            'isPending'       => $isPending,
            'request'         => $requestRec,
            'displayClockIn'  => $displayClockIn,
            'displayClockOut' => $displayClockOut,
            'displayBreaks'   => $displayBreaks,
            'displayNote'     => $displayNote,
        ]);
    }
}
