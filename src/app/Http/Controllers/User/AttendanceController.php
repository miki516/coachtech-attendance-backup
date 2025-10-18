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
    // 画面表示
    public function showPunch()
    {
        $user = Auth::user();
        $now = Carbon::now();

        // 未退勤の勤怠レコード（＝出勤中）
        $open = Attendance::where('user_id', $user->id)
            ->whereNull('clock_out')
            ->latest('clock_in')
            ->first();

        // 今日すでに出勤レコードがあるか
        $todayExists = Attendance::where('user_id', $user->id)
            ->whereDate('clock_in', $now->toDateString())
            ->exists();

        // 休憩中
        $break = null;
        if ($open) {
        $break = BreakTime::where('attendance_id', $open->id)
            ->whereNull('break_end')
            ->latest('break_start')
            ->first();
        }

        // ラベル判定
        $status = '勤務外';
        if ($break) {
            $status = '休憩中';
        } elseif ($open) {
            $status = '出勤中';
        } elseif ($todayExists) {
            $status = '退勤済';
        }

        // Blade用のフラグ
        $canClockIn  = !$open && !$todayExists;
        $canClockOut = (bool) $open;
        $canBreakIn  = (bool) $open && !$break;
        $canBreakOut = (bool) $break;

        return view('attendance.punch', compact(
            'now', 'status', 'open', 'canClockIn', 'canClockOut', 'canBreakIn', 'canBreakOut'
        ));
    }

    // 出勤ボタン押下
    public function storePunch()
    {
        $user = Auth::user();
        $today = now()->toDateString();

        $alreadyOpen = Attendance::where('user_id', $user->id)
            ->whereNull('clock_out')
            ->exists();

        if ($alreadyOpen) {
            return back()->with('error', 'すでに出勤中です');
        }

        // その日すでに出勤済か
        $todayExists = Attendance::where('user_id', $user->id)
            ->whereDate('clock_in', $today)
            ->exists();
        if ($todayExists) {
            return back();
        }

        Attendance::create([
            'user_id'  => $user->id,
            'clock_in' => now(),
        ]);

        return redirect()->route('user.attendance.punch');
    }

    // 退勤ボタン押下
    public function clockOut(Attendance $attendance)
    {
        // 自分のレコードか確認
        if ($attendance->user_id !== auth()->id()) {
            abort(403);
        }

        $attendance->update([
            'clock_out' => now(),
        ]);

        return back();
    }

    // 勤怠一覧を表示
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

        // 自分の当月勤怠を日付ごとにまとめる
        $byDate = Attendance::with('breakTimes')
            ->where('user_id', $user->id)
            ->whereBetween('clock_in', [$start, $end])
            ->orderBy('clock_in')
            ->get()
            ->keyBy(fn($attendance) => $attendance->clock_in->toDateString());

        // 月内の全日を行データに整形
        $rows = [];
        foreach (CarbonPeriod::create($start, $end) as $day) {
            $rec = $byDate[$day->toDateString()] ?? null;

            // 休憩分
            $breakMin = 0;
            if ($rec && $rec->relationLoaded('breakTimes')) {
                $breakMin = $rec->breakTimes->sum(function ($b) {
                    return ($b->break_start && $b->break_end)
                        ? $b->break_end->diffInMinutes($b->break_start)
                        : 0;
                });
            }

            // 勤務合計（退勤が無ければ0）
            $workMin = ($rec && $rec->clock_in && $rec->clock_out)
                ? max($rec->clock_out->diffInMinutes($rec->clock_in) - $breakMin, 0)
                : 0;

            $rows[] = [
                'day'       => $day->copy(),
                'rec'       => $rec,
                'break_min' => $breakMin,
                'work_min'  => $workMin,
            ];
        }

        // 前月・翌月のクエリ値
        $prev = $start->copy()->subMonth()->format('Y-m');
        $next = $end->copy()->addMonth()->format('Y-m');

        // 表示中の月が今月なら翌月リンクを無効に
        $nextDisabled = $cursor->isSameMonth(now());

        return view('attendance.index', [
            'rows'   => $rows,
            'cursor'    => $cursor,
            'prevMonth' => $prev,
            'nextMonth' => $next,
            'nextDisabled' => $nextDisabled,
        ]);
    }

    public function show(string $date)
    {
        $user = Auth::user();
        $day = Carbon::createFromFormat('Y-m-d', $date);

        $attendance = Attendance::with('breakTimes')
            ->where('user_id', $user->id)
            ->whereDate('clock_in', $day)
            ->orderBy('clock_in')
            ->first();

        if (!$attendance) {
            return view('attendance.detail', [
                'date'       => $day,
                'attendance' => null,
                'breaks'     => collect(),
                'isPending'  => false,
            ]);
        }

        $breaks = $attendance->breakTimes
            ->sortBy('break_start')
            ->values();

        $isPending = StampCorrectionRequest::where('user_id', $user->id)
            ->where('attendance_id', $attendance->id)
            ->where('status', 'pending')
            ->exists();

        return view('attendance.detail', [
            'date'       => $day,
            'attendance' => $attendance,
            'breaks'     => $breaks,
            'isPending'  => $isPending,
        ]);
    }
}