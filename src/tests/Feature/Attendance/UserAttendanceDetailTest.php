<?php

namespace Tests\Feature\Attendance;

use Tests\TestCase;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Attendance;
use App\Models\BreakTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

class UserAttendanceDetailTest extends TestCase
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

    private function makeAttendance(User $user, Carbon $day, string $in = '09:00:00', ?string $out = '18:00:00'): Attendance
    {
        $inAt  = $day->copy()->setTimeFromTimeString($in);
        $outAt = $out ? $day->copy()->setTimeFromTimeString($out) : null;

        return Attendance::create([
            'user_id'   => $user->id,
            'work_date' => $day->copy()->startOfDay(),
            'clock_in'  => $inAt,
            'clock_out' => $outAt,
            'note'      => null,
        ]);
    }

    /** 勤怠詳細：名前がログインユーザーの氏名になっている */
    public function test_detail_shows_login_user_name()
    {
        $day  = Carbon::create(2025, 10, 8, 9, 0, 0, 'Asia/Tokyo');
        $user = $this->actingUserAt($day);
        $att  = $this->makeAttendance($user, $day, '09:10:00', '18:20:00');

        $res = $this->get(route('user.attendance.show', ['attendance' => $att->id]));
        $res->assertOk();
        $res->assertSee('テスト太郎'); // 表示欄にユーザー名が出ていること
    }

    /** 勤怠詳細：日付が選択した日付になっている（日本語表記/数値表記どちらでもOK） */
    public function test_detail_shows_selected_date()
    {
        $day  = Carbon::create(2025, 10, 12, 9, 0, 0, 'Asia/Tokyo');
        $user = $this->actingUserAt($day);
        $att  = $this->makeAttendance($user, $day);

        $res = $this->get(route('user.attendance.show', ['attendance' => $att->id]));
        $res->assertOk();

        // 画面の表記ゆれに耐える（例：2025年10月12日（日）／2025/10/12）
        $candidates = [
            $day->locale('ja')->isoFormat('YYYY年M月D日（ddd）'),
            $day->locale('ja')->isoFormat('YYYY年M月D日'),
            $day->format('Y/m/d'),
            $day->format('Y-m-d'),
        ];
        $html = $res->getContent();
        $found = false;
        foreach ($candidates as $s) {
            if (str_contains($html, $s)) { $found = true; break; }
        }
        $this->assertTrue($found, '詳細画面に対象日付が表示されていません（候補：'.implode(' / ', $candidates).'）');
    }

    /** 勤怠詳細：「出勤・退勤」欄の時間が打刻と一致している（H:i 表示想定） */
    public function test_detail_shows_clock_in_and_out_times()
    {
        $day  = Carbon::create(2025, 10, 15, 0, 0, 0, 'Asia/Tokyo');
        $user = $this->actingUserAt($day->copy()->setTime(9,0));
        $att  = $this->makeAttendance($user, $day, '09:05:00', '18:30:00');

        $res = $this->get(route('user.attendance.show', ['attendance' => $att->id]));
        $res->assertOk();

        // Blade 側が H:i で表示している想定
        $res->assertSee('09:05');
        $res->assertSee('18:30');
    }

    /** 勤怠詳細：「休憩」欄の各時間が打刻と一致している（複数休憩対応・H:i 表示想定） */
    public function test_detail_shows_break_intervals_matching_stamps()
    {
        $day  = Carbon::create(2025, 10, 20, 0, 0, 0, 'Asia/Tokyo');
        $user = $this->actingUserAt($day->copy()->setTime(9,0));
        $att  = $this->makeAttendance($user, $day, '09:00:00', '19:00:00');

        // 1回目：12:05 → 12:25
        BreakTime::create([
            'attendance_id' => $att->id,
            'break_start'   => '12:05:00',
            'break_end'     => '12:25:00',
        ]);
        // 2回目：15:00 → 15:10
        BreakTime::create([
            'attendance_id' => $att->id,
            'break_start'   => '15:00:00',
            'break_end'     => '15:10:00',
        ]);

        $res = $this->get(route('user.attendance.show', ['attendance' => $att->id]));
        $res->assertOk();

        // H:i 表示想定（Blade側で format('H:i') の前提）
        $res->assertSee('12:05');
        $res->assertSee('12:25');
        $res->assertSee('15:00');
        $res->assertSee('15:10');
    }

    /** by_date 入口から作られた詳細でも、日付とユーザー名が正しく出る（未勤務日） */
    public function test_detail_by_date_shows_user_and_date_even_without_record()
    {
        $base = Carbon::create(2025, 10, 25, 9, 0, 0, 'Asia/Tokyo');
        $user = $this->actingUserAt($base);

        // 勤務なし日の詳細入口（by_date は空レコード作成→ID付き詳細へ 302 リダイレクト想定）
        $targetDate = '2025-10-26';
        $res = $this->get(route('user.attendance.show.by_date', ['date' => $targetDate]));

        // リダイレクトを確認
        $res->assertRedirect();
        $showUrl = $res->headers->get('Location');
        $this->assertNotEmpty($showUrl, 'by_date からのリダイレクト先URLが取得できません');

        // 追従して詳細を取得
        $detail = $this->get($showUrl);
        $detail->assertOk();

        $detail->assertSee('テスト太郎');

        // 日付の表記ゆれ吸収（Y/m/d or 日本語）
        $candidates = [
            Carbon::parse($targetDate)->locale('ja')->isoFormat('YYYY年M月D日（ddd）'),
            Carbon::parse($targetDate)->locale('ja')->isoFormat('YYYY年M月D日'),
            Carbon::parse($targetDate)->format('Y/m/d'),
            $targetDate,
        ];
        $html = $detail->getContent();
        $found = false;
        foreach ($candidates as $s) {
            if (str_contains($html, $s)) { $found = true; break; }
        }
        $this->assertTrue($found, 'by_date 詳細に対象日付が表示されていません。');
    }
}
