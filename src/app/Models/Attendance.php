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
        'work_date',
        'clock_in',
        'clock_out',
    ];

    protected $casts = [
        'work_date' => 'date',
        'clock_in'  => 'datetime',
        'clock_out' => 'datetime',
    ];

    // 保険：clock_in があり work_date が未設定なら自動補完
    protected static function booted()
    {
        static::creating(function (self $m) {
            if (!$m->work_date && $m->clock_in) {
                $m->work_date = $m->clock_in->toDateString();
            }
        });
    }

    // 月範囲の抽出は work_date ベースに（NULLの clock_in に依存しない）
    public function scopeForMonth($q, Carbon $start, Carbon $end)
    {
        return $q->whereBetween('work_date', [
            $start->toDateString(),
            $end->toDateString(),
        ]);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function breakTimes()
    {
        return $this->hasMany(BreakTime::class, 'attendance_id');
    }

    public function stampCorrectionRequests()
    {
        return $this->hasMany(StampCorrectionRequest::class, 'attendance_id');
    }
}
