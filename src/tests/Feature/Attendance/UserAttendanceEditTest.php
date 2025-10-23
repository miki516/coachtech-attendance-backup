<?php

namespace Tests\Feature\Attendance;

use Tests\TestCase;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Attendance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

class UserAttendanceEditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.timezone' => 'Asia/Tokyo']);
    }

    private function actingUser(): User
    {
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

    private function makeAttendance(
        User $user,
        string $date = '2025-10-22',
        string $in = '09:00:00',
        ?string $out = '18:00:00'
    ): Attendance {
        $d = Carbon::parse($date, 'Asia/Tokyo');
        return Attendance::create([
            'user_id'   => $user->id,
            'work_date' => $d->copy()->startOfDay(),
            'clock_in'  => $d->copy()->setTimeFromTimeString($in),
            'clock_out' => $out ? $d->copy()->setTimeFromTimeString($out) : null,
            'note'      => null,
        ]);
    }

    /**
     * 1) 出勤 > 退勤（または退勤 < 出勤）：相関エラー
     * 期待文言：出勤時間もしくは退勤時間が不適切な値です
     * （clock_in か clock_out のどちらかに付けばOK）
     */
    public function test_clock_in_after_clock_out_is_rejected()
    {
        $user = $this->actingUser();
        $att  = $this->makeAttendance($user, '2025-10-22', '09:00:00', '18:00:00');

        $fromUrl = route('user.attendance.show', ['attendance' => $att->id]);

        $resp = $this->from($fromUrl)->post(route('user.request.store'), [
            'attendance_id' => $att->id,
            'date'          => '2025-10-22',
            'clock_in'      => '19:00',     // 退勤より後
            'clock_out'     => '18:00',
            'breaks'        => [],
            'note'          => '修正お願いします',
        ]);

        $resp->assertRedirect($fromUrl);

        $errors = session('errors')->getMessages();
        $bag = array_merge($errors['clock_in'] ?? [], $errors['clock_out'] ?? []);
        $this->assertTrue(
            collect($bag)->contains(fn ($m) => str_contains($m, '出勤時間もしくは退勤時間が不適切な値です')),
            '相関エラーの文言が見つかりません（期待：「出勤時間もしくは退勤時間が不適切な値です」）'
        );
    }

    /**
     * 2) 休憩開始 < 出勤 / 休憩開始 > 退勤：休憩開始エラー
     * 期待文言：休憩時間が不適切な値です
     */
    public function test_break_start_invalid_boundaries_are_rejected()
    {
        $user = $this->actingUser();
        $att  = $this->makeAttendance($user, '2025-10-22', '09:00:00', '18:00:00');

        $fromUrl = route('user.attendance.show', ['attendance' => $att->id]);

        // 休憩開始が出勤より前
        $resp1 = $this->from($fromUrl)->post(route('user.request.store'), [
            'attendance_id' => $att->id,
            'date'          => '2025-10-22',
            'clock_in'      => '09:00',
            'clock_out'     => '18:00',
            'breaks'        => [
                ['start' => '08:30', 'end' => '09:10'],
            ],
            'note'          => '修正お願いします',
        ]);
        $resp1->assertRedirect($fromUrl);
        $resp1->assertSessionHasErrors(['breaks.0.start' => '休憩時間が不適切な値です']);

        // 休憩開始が退勤より後
        $resp2 = $this->from($fromUrl)->post(route('user.request.store'), [
            'attendance_id' => $att->id,
            'date'          => '2025-10-22',
            'clock_in'      => '09:00',
            'clock_out'     => '18:00',
            'breaks'        => [
                ['start' => '19:00', 'end' => null],
            ],
            'note'          => '修正お願いします',
        ]);
        $resp2->assertRedirect($fromUrl);
        $resp2->assertSessionHasErrors(['breaks.0.start' => '休憩時間が不適切な値です']);
    }

    /**
     * 3) 休憩終了 > 退勤：休憩終了エラー
     * 期待文言：休憩時間もしくは退勤時間が不適切な値です
     */
    public function test_break_end_after_clock_out_is_rejected()
    {
        $user = $this->actingUser();
        $att  = $this->makeAttendance($user, '2025-10-22', '09:00:00', '18:00:00');

        $fromUrl = route('user.attendance.show', ['attendance' => $att->id]);

        $resp = $this->from($fromUrl)->post(route('user.request.store'), [
            'attendance_id' => $att->id,
            'date'          => '2025-10-22',
            'clock_in'      => '09:00',
            'clock_out'     => '18:00',
            'breaks'        => [
                ['start' => '17:30', 'end' => '19:00'], // 終了が退勤後
            ],
            'note'          => '修正お願いします',
        ]);

        $resp->assertRedirect($fromUrl);
        $resp->assertSessionHasErrors(['breaks.0.end' => '休憩時間もしくは退勤時間が不適切な値です']);
    }

    /**
     * 4) 備考未入力：必須エラー
     * 期待文言：備考を記入してください
     */
    public function test_note_is_required()
    {
        $user = $this->actingUser();
        $att  = $this->makeAttendance($user);

        $fromUrl = route('user.attendance.show', ['attendance' => $att->id]);

        $resp = $this->from($fromUrl)->post(route('user.request.store'), [
            'attendance_id' => $att->id,
            'date'          => '2025-10-22',
            'clock_in'      => '09:05',
            'clock_out'     => '18:00',
            'breaks'        => [],
            'note'          => '', // 未入力
        ]);

        $resp->assertRedirect($fromUrl);
        $resp->assertSessionHasErrors(['note' => '備考を記入してください']);
    }

    /**
     * 成功系：申請が作成され、一覧（承認待ち）に表示される
     */
    public function test_submit_correction_creates_pending_request_and_list_shows_it()
    {
        $user = $this->actingUser();
        $att  = $this->makeAttendance($user);

        $payload = [
            'attendance_id' => $att->id,
            'date'          => '2025-10-22',
            'clock_in'      => '09:10',
            'clock_out'     => '18:30',
            'breaks'        => [
                ['start' => '12:00', 'end' => '12:20'],
            ],
            'note'          => '遅延のため修正お願いします',
        ];

        $this->post(route('user.request.store'), $payload)
            ->assertRedirect(route('request.list'));

        // 一覧ページ表示
        $list = $this->get(route('request.list'));
        $list->assertOk();
        $list->assertSee('承認待ち');                  // タブ/ステータス
        $list->assertSee('遅延のため修正お願いします'); // 備考
        $list->assertSee('テスト太郎');               // 申請者名

        // DB 生成の最低限担保
        $this->assertDatabaseHas('stamp_correction_requests', [
            'user_id'       => $user->id,
            'attendance_id' => $att->id,
            'status'        => 'pending',
            'reason'        => '遅延のため修正お願いします',
            'target_date'   => '2025-10-22',
        ]);
    }

    /**
     * 成功系：申請一覧の「詳細」リンクから勤怠詳細へ遷移できる
     * （attendance_id があれば ID 詳細、無ければ by_date 詳細）
     */
    public function test_each_request_detail_link_navigates_to_attendance_detail()
    {
        $user = $this->actingUser();
        $att  = $this->makeAttendance($user, '2025-10-22', '09:00:00', '18:00:00');

        // 申請を1件作成（ID紐づき）
        $this->post(route('user.request.store'), [
            'attendance_id' => $att->id,
            'date'          => '2025-10-22',
            'clock_in'      => '09:05',
            'clock_out'     => '18:10',
            'breaks'        => [],
            'note'          => '修正テスト',
        ])->assertRedirect(route('request.list'));

        $list = $this->get(route('request.list'));
        $list->assertOk();
        $html = $list->getContent();

        $byIdUrl   = route('user.attendance.show', ['attendance' => $att->id]);
        $byDateUrl = route('user.attendance.show.by_date', ['date' => '2025-10-22']);

        $this->assertTrue(
            (str_contains($html, $byIdUrl) || str_contains($html, $byDateUrl)),
            '申請一覧に勤怠詳細へのリンクが見つかりません（byId: '.$byIdUrl.' / byDate: '.$byDateUrl.'）'
        );

        $target = str_contains($html, $byIdUrl) ? $byIdUrl : $byDateUrl;
        $detail = $this->get($target);
        $detail->assertOk();
    }
}
