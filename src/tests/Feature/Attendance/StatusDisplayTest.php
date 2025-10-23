<?php

namespace Tests\Feature\Ui;

use Tests\TestCase;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Attendance;
use App\Models\BreakTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

class StatusDisplayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.timezone' => 'Asia/Tokyo']);
    }

    private function actingUserAt(Carbon $when): User
    {
        Carbon::setTestNow($when);
        $user = User::factory()->create([
            'name' => 'テスト太郎',
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
            'email_verified_at' => now(),
            'role' => 'user',
        ]);
        $this->actingAs($user);
        return $user;
    }

    /** 勤務外：当日に出勤レコードなし → 出勤ボタンのみ表示 */
    public function test_off_duty_shows_only_clock_in_button()
    {
        $fixed = Carbon::create(2025, 10, 22, 9, 30, 0, 'Asia/Tokyo');
        $this->actingUserAt($fixed);

        $response = $this->get(route('user.attendance.punch'));
        $response->assertOk();
        $response->assertSee('出勤');
        $response->assertDontSee('退勤');
        $response->assertDontSee('休憩入');
        $response->assertDontSee('休憩戻');
    }

    /** 出勤中：open勤怠（clock_out:null） → 退勤/休憩入は表示、休憩戻は非表示 */
    public function test_on_duty_shows_clock_out_and_break_in_but_not_break_out()
    {
        $fixed = Carbon::create(2025, 10, 22, 10, 00, 0, 'Asia/Tokyo');
        $user  = $this->actingUserAt($fixed);

        Attendance::create([
            'user_id'   => $user->id,
            'work_date' => $fixed->copy()->startOfDay(),
            'clock_in'  => $fixed->copy()->subHour(), // 9:00 出勤
            'clock_out' => null,
            'note'      => null,
        ]);

        $response = $this->get(route('user.attendance.clockin'));
        $response->assertOk();
        $response->assertSee('退勤');
        $response->assertSee('休憩入');
        $response->assertDontSee('休憩戻');
        $response->assertDontSee('出勤');
    }

    /** 休憩中：open勤怠＋open休憩（break_end:null） → 休憩戻は表示、休憩入は非表示 */
    public function test_on_break_shows_break_out_but_not_break_in()
    {
        $fixed = Carbon::create(2025, 10, 22, 12, 15, 0, 'Asia/Tokyo');
        $user  = $this->actingUserAt($fixed);

        $att = Attendance::create([
            'user_id'   => $user->id,
            'work_date' => $fixed->copy()->startOfDay(),
            'clock_in'  => $fixed->copy()->subHours(3), // 9:15 出勤
            'clock_out' => null,
            'note'      => null,
        ]);

        BreakTime::create([
            'attendance_id' => $att->id,
            'break_start'   => $fixed->copy()->subMinutes(10)->format('H:i'), // 12:05 休憩入
            'break_end'     => null,
        ]);

        $response = $this->get(route('user.attendance.clockin'));
        $response->assertOk();
        $response->assertSee('休憩戻');       // 戻るボタンは出る
        $response->assertDontSee('休憩入');   // 休憩入は出ない
        $response->assertSee('退勤');         // 退勤は出る
        $response->assertDontSee('出勤');
    }

    /** 退勤済：close勤怠（clock_outあり） → お疲れ様でした。表示、出勤ボタンは非表示 */
    public function test_clocked_out_shows_thanks_and_not_clock_in()
    {
        $fixed = Carbon::create(2025, 10, 22, 18, 00, 0, 'Asia/Tokyo');
        $user  = $this->actingUserAt($fixed);

        Attendance::create([
            'user_id'   => $user->id,
            'work_date' => $fixed->copy()->startOfDay(),
            'clock_in'  => $fixed->copy()->subHours(8),   // 10:00 出勤
            'clock_out' => $fixed->copy()->subMinutes(5), // 17:55 退勤
            'note'      => null,
        ]);

        $response = $this->get(route('user.attendance.clockin'));
        $response->assertOk();
        $response->assertSee('お疲れ様でした。');
        $response->assertDontSee('出勤');
        $response->assertDontSee('休憩入');
        $response->assertDontSee('休憩戻');
    }
}
