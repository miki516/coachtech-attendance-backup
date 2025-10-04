<?php

namespace App\Models;

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
