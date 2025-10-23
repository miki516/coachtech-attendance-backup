<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginUserTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // 日本語メッセージ固定（任意）
        config(['app.locale' => 'ja']);
    }

    /**
     * メールアドレスが未入力の場合、バリデーションメッセージが表示される
     */
    public function test_email_is_required_on_login()
    {
        $response = $this->from('/login')->post('/login', [
            'email' => '',
            'password' => 'password123',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors([
            'email' => 'メールアドレスを入力してください',
        ]);
        $this->assertGuest();
    }

    /**
     * パスワードが未入力の場合、バリデーションメッセージが表示される
     */
    public function test_password_is_required_on_login()
    {
        $response = $this->from('/login')->post('/login', [
            'email' => 'test@example.com',
            'password' => '',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors([
            'password' => 'パスワードを入力してください',
        ]);
        $this->assertGuest();
    }

    /**
     * 登録内容と一致しない場合、エラーメッセージが表示される
     */
    public function test_login_fails_with_wrong_credentials_and_message()
    {
        // 正しいユーザーを用意
        User::factory()->create([
            'name' => 'テスト太郎',
            'email' => 'real@example.com',
            'password' => Hash::make('password123'),
        ]);

        // わざと誤ったメール or パスワードでログイン
        $response = $this->from('/login')->post('/login', [
            'email' => 'wrong@example.com', // 誤り
            'password' => 'password123',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors([
            'email' => 'ログイン情報が登録されていません',
        ]);
        $this->assertGuest();
    }

    /**
     * 正しい資格情報でログインできる
     */
    public function test_login_succeeds_with_correct_credentials()
    {
        $user = User::factory()->create([
            'name' => 'テスト太郎',
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertStatus(302);
    }
}
