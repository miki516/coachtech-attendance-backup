<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Attendance;
use App\Models\BreakTime;

class AttendanceSeeder extends Seeder
{
    public function run(): void
    {
        // 一般ユーザーを取得
        $user = User::where('role', 'user')->first();

        // 今月・先月・先々月の3か月分の勤怠データを作成
        for ($m = 0; $m < 3; $m++) {
            $month = now()->startOfMonth()->subMonths($m);

            // 10日分
            for ($i = 0; $i < 10; $i++) {
                $date = $month->copy()->addDays($i);

                // 出勤・退勤時刻
                $clockIn  = $date->copy()->setTime(9, 0);
                $clockOut = $date->copy()->setTime(18, 0);

                // 勤怠データ作成
                $attendance = Attendance::create([
                    'user_id'   => $user->id,
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
