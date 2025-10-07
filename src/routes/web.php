<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\User\AttendanceController as UserAttendanceController;
use App\Http\Controllers\User\StampCorrectionRequestController as UserRequestController;
use App\Http\Controllers\Admin\AdminLoginController;
use App\Http\Controllers\Admin\AttendanceController as AdminAttendanceController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Admin\StampCorrectionRequestController as AdminRequestController;

// ==============================
// 一般ユーザー
// ==============================

// 出勤登録
Route::get('/attendance', [UserAttendanceController::class, 'register'])->name('user.attendance.register');

// 勤怠一覧
Route::get('/attendance/list', [UserAttendanceController::class, 'index'])->name('user.attendance.index');

// 勤怠詳細
Route::get('/attendance/detail/{id}', [UserAttendanceController::class, 'show'])->name('user.attendance.show');

// 申請一覧
Route::get('/stamp_correction_request/list', [UserRequestController::class, 'index'])->name('user.request.index');


// ==============================
// 管理者
// ==============================

Route::prefix('admin')->group(function () {
    // 管理ログイン
    Route::get('/login', [AdminLoginController::class, 'showLoginForm'])->name('admin.login');
});

Route::middleware('admin')->prefix('admin')->group(function () {
    Route::post('/login', [AdminLoginController::class, 'login'])->name('admin.login.post');

    // 勤怠一覧
    Route::get('/attendance/list', [AdminAttendanceController::class, 'index'])->name('admin.attendance.index');

    // 勤怠詳細
    Route::get('/attendance/{id}', [AdminAttendanceController::class, 'detail'])->name('admin.attendance.show');

    // スタッフ一覧
    Route::get('/staff/list', [StaffController::class, 'index'])->name('admin.staff.index');

    // スタッフ別勤怠一覧
    Route::get('/attendance/staff/{id}', [StaffController::class, 'show'])->name('admin.staff.show');

    // 申請一覧
    Route::get('/stamp_correction_request/list', [AdminRequestController::class, 'index'])->name('admin.request.index');

    // 修正申請承認
    Route::get('/stamp_correction_request/approve/{attendance_correct_request_id}', [AdminRequestController::class, 'approval'])->name('admin.request.approval');
});
