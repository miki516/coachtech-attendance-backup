<?php

namespace Tests\Feature\Attendance;

use Tests\TestCase;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Attendance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

class ClockInTest extends TestCase
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

    /**
     * 出勤ボタンが正しく機能する
     * 1) 勤務外で「出勤」ボタンが見える
     * 2) POSTで出勤 → 画面は勤務中相当のUI（退勤/休憩入表示、出勤は非表示）
     * 3) DBに出勤時刻が記録される
     */
    public function test_clock_in_button_works_and_status_becomes_on_duty()
    {
        $fixed = Carbon::create(2025, 10, 22, 9, 30, 0, 'Asia/Tokyo');
        $user  = $this->actingUserAt($fixed);

        // 勤怠打刻画面（勤務外）
        $response = $this->get('/attendance');
        $response->assertOk();
        $response->assertSee('出勤');
        $response->assertDontSee('退勤');
        $response->assertDontSee('休憩入');
        $response->assertDontSee('休憩戻');

        // 出勤実行（POST /attendance）
        $response = $this->post('/attendance', []); // CSRFはテストでは不要
        $response->assertStatus(302);
        // 成功後の画面
        $page = $this->get('/attendance');
        $page->assertOk();
        $page->assertSee('退勤');
        $page->assertSee('休憩入');
        $page->assertDontSee('出勤');

        // DBに出勤時刻が正しく記録
        $this->assertDatabaseHas('attendances', [
            'user_id'   => $user->id,
            'work_date' => $fixed->copy()->startOfDay()->toDateString(),
        ]);

        $attendance = Attendance::where('user_id', $user->id)->first();
        $this->assertNotNull($attendance);
        // clock_in が固定時刻で保存されているか
        $this->assertTrue(
            Carbon::parse($attendance->clock_in)->equalTo($fixed),
            'clock_in が期待と一致しません: ' . $attendance->clock_in . ' != ' . $fixed->toDateTimeString()
        );
    }

    /**
     * 出勤は一日一回のみ：退勤済ユーザーは同日に再出勤できない（出勤ボタン非表示）
     */
    public function test_clock_in_button_is_hidden_when_already_clocked_out_today()
    {
        $fixed = Carbon::create(2025, 10, 22, 18, 0, 0, 'Asia/Tokyo');
        $user  = $this->actingUserAt($fixed);

        Attendance::create([
            'user_id'   => $user->id,
            'work_date' => $fixed->copy()->startOfDay(),
            'clock_in'  => $fixed->copy()->setTime(9, 0),
            'clock_out' => $fixed->copy()->setTime(17, 0),
            'note'      => null,
        ]);

        $page = $this->get('/attendance');
        $page->assertOk();
        $page->assertSee('お疲れ様でした。');
        $page->assertDontSee('出勤');
    }

    /**
     * 出勤時刻が勤怠一覧画面で確認できる
     */
    public function test_clock_in_time_is_visible_on_attendance_list()
    {
        $fixed = Carbon::create(2025, 10, 22, 9, 30, 0, 'Asia/Tokyo');
        $user  = $this->actingUserAt($fixed);

        // 出勤
        $this->post('/attendance');

        // 一覧画面へ
        $list = $this->get('/attendance/list');
        $list->assertOk();

        // 画面に当日の出勤時刻（09:30 など）が表示されることを確認
        // 一覧の表記フォーマットに合わせて調整（ここでは H:i 想定）
        $list->assertSee($fixed->format('H:i'));

        // DB側の担保
        $this->assertDatabaseHas('attendances', [
            'user_id'   => $user->id,
            'work_date' => $fixed->copy()->startOfDay()->toDateString(),
        ]);
    }
}
