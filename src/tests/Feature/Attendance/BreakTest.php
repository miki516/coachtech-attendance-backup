<?php

namespace Tests\Feature\Attendance;

use Tests\TestCase;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Attendance;
use App\Models\BreakTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

class BreakTest extends TestCase
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
            'email' => 'user@example.com',
            'password' => Hash::make('password123'),
            'email_verified_at' => now(),
            'role' => 'user',
        ]);
        $this->actingAs($user);
        return $user;
    }

    private function openAttendance(User $user, Carbon $when): Attendance
    {
        return Attendance::create([
            'user_id'   => $user->id,
            'work_date' => $when->copy()->startOfDay(),
            'clock_in'  => $when->copy()->setTime(9, 0, 0),
            'clock_out' => null,
            'note'      => null,
        ]);
    }

    /** 休憩ボタンが正しく機能する（休憩入 → 休憩中表示） */
    public function test_break_in_button_works_and_status_becomes_on_break()
    {
        $fixed = Carbon::create(2025, 10, 22, 12, 00, 0, 'Asia/Tokyo');
        $user  = $this->actingUserAt($fixed);
        $att   = $this->openAttendance($user, $fixed);

        // 出勤中 → 「休憩入」ボタンが見える／「休憩戻」は見えない
        $page = $this->get('/attendance');
        $page->assertOk();
        $page->assertSee('休憩入');
        $page->assertDontSee('休憩戻');

        // 休憩入
        $resp = $this->post("/attendance/{$att->id}/break-in");
        $resp->assertStatus(302);

        // 画面再取得 → 「休憩戻」表示（休憩中）
        $page = $this->get('/attendance');
        $page->assertOk();
        $page->assertSee('休憩戻');
        $page->assertDontSee('休憩入');

        // DB確認（openな休憩が1件）
        $this->assertDatabaseHas('break_times', [
            'attendance_id' => $att->id,
            'break_end'     => null,
        ]);
    }

    /** 休憩戻ボタンが正しく機能する（休憩中 → 出勤中表示） */
    public function test_break_out_button_works_and_status_returns_to_on_duty()
    {
        $fixed = Carbon::create(2025, 10, 22, 12, 10, 0, 'Asia/Tokyo');
        $user  = $this->actingUserAt($fixed);
        $att   = $this->openAttendance($user, $fixed);

        // 先に休憩入
        $this->post("/attendance/{$att->id}/break-in");

        // 5分後に休憩戻
        Carbon::setTestNow($fixed->copy()->addMinutes(5));
        $resp = $this->post("/attendance/{$att->id}/break-out");
        $resp->assertStatus(302);

        // 出勤中UIに戻る（休憩入あり／休憩戻なし）
        $page = $this->get('/attendance');
        $page->assertOk();
        $page->assertSee('休憩入');
        $page->assertDontSee('休憩戻');

        // DB：break_end が埋まっている
        $bt = BreakTime::where('attendance_id', $att->id)->first();
        $this->assertNotNull($bt);
        $this->assertNotNull($bt->break_end, 'break_end が保存されていません');
    }

    /** 休憩は一日に何回でもできる（再度 休憩入 ができる） */
    public function test_can_take_multiple_breaks_in_a_day()
    {
        $fixed = Carbon::create(2025, 10, 22, 12, 00, 0, 'Asia/Tokyo');
        $user  = $this->actingUserAt($fixed);
        $att   = $this->openAttendance($user, $fixed);

        // 1回目：入→戻
        $this->post("/attendance/{$att->id}/break-in");
        Carbon::setTestNow($fixed->copy()->addMinutes(10));
        $this->post("/attendance/{$att->id}/break-out");

        // 2回目：さらに入できること
        Carbon::setTestNow($fixed->copy()->addMinutes(20));
        $resp = $this->post("/attendance/{$att->id}/break-in");
        $resp->assertStatus(302);

        // 画面：休憩中（戻ボタンあり／入ボタンなし）
        $page = $this->get('/attendance');
        $page->assertOk();
        $page->assertSee('休憩戻');
        $page->assertDontSee('休憩入');

        // DB：2件以上のBreakTimeが作成されていること
        $this->assertTrue(BreakTime::where('attendance_id', $att->id)->count() >= 2);
    }

    /** 休憩戻は一日に何回でもできる（2回目の休憩からも戻れる） */
    public function test_can_break_out_multiple_times_in_a_day()
    {
        $fixed = Carbon::create(2025, 10, 22, 12, 00, 0, 'Asia/Tokyo');
        $user  = $this->actingUserAt($fixed);
        $att   = $this->openAttendance($user, $fixed);

        // 1回目：入→戻
        $this->post("/attendance/{$att->id}/break-in");
        Carbon::setTestNow($fixed->copy()->addMinutes(10));
        $this->post("/attendance/{$att->id}/break-out");

        // 2回目：入→戻
        Carbon::setTestNow($fixed->copy()->addMinutes(20));
        $this->post("/attendance/{$att->id}/break-in");
        Carbon::setTestNow($fixed->copy()->addMinutes(30));
        $resp = $this->post("/attendance/{$att->id}/break-out");
        $resp->assertStatus(302);

        // 画面：出勤中UIに戻る（休憩入あり／休憩戻なし）
        $page = $this->get('/attendance');
        $page->assertOk();
        $page->assertSee('休憩入');
        $page->assertDontSee('休憩戻');

        // DB：全ての休憩がcloseになっている
        $this->assertEquals(
            0,
            BreakTime::where('attendance_id', $att->id)->whereNull('break_end')->count(),
            'openな休憩レコードが残っています'
        );
    }

    /** 休憩の合計時間が勤怠一覧画面で正しく表示される */
    public function test_total_break_time_is_visible_on_attendance_list()
    {
        $fixed = Carbon::create(2025, 10, 22, 12, 00, 0, 'Asia/Tokyo');
        $user  = $this->actingUserAt($fixed);
        $att   = $this->openAttendance($user, $fixed);

        // 12:05 入 → 12:20 戻（15分）
        Carbon::setTestNow($fixed->copy()->addMinutes(5));
        $this->post("/attendance/{$att->id}/break-in");
        Carbon::setTestNow($fixed->copy()->addMinutes(20));
        $this->post("/attendance/{$att->id}/break-out");

        // 一覧画面へ
        $list = $this->get('/attendance/list');
        $list->assertOk();
        $html = $list->getContent();

        // 合計休憩時間 15分 を想定。
        $patterns = [
            '/\b0:15\b/u',    // 0:15
        ];

        $matched = false;
        foreach ($patterns as $p) {
            if (preg_match($p, $html) === 1) { $matched = true; break; }
        }

        $this->assertTrue(
            $matched,
            "一覧に合計休憩時間（15分相当）が表示されていません。"
        );
    }
}
