<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StampCorrectionRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'attendance_id',
        'target_date',
        'requested_clock_in',
        'requested_clock_out',
        'requested_breaks',
        'reason',
        'status',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'target_date'         => 'date',
        'requested_clock_in'  => 'datetime',
        'requested_clock_out' => 'datetime',
        'requested_breaks'    => 'array',
        'approved_at'         => 'datetime',
    ];

    public function user() {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function attendance() {
    return $this->belongsTo(Attendance::class, 'attendance_id');
    }
}
