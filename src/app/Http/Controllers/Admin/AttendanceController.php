<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Attendance;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AttendanceController extends Controller
{
    // 勤怠一覧を表示
    public function index(Request $request)
    {
        $date = $request->input('date')
            ? Carbon::parse($request->input('date'))
            : Carbon::today();

        // 当日の勤怠を取得
        $attendances = Attendance::with('user', 'breakTimes')
            ->whereDate('clock_in', $date)
            ->orderBy('user_id')
            ->get();

        // 全ユーザー取得
        $users = User::where('role', 'user')->get();

        // 表示用に整形
        $rows = $users->map(function ($user) use ($attendances) {
            $att = $attendances->firstWhere('user_id', $user->id);

            $clockIn  = $att?->clock_in ? Carbon::parse($att->clock_in)->format('H:i') : '—';
            $clockOut = $att?->clock_out ? Carbon::parse($att->clock_out)->format('H:i') : '—';

            $totalBreakMin = $att?->breakTimes->sum(function ($b) {
                return $b->break_start && $b->break_end
                    ? Carbon::parse($b->break_end)->diffInMinutes(Carbon::parse($b->break_start))
                    : 0;
            }) ?? 0;

            $breakTime = $totalBreakMin
                ? sprintf('%d:%02d', intdiv($totalBreakMin, 60), $totalBreakMin % 60)
                : '—';

            $totalWork = '—';
            if ($att?->clock_in && $att?->clock_out) {
                $totalMin = Carbon::parse($att->clock_out)->diffInMinutes(Carbon::parse($att->clock_in)) - $totalBreakMin;
                $totalWork = sprintf('%d:%02d', intdiv($totalMin, 60), $totalMin % 60);
            }

            return [
                'name'      => $user->name,
                'clock_in'  => $clockIn,
                'clock_out' => $clockOut,
                'break'     => $breakTime,
                'total'     => $totalWork,
                'user_id'   => $user->id,
            ];
        });

        // 前日・翌日ボタン用の日付
        $prevDate = $date->copy()->subDay()->toDateString();
        $nextDate = $date->copy()->addDay()->toDateString();

        return view('admin.attendance.index', [
            'date'     => $date,
            'rows'     => $rows,
            'prevDate' => $prevDate,
            'nextDate' => $nextDate,
        ]);
    }

    public function show($attendanceId)
    {
        $attendance = Attendance::with(['user', 'breakTimes'])
            ->findOrFail($attendanceId);

        $date = $attendance->clock_in
            ? Carbon::parse($attendance->clock_in)
            : Carbon::today();

        $breaks = $attendance->breakTimes
            ->sortBy('break_start')
            ->values();

        return view('admin.attendance.show', [
            'attendance' => $attendance,
            'breaks' => $breaks,
            'date' => $date,
        ]);
    }
}
