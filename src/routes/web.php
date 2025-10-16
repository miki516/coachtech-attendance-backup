<?php

use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\User\AttendanceController as UserAttendanceController;
use App\Http\Controllers\User\BreakController;
use App\Http\Controllers\User\StampCorrectionRequestController as UserRequestController;
use App\Http\Controllers\Admin\AdminLoginController;
use App\Http\Controllers\Admin\AttendanceController as AdminAttendanceController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Admin\StampCorrectionRequestController as AdminRequestController;

// ==============================
// 会員登録（FormRequest対応版）
// ==============================
Route::post('/register', [RegisterController::class, 'store'])->name('register');

// ==============================
// ログイン・ログアウト
// ==============================
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// ==============================
// メール認証関連
// ==============================
Route::middleware('auth')->group(function () {
    Route::get('/email/verify', fn () => view('auth.verify-email'))
        ->name('verification.notice');

    Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
        $request->fulfill();
        return redirect()->route('login');
    })->middleware('signed')->name('verification.verify');

    Route::post('/email/verification-notification', function (Request $request) {
        $request->user()->sendEmailVerificationNotification();
    })->middleware('throttle:6,1')->name('verification.send');
});

// ==============================
// 一般ユーザー
// ==============================
Route::middleware(['auth', 'verified'])->group(function () {

// 出勤登録
Route::get('/attendance', [UserAttendanceController::class, 'showPunch'])
    ->name('user.attendance.punch');

Route::post('/attendance', [UserAttendanceController::class, 'storePunch'])
    ->name('user.attendance.clockin');

// 退勤
Route::post('/attendance/{attendance}/clockout', [UserAttendanceController::class, 'clockOut'])
    ->name('user.attendance.clockout');

// 休憩開始
Route::post('/attendance/{attendance}/break-in', [BreakController::class, 'store'])
    ->name('user.attendance.break.in');

// 休憩終了
Route::post('/attendance/{attendance}/break-out', [BreakController::class, 'close'])
    ->name('user.attendance.break.out');

// 勤怠一覧
Route::get('/attendance/list', [UserAttendanceController::class, 'index'])
    ->name('user.attendance.index');

// 勤怠詳細
Route::get('/attendance/detail/{date}', [UserAttendanceController::class, 'show'])
    ->where('date', '^\d{4}-\d{2}-\d{2}$')
    ->name('user.attendance.show');

// 勤怠修正申請
Route::post('/stamp_correction_request', [UserRequestController::class, 'store'])
    ->name('user.request.store');
});

// ==============================
// 管理者
// ==============================
// 管理者ログイン
Route::prefix('admin')->group(function () {
    Route::get('/login', [AdminLoginController::class, 'showLoginForm'])->name('admin.login');
    Route::post('/login', [AdminLoginController::class, 'login'])->name('admin.login.post');
});

// 管理者ログアウト（分岐用）
Route::post('/admin/logout', function (Request $request) {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();
    return redirect()->route('admin.login');
})->name('admin.logout');

Route::middleware(['auth', 'admin'])->prefix('admin')->group(function () {

    // 勤怠一覧
    Route::get('/attendance/list', [AdminAttendanceController::class, 'index'])
        ->name('admin.attendance.index');

    // 勤怠詳細
    Route::get('/attendance/{attendance}', [AdminAttendanceController::class, 'show'])
        ->whereNumber('attendance')
        ->name('admin.attendance.show');

    // スタッフ一覧
    Route::get('/staff/list', [StaffController::class, 'index'])
        ->name('admin.staff.index');

    // スタッフ別勤怠一覧
    Route::get('/attendance/staff/{staff}', [StaffController::class, 'show'])
        ->whereNumber('staff')
        ->name('admin.staff.show');

    // 修正申請承認
    Route::get('/stamp_correction_request/approve/{attendance_correct_request_id}',
        [AdminRequestController::class, 'approval'])
        ->whereNumber('attendance_correct_request_id')
        ->name('admin.request.approval');
});

// =====================================
// 「申請一覧」は一般/管理で同じパスを使う
// =====================================
Route::middleware(['auth'])->get('/stamp_correction_request/list', function (Request $request) {
    $role = $request->user()?->role;
    return ($role === 'admin')
        ? app(AdminRequestController::class)->index($request)
        : app(UserRequestController::class)->index($request);
})->name('request.list');