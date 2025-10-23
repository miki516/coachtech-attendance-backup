<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Attendance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

class AdminAttendanceListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.timezone' => 'Asia/Tokyo']);
    }

    private function actingAdmin(): User
    {
        $admin = User::factory()->create([
            'name' => '管理者太郎',
            'email' => 'admin@example.com',
            'password' => Hash::make('password123'),
            'email_verified_at' => now(),
            'role' => 'admin',
        ]);
        $this->actingAs($admin);
        return $admin;
    }

    private function makeUser(string $name, string $email): User
    {
        return User::factory()->create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make('password123'),
            'email_verified_at' => now(),
            'role' => 'user',
        ]);
    }

    private function makeAttendance(User $user, Carbon $day, string $in, ?string $out): Attendance
    {
        return Attendance::create([
            'user_id'   => $user->id,
            'work_date' => $day->copy()->startOfDay(),
            'clock_in'  => $day->copy()->setTimeFromTimeString($in),
            'clock_out' => $out ? $day->copy()->setTimeFromTimeString($out) : null,
            'note'      => null,
        ]);
    }

    /** 初回遷移で現在日付（当日）が表示される */
    public function test_initial_access_shows_today_date()
    {
        $this->actingAdmin();

        $today = Carbon::create(2025, 10, 22, 9, 0, 0, 'Asia/Tokyo');
        Carbon::setTestNow($today);

        $res = $this->get(route('admin.attendance.index')); // /admin/attendance/list
        $res->assertOk();

        // 表記ゆれに耐える候補
        $candidates = [
            $today->locale('ja')->isoFormat('YYYY年M月D日（ddd）'),
            $today->locale('ja')->isoFormat('YYYY年M月D日'),
            $today->format('Y/m/d'),
            $today->toDateString(),
        ];
        $html = $res->getContent();
        $this->assertTrue(
            collect($candidates)->contains(fn($s) => str_contains($html, $s)),
            '当日の日付表示が見つかりません（候補：'.implode(' / ', $candidates).'）'
        );

        // 前日／翌日ボタンの文言が画面にある（UIの最低限確認）
        $res->assertSee('前日');
        $res->assertSee('翌日');
    }

    /** その日になされた全ユーザーの勤怠情報が表示される */
    public function test_list_shows_all_users_records_for_the_day()
    {
        $this->actingAdmin();

        $day = Carbon::create(2025, 10, 22, 0, 0, 0, 'Asia/Tokyo');
        Carbon::setTestNow($day);

        $alice = $this->makeUser('山田花子', 'hanako@example.com');
        $taro  = $this->makeUser('田中太郎', 'taro@example.com');
        $other = $this->makeUser('別日ユーザー', 'other@example.com');

        // 同日：2名の勤怠
        $this->makeAttendance($alice, $day, '09:00:00', '18:00:00');
        $this->makeAttendance($taro,  $day, '10:15:00', '19:30:00');

        // 別日：当日と被らないユニークな時刻にする（否定テスト用）
        $nextDay = $day->copy()->addDay();
        $this->makeAttendance($other, $nextDay, '07:11:00', '07:12:00');

        $res = $this->get(route('admin.attendance.index'));
        $res->assertOk();

        // 当日の2名が見える
        $res->assertSee('山田花子');
        $res->assertSee('09:00');
        $res->assertSee('18:00');

        $res->assertSee('田中太郎');
        $res->assertSee('10:15');
        $res->assertSee('19:30');

        // 別日レコード特有の時刻だけを否定（レイアウトに左右されない）
        $res->assertDontSee('07:11');
        $res->assertDontSee('07:12');
    }

    /** 「前日」を押すと前日データに切り替わる（?date= 前日） */
    public function test_prev_day_navigation_shows_previous_day_data()
    {
        $this->actingAdmin();

        $today = Carbon::create(2025, 10, 22, 0, 0, 0, 'Asia/Tokyo');
        Carbon::setTestNow($today);

        $prev = $today->copy()->subDay(); // 2025-10-21
        $user = $this->makeUser('前日ユーザー', 'prev@example.com');
        $this->makeAttendance($user, $prev, '08:45:00', '17:10:00');

        // 一旦当日
        $this->get(route('admin.attendance.index'))->assertOk();

        // 前日へ（実装想定：?date=YYYY-MM-DD）
        $res = $this->get(route('admin.attendance.index', ['date' => $prev->toDateString()]));
        $res->assertOk();

        // 前日の日付表示
        $candidates = [
            $prev->locale('ja')->isoFormat('YYYY年M月D日（ddd）'),
            $prev->locale('ja')->isoFormat('YYYY年M月D日'),
            $prev->format('Y/m/d'),
            $prev->toDateString(),
        ];
        $html = $res->getContent();
        $this->assertTrue(
            collect($candidates)->contains(fn($s) => str_contains($html, $s)),
            '前日の日付表示が見つかりません（候補：'.implode(' / ', $candidates).'）'
        );

        // 前日のレコードが表示
        $res->assertSee('前日ユーザー');
        $res->assertSee('08:45');
        $res->assertSee('17:10');
    }

    /** 「翌日」を押すと翌日データに切り替わる（?date= 翌日） */
    public function test_next_day_navigation_shows_next_day_data()
    {
        $this->actingAdmin();

        $today = Carbon::create(2025, 10, 22, 0, 0, 0, 'Asia/Tokyo');
        Carbon::setTestNow($today);

        $next = $today->copy()->addDay(); // 2025-10-23
        $user = $this->makeUser('翌日ユーザー', 'next@example.com');
        $this->makeAttendance($user, $next, '11:00:00', '20:05:00');

        // 翌日へ
        $res = $this->get(route('admin.attendance.index', ['date' => $next->toDateString()]));
        $res->assertOk();

        // 翌日の日付表示
        $candidates = [
            $next->locale('ja')->isoFormat('YYYY年M月D日（ddd）'),
            $next->locale('ja')->isoFormat('YYYY年M月D日'),
            $next->format('Y/m/d'),
            $next->toDateString(),
        ];
        $html = $res->getContent();
        $this->assertTrue(
            collect($candidates)->contains(fn($s) => str_contains($html, $s)),
            '翌日の日付表示が見つかりません（候補：'.implode(' / ', $candidates).'）'
        );

        // 翌日のレコードが表示
        $res->assertSee('翌日ユーザー');
        $res->assertSee('11:00');
        $res->assertSee('20:05');
    }
}
