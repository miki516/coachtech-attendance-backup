<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'clock_in',
        'clock_out',
    ];

    protected $casts = [
        'clock_in' => 'datetime',
        'clock_out' => 'datetime',
    ];

    public function scopeForMonth($q, Carbon $start, Carbon $end) {
        return $q->whereBetween('clock_in', [$start->startOfDay(), $end->endOfDay()]);
    }

    public function user() {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function breakTimes() {
        return $this->hasMany(BreakTime::class, 'attendance_id');
    }

    public function stampCorrectionRequests() {
        return $this->hasMany(StampCorrectionRequest::class, 'attendance_id');
    }
}
