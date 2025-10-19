<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Attendance;
use App\Models\BreakTime;
use Illuminate\Support\Carbon;

class AttendanceSeeder extends Seeder
{
    public function run(): void
    {
        // 一般ユーザーを1人取得（存在しない場合は早期終了）
        $user = User::where('role', 'user')->first();
        if (!$user) {
            $this->command?->warn('No user with role=user found. Skipping AttendanceSeeder.');
            return;
        }

        // 今月・先月・先々月の3か月分の勤怠データを作成
        for ($m = 0; $m < 3; $m++) {
            $month = Carbon::now()->startOfMonth()->subMonths($m);

            // 10日分
            for ($i = 0; $i < 10; $i++) {
                $date = $month->copy()->addDays($i);

                // 出勤・退勤時刻
                $clockIn  = $date->copy()->setTime(9, 0);
                $clockOut = $date->copy()->setTime(18, 0);

                // ★ 勤怠データ作成（work_date を必ずセット）
                $attendance = Attendance::create([
                    'user_id'   => $user->id,
                    'work_date' => $date->toDateString(),   // ここが必須
                    'clock_in'  => $clockIn,
                    'clock_out' => $clockOut,
                ]);

                // 休憩データ（パターンで変化）
                // 偶数日は1回、奇数日は2回休憩
                if ($i % 2 === 0) {
                    BreakTime::create([
                        'attendance_id' => $attendance->id,
                        'break_start'   => $date->copy()->setTime(12, 0),
                        'break_end'     => $date->copy()->setTime(13, 0),
                    ]);
                } else {
                    BreakTime::create([
                        'attendance_id' => $attendance->id,
                        'break_start'   => $date->copy()->setTime(12, 0),
                        'break_end'     => $date->copy()->setTime(12, 30),
                    ]);
                    BreakTime::create([
                        'attendance_id' => $attendance->id,
                        'break_start'   => $date->copy()->setTime(15, 0),
                        'break_end'     => $date->copy()->setTime(15, 15),
                    ]);
                }
            }
        }
    }
}
