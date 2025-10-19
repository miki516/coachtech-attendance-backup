<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStampCorrectionRequestsTable extends Migration
{
    public function up()
    {
        Schema::create('stamp_correction_requests', function (Blueprint $table) {
            $table->id();

            // 申請したユーザー
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // 対象の勤怠データ（勤怠が削除されたら null）
            $table->foreignId('attendance_id')
                ->nullable()
                ->constrained('attendances')
                ->nullOnDelete();

            // 対象日（必須）
            $table->date('target_date');

            // 修正希望の打刻時刻
            $table->dateTime('requested_clock_in')->nullable();
            $table->dateTime('requested_clock_out')->nullable();

            // 修正希望の休憩配列
            $table->json('requested_breaks')->nullable();

            // 修正理由（備考）
            $table->text('reason');

            // 申請ステータス
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');

            // 承認者（管理者）— 管理者が削除されたら null
            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // 承認日時
            $table->timestamp('approved_at')->nullable();

            $table->timestamps();
        });
    }

    public function down()
    {
        // 作成したテーブルをそのまま落とせば十分
        Schema::dropIfExists('stamp_correction_requests');
    }
}
