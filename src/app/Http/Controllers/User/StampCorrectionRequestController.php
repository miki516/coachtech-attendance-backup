<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class StampCorrectionRequestController extends Controller
{
    // 勤怠修正申請を作成
    public function store(Request $request)
    {
        $user = Auth::user();

        StampCorrectionRequest::create([
            'user_id'  => $user->id,
            'attendance_id' => $attendance->id,
            'requested_clock_in' =>
            'requested_clock_out' =>
            'reason'
            'status' => '承認待ち'

        ]);
    }

}
