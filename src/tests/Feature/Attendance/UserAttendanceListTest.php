<?php

namespace Tests\Feature\Attendance;

use Tests\TestCase;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Attendance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

class UserAttendanceListTest extends TestCase
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

    private function makeAttendance(User $user, Carbon $date, string $in = '09:00:00', ?string $out = '18:00:00'): Attendance
    {
        $inAt  = $date->copy()->setTimeFromTimeString($in);
        $outAt = $out ? $date->copy()->setTimeFromTimeString($out) : null;

        return Attendance::create([
            'user_id'   => $user->id,
            'work_date' => $date->copy()->startOfDay(),
            'clock_in'  => $inAt,
            'clock_out' => $outAt,
            'note'      => null,
        ]);
    }

    /** 自分が行った勤怠情報がすべて表示される（他人のは表示されない） */
    public function test_only_own_records_are_listed_for_the_month()
    {
        $now   = Carbon::create(2025, 10, 22, 9, 0, 0, 'Asia/Tokyo');
        $userA = $this->actingUserAt($now);
        $userB = User::factory()->create([
            'email' => 'other@example.com',
            'password' => Hash::make('password123'),
            'email_verified_at' => now(),
            'role' => 'user',
        ]);

        // 当月（2025/10） 自分の勤怠 2 件
        $d1 = Carbon::create(2025, 10, 3, 0, 0, 0, 'Asia/Tokyo');
        $d2 = Carbon::create(2025, 10, 5, 0, 0, 0, 'Asia/Tokyo');

        $att1 = $this->makeAttendance($userA, $d1, '09:00:00', '18:00:00');
        $att2 = $this->makeAttendance($userA, $d2, '10:00:00', '19:00:00');

        // 同月に他人の勤怠
        $d3 = Carbon::create(2025, 10, 7, 0, 0, 0, 'Asia/Tokyo');
        $otherAtt = $this->makeAttendance($userB, $d3, '09:00:00', '18:00:00');

        $res = $this->get('/attendance/list');
        $res->assertOk();

        // 自分の2日分は「ID付きの詳細リンク」が出る
        $res->assertSee(route('user.attendance.show', ['attendance' => $att1->id]));
        $res->assertSee(route('user.attendance.show', ['attendance' => $att2->id]));

        // 他人のレコードIDの詳細リンクは出ない（混入していないことを担保）
        $res->assertDontSee(route('user.attendance.show', ['attendance' => $otherAtt->id]));

        // 自分のレコードが無い日の行は「by_date」リンクになる
        $res->assertSee(route('user.attendance.show.by_date', ['date' => $d3->toDateString()]));

        // 日付セル自体は月カレンダーとして出るので、日付文字列が表示されていてもOK
        $res->assertSee($d1->locale('ja')->isoFormat('MM/DD(ddd)'));
        $res->assertSee($d2->locale('ja')->isoFormat('MM/DD(ddd)'));
        $res->assertSee($d3->locale('ja')->isoFormat('MM/DD(ddd)'));
    }


    /** 一覧に遷移したら現在の月（Y/m）が表示される */
    public function test_current_month_header_is_shown_on_initial_list()
    {
        $now  = Carbon::create(2025, 10, 22, 9, 0, 0, 'Asia/Tokyo');
        $this->actingUserAt($now);

        $res = $this->get('/attendance/list');
        $res->assertOk();
        $res->assertSee($now->format('Y/m')); // 見出し部 {{ $cursor->format('Y/m') }}
    }

    /** 「前月」：前月の情報が表示される（month パラメータで遷移） */
    public function test_prev_month_navigation_shows_previous_month_records()
    {
        $now   = Carbon::create(2025, 10, 22, 9, 0, 0, 'Asia/Tokyo');
        $user  = $this->actingUserAt($now);
        $prevM = '2025-09';

        // 前月 2025/09 に1件
        $prevDay = Carbon::create(2025, 9, 14, 0, 0, 0, 'Asia/Tokyo');
        $this->makeAttendance($user, $prevDay, '09:00:00', '17:30:00');

        $res = $this->get(route('user.attendance.index', ['month' => $prevM]));
        $res->assertOk();
        $res->assertSee('2025/09'); // 見出し
        $res->assertSee($prevDay->locale('ja')->isoFormat('MM/DD(ddd)')); // 行がある
    }

    /** 「翌月」：翌月の情報が表示される（month パラメータで遷移） */
    public function test_next_month_navigation_shows_next_month_records()
    {
        $now   = Carbon::create(2025, 10, 22, 9, 0, 0, 'Asia/Tokyo');
        $user  = $this->actingUserAt($now);
        $nextM = '2025-11';

        // 翌月 2025/11 に1件
        $nextDay = Carbon::create(2025, 11, 2, 0, 0, 0, 'Asia/Tokyo');
        $this->makeAttendance($user, $nextDay, '11:00:00', '20:00:00');

        $res = $this->get(route('user.attendance.index', ['month' => $nextM]));
        $res->assertOk();
        $res->assertSee('2025/11'); // 見出し
        $res->assertSee($nextDay->locale('ja')->isoFormat('MM/DD(ddd)'));
    }

    /** 「詳細」リンクでその日の勤怠詳細に遷移できる */
    public function test_detail_link_navigates_to_attendance_show()
    {
        $now  = Carbon::create(2025, 10, 10, 9, 0, 0, 'Asia/Tokyo');
        $user = $this->actingUserAt($now);

        $day  = Carbon::create(2025, 10, 8, 0, 0, 0, 'Asia/Tokyo');
        $att  = $this->makeAttendance($user, $day, '09:00:00', '18:00:00');

        // 一覧に「詳細」リンク（ID付き）が出ていること
        $list = $this->get('/attendance/list');
        $list->assertOk();

        $showUrl = route('user.attendance.show', ['attendance' => $att->id]);
        $list->assertSee($showUrl);

        // 実際に詳細ページへ遷移できること
        $detail = $this->get($showUrl);
        $detail->assertOk();
    }
}
