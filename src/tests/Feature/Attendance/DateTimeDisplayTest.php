<?php

namespace Tests\Feature\Ui;

use Tests\TestCase;
use Carbon\Carbon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DateTimeDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_now_datetime_is_shown_in_expected_format_on_punch_screen()
    {
        // タイムゾーン固定
        config(['app.timezone' => 'Asia/Tokyo']);

        // 認証が必要なので、ログイン済み＆メール認証済みユーザーを用意
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'email_verified_at' => now(), // 画面が verified ミドルウェアなら必須
            'role' => 'user',
        ]);
        $this->actingAs($user);

        // 表示のぶれを防ぐため分まで固定
        $fixed = Carbon::create(2025, 10, 22, 9, 30, 0, 'Asia/Tokyo');
        Carbon::setTestNow($fixed);

        // 画面取得
        $response = $this->get(route('user.attendance.clockin'));
        $response->assertOk();

        // Blade実装どおりに一致確認
        $expectedDate = $fixed->locale('ja')->isoFormat('YYYY年M月D日（ddd）');
        $expectedTime = $fixed->format('H:i');

        $response->assertSee($expectedDate);
        $response->assertSee($expectedTime);
    }
}
