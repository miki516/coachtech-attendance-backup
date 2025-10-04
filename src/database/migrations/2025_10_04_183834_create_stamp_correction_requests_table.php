<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStampCorrectionRequestsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('stamp_correction_requests', function (Blueprint $table) {
            $table->id();

            // 申請したユーザー
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // 対象の勤怠データ
            $table->foreignId('attendance_id')->constrained('attendances');

            // 修正希望の打刻時刻
            $table->datetime('requested_clock_in')->nullable();
            $table->datetime('requested_clock_out')->nullable();

            // 修正理由（備考）
            $table->text('reason');

            // 申請ステータス
            $table->enum('status',['pending', 'approved', 'rejected'])->default('pending');

            // 承認者（管理者）
            $table->foreignId('approved_by')->nullable()->constrained('users');

            // 承認日時
            $table->timestamp('approved_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('stamp_correction_requests');
    }
}
