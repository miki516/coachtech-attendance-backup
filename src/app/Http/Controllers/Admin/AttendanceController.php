<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateAttendanceRequest;
use App\Models\Attendance;
use App\Models\StampCorrectionRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AttendanceController extends Controller
{
    // 勤怠一覧を表示
    public function index(Request $request)
    {
        $date = $request->input('date')
            ? Carbon::parse($request->input('date'))
            : Carbon::today();

        // 当日の勤怠（ユーザー＆休憩を事前ロード）
        $attendances = Attendance::with(['user', 'breakTimes'])
            ->whereDate('work_date', $date)
            ->orderBy('user_id')
            ->get();

        // 全ユーザー（一般ユーザー）
        $users = User::where('role', 'user')->get();

        // 表示用に整形
        $rows = $users->map(function ($user) use ($attendances) {
            $att = $attendances->firstWhere('user_id', $user->id);

            $clockIn  = $att?->clock_in ? Carbon::parse($att->clock_in)->format('H:i') : '—';
            $clockOut = $att?->clock_out ? Carbon::parse($att->clock_out)->format('H:i') : '—';

            $totalBreakMin = $att?->breakTimes->sum(function ($b) {
                return $b->break_start && $b->break_end
                    ? Carbon::parse($b->break_end)->diffInMinutes(Carbon::parse($b->break_start))
                    : 0;
            }) ?? 0;

            $breakTime = $totalBreakMin
                ? sprintf('%d:%02d', intdiv($totalBreakMin, 60), $totalBreakMin % 60)
                : '—';

            $totalWork = '—';
            if ($att?->clock_in && $att?->clock_out) {
                $totalMin = Carbon::parse($att->clock_out)->diffInMinutes(Carbon::parse($att->clock_in)) - $totalBreakMin;
                $totalMin = max($totalMin, 0);
                $totalWork = sprintf('%d:%02d', intdiv($totalMin, 60), $totalMin % 60);
            }

            return [
                'name'          => $user->name,
                'clock_in'      => $clockIn,
                'clock_out'     => $clockOut,
                'break'         => $breakTime,
                'total'         => $totalWork,
                'user_id'       => $user->id,
                'attendance_id' => $att?->id,
            ];
        });

        // 前日・翌日
        $prevDate = $date->copy()->subDay()->toDateString();
        $nextDate = $date->copy()->addDay()->toDateString();

        return view('admin.attendance.index', [
            'date'     => $date,
            'rows'     => $rows,
            'prevDate' => $prevDate,
            'nextDate' => $nextDate,
        ]);
    }

    // 勤怠詳細
    public function show(Attendance $attendance)
    {
        // attendance_id で申請を取得（対象日の補完用）
        $requestRec = StampCorrectionRequest::where('attendance_id', $attendance->id)
            ->latest('created_at')
            ->first();

        $isPending = $requestRec && $requestRec->status === 'pending';

        // 対象日の決定：work_date → 申請 target_date → clock_in → now()
        if ($attendance->work_date) {
            $date = $attendance->work_date->copy()->startOfDay();
        } elseif ($requestRec?->target_date) {
            $date = Carbon::parse($requestRec->target_date)->startOfDay();
        } elseif ($attendance->clock_in) {
            $date = $attendance->clock_in->copy()->startOfDay();
        } else {
            $date = now()->startOfDay();
        }

        $breaks = $attendance->breakTimes
            ? $attendance->breakTimes->sortBy('break_start')->values()
            : collect();

        return view('admin.attendance.show', compact(
            'attendance', 'breaks', 'date', 'isPending'
        ));
    }

    // 勤怠修正
    public function update(UpdateAttendanceRequest $request, Attendance $attendance)
    {
        $date = Carbon::parse(
            $request->input('date', $attendance->work_date?->toDateString()
                ?? $attendance->clock_in?->toDateString()
                ?? now()->toDateString())
        );

        $toDT = function (?string $hm) use ($date) {
            if (!$hm) return null;
            [$h, $m] = explode(':', $hm);
            return $date->copy()->setTime((int) $h, (int) $m);
        };

        $breaks = collect($request->input('breaks', []));

        // 1) 勤怠 上書き
        $attendance->update([
            'clock_in'  => $toDT($request->input('clock_in')),
            'clock_out' => $toDT($request->input('clock_out')),
        ]);

        // 2) 休憩は全削除→入れ直し（開始・終了が両方ある行のみ）
        $attendance->breakTimes()->delete();
        foreach ($breaks as $b) {
            $start = isset($b['start']) ? $toDT($b['start']) : null;
            $end   = isset($b['end'])   ? $toDT($b['end'])   : null;
            if ($start && $end) {
                $attendance->breakTimes()->create([
                    'break_start' => $start,
                    'break_end'   => $end,
                ]);
            }
        }

        // 戻り先
        $return = $request->input('return_to');
        if ($return === 'list') {
            return redirect()
                ->route('admin.attendance.index', ['date' => $request->input('context_date')])
                ->with('status', '勤怠を修正しました');
        }
        if ($return === 'staff') {
            return redirect()
                ->route('admin.staff.show', [
                    'staff' => $request->input('context_staff', $attendance->user_id),
                    'month' => $request->input('context_month'),
                ])
                ->with('status', '勤怠を修正しました');
        }

        return redirect()
            ->route('admin.attendance.show', $attendance)
            ->with('status', '勤怠を修正しました');
    }
}
