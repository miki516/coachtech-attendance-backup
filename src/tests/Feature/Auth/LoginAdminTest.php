<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // 日本語メッセージ固定
        config(['app.locale' => 'ja']);
    }

    /**
     * メールアドレスが未入力の場合、バリデーションメッセージが表示される
     */
    public function test_email_is_required_on_admin_login()
    {
        $response = $this->from('/admin/login')->post('/admin/login', [
            'email' => '',
            'password' => 'password123',
        ]);

        $response->assertRedirect('/admin/login');
        $response->assertSessionHasErrors([
            'email' => 'メールアドレスを入力してください',
        ]);
        $this->assertGuest();
    }

    /**
     * パスワードが未入力の場合、バリデーションメッセージが表示される
     */
    public function test_password_is_required_on_admin_login()
    {
        $response = $this->from('/admin/login')->post('/admin/login', [
            'email' => 'admin@example.com',
            'password' => '',
        ]);

        $response->assertRedirect('/admin/login');
        $response->assertSessionHasErrors([
            'password' => 'パスワードを入力してください',
        ]);
        $this->assertGuest();
    }

    /**
     * 登録内容と一致しない場合、エラーメッセージが表示される
     */
    public function test_admin_login_fails_with_wrong_credentials_and_message()
    {
        // 正しい管理者ユーザーを用意
        User::factory()->create([
            'name' => '管理者太郎',
            'email' => 'admin@example.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
        ]);

        // わざと誤ったメール／パスワードでログイン
        $response = $this->from('/admin/login')->post('/admin/login', [
            'email' => 'wrong@example.com',
            'password' => 'password123',
        ]);

        $response->assertRedirect('/admin/login');
        $response->assertSessionHasErrors([
            // カスタム日本語メッセージを使用している想定
            'email' => 'ログイン情報が登録されていません',
        ]);
        $this->assertGuest();
    }

    /**
     * 正しい資格情報で管理者としてログインできる
     */
    public function test_admin_login_succeeds_with_correct_credentials()
    {
        $admin = User::factory()->create([
            'name' => '管理者太郎',
            'email' => 'admin@example.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
        ]);

        $response = $this->post('/admin/login', [
            'email' => 'admin@example.com',
            'password' => 'password123',
        ]);

        // 認証されていること
        $this->assertAuthenticatedAs($admin);

        // 成功時のリダイレクト先
        $response->assertRedirect('/admin/attendance/list');
        $response->assertStatus(302);
    }

    /**
     * 一般ユーザーは管理者ログイン画面からは入れないことを担保
     */
    public function test_non_admin_cannot_login_via_admin_login_when_role_check_enabled()
    {
        $user = User::factory()->create([
            'name' => '一般ユーザー',
            'email' => 'user@example.com',
            'password' => Hash::make('password123'),
            'role' => 'user',
        ]);

        $response = $this->from('/admin/login')->post('/admin/login', [
            'email' => 'user@example.com',
            'password' => 'password123',
        ]);

        // roleで弾いて /admin/login に戻す
        $response->assertRedirect('/admin/login');
        $this->assertGuest();
    }
}
