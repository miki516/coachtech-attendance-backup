<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAttendancesTable extends Migration
{
    public function up()
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();

            // ユーザーとの紐づけ
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // 「その日の勤怠」を一意に特定するための日付キー（勤務が無くても必ず持てる列）
            $table->date('work_date');

            // 出勤・退勤時刻（無い日もあるので nullable）
            $table->dateTime('clock_in')->nullable();
            $table->dateTime('clock_out')->nullable();

            $table->timestamps();

            // 同一ユーザー×同一日で1レコードにするためのユニーク制約
            $table->unique(['user_id', 'work_date']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('attendances');
    }
}
