<?php

namespace Tests\Feature\Attendance;

use Tests\TestCase;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Attendance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

class ClockOutTest extends TestCase
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

    /**
     * 退勤ボタンが正しく機能する
     * - 出勤中画面で「退勤」ボタン表示
     * - POSTで退勤後、画面は「退勤済」相当（お疲れ様でした。表示、退勤ボタン非表示）
     * - DBに退勤時刻が記録される
     */
    public function test_clock_out_button_works_and_status_becomes_clocked_out()
    {
        // 18:00 に退勤する前提
        $fixed = Carbon::create(2025, 10, 22, 18, 0, 0, 'Asia/Tokyo');
        $user  = $this->actingUserAt($fixed);
        $att   = $this->openAttendance($user, $fixed);

        // 出勤中：退勤ボタンが見える
        $page = $this->get('/attendance');
        $page->assertOk();
        $page->assertSee('退勤');

        // 退勤実行
        $resp = $this->post("/attendance/{$att->id}/clockout");
        $resp->assertStatus(302); // 遷移（/attendance想定）
        // $resp->assertRedirect('/attendance');

        // 退勤済UI（あなたのBladeでは「お疲れ様でした。」表示、退勤ボタンなし）
        $page = $this->get('/attendance');
        $page->assertOk();
        $page->assertSee('お疲れ様でした。');
        $page->assertDontSee('退勤');
        $page->assertDontSee('休憩入');
        $page->assertDontSee('休憩戻');

        // DB：退勤時刻が保存されている
        $att->refresh();
        $this->assertNotNull($att->clock_out, 'clock_out が保存されていません');
        $this->assertTrue(
            Carbon::parse($att->clock_out)->equalTo($fixed),
            'clock_out が期待と一致しません: ' . $att->clock_out . ' != ' . $fixed->toDateTimeString()
        );
    }

    /**
     * 退勤時刻が勤怠一覧画面で確認できる
     */
    public function test_clock_out_time_is_visible_on_attendance_list()
    {
        // 9:30 出勤 → 17:55 退勤
        $base  = Carbon::create(2025, 10, 22, 9, 30, 0, 'Asia/Tokyo');
        $user  = $this->actingUserAt($base);
        $att   = $this->openAttendance($user, $base);

        // 退勤（17:55）
        Carbon::setTestNow($base->copy()->setTime(17, 55));
        $this->post("/attendance/{$att->id}/clockout");

        // 一覧画面へ
        $list = $this->get('/attendance/list');
        $list->assertOk();

        $list->assertSee('17:55');

        // DB担保
        $this->assertDatabaseHas('attendances', [
            'user_id'   => $user->id,
            'work_date' => $base->copy()->startOfDay()->toDateString(),
        ]);
    }
}
