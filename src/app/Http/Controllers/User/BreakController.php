<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\BreakTime;
use Illuminate\Support\Facades\Auth;

class BreakController extends Controller
{
    // 休憩開始
    public function store(Attendance $attendance)
    {
        // 自分の勤怠か & 未退勤かチェック
        $this->authorizeAttendance($attendance);

        BreakTime::create([
            'attendance_id' => $attendance->id,
            'break_start' => now(),
        ]);

        return back();
    }

    // 休憩終了
    public function close(Attendance $attendance)
    {
        $current = BreakTime::where('attendance_id', $attendance->id)
            ->whereNull('break_end')
            ->latest('break_start')
            ->first();

        $current->update([
            'break_end' => now(),
        ]);

        return back();
    }

    private function authorizeAttendance(Attendance $attendance)
    {
        if ($attendance->user_id !== Auth::id()) {
            abort(403);
        }
        if (!is_null($attendance->clock_out)) {
            abort(400, 'すでに退勤済みです。');
        }
    }
}
