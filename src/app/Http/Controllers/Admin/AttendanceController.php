<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateAttendanceRequest;
use App\Models\Attendance;
use App\Models\StampCorrectionRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller
{
    // 勤怠一覧を表示
    public function index(Request $request)
    {
        $date = $request->input('date')
            ? Carbon::parse($request->input('date'))
            : Carbon::today();

        // 当日の勤怠を取得（ユーザー＆休憩を事前ロード）
        $attendances = Attendance::with(['user', 'breakTimes'])
            ->whereDate('work_date', $date)
            ->orderBy('user_id')
            ->get();

        // 全ユーザー取得（一般ユーザー）
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

        // 前日・翌日ボタン用の日付
        $prevDate = $date->copy()->subDay()->toDateString();
        $nextDate = $date->copy()->addDay()->toDateString();

        return view('admin.attendance.index', [
            'date'     => $date,
            'rows'     => $rows,
            'prevDate' => $prevDate,
            'nextDate' => $nextDate,
        ]);
    }

    // 勤怠詳細（表示だけなのでバリデ不要）
    public function show(Attendance $attendance)
    {
        // attendance_id で申請を取得
        $requestRec = StampCorrectionRequest::where('attendance_id', $attendance->id)
            ->latest('created_at')
            ->first();

        // 対象日の決定順：work_date → 申請 target_date → clock_in → now()
        if ($attendance->work_date) {
            $date = $attendance->work_date->copy()->startOfDay();
        } elseif ($requestRec?->target_date) {
            $date = Carbon::parse($requestRec->target_date)->startOfDay();
        } elseif ($attendance->clock_in) {
            $date = $attendance->clock_in->copy()->startOfDay();
        } else {
            $date = now()->startOfDay(); // 最後の保険
        }

        $breaks = $attendance->breakTimes
            ? $attendance->breakTimes->sortBy('break_start')->values()
            : collect();

        return view('admin.attendance.show', [
            'attendance' => $attendance,
            'breaks'     => $breaks,
            'date'       => $date,
        ]);
    }

    // 勤怠修正（管理者修正は必ず備考必須にしたい → 専用FormRequest）
    public function update(UpdateAttendanceRequest $request, Attendance $attendance)
    {
        $admin = Auth::user();
        $date  = Carbon::parse($request->input('date', $attendance->clock_in?->toDateString() ?? now()->toDateString()));

        $toDT = function (?string $hm) use ($date) {
            if (!$hm) return null;
            [$h, $m] = explode(':', $hm);
            return $date->copy()->setTime((int) $h, (int) $m);
        };

        $breaks = collect($request->input('breaks', []));

        DB::transaction(function () use ($request, $attendance, $admin, $date, $toDT, $breaks) {
            // 1) 履歴を残す（管理者修正＝確定扱い）
            StampCorrectionRequest::create([
                'user_id'             => $attendance->user_id,
                'attendance_id'       => $attendance->id,
                'target_date'         => $date->toDateString(),
                'requested_clock_in'  => $toDT($request->input('clock_in')),
                'requested_clock_out' => $toDT($request->input('clock_out')),
                'requested_breaks'    => $breaks->map(fn ($b) => [
                    'start' => isset($b['start']) ? $toDT($b['start'])?->toDateTimeString() : null,
                    'end'   => isset($b['end'])   ? $toDT($b['end'])?->toDateTimeString()   : null,
                ])->filter(fn ($b) => $b['start'] || $b['end'])->values()->all(),
                'reason'              => $request->input('note'), // 必須（FormRequestで担保）
                'status'              => 'approved',
                'approved_by'         => $admin->id,
                'approved_at'         => now(),
            ]);

            // 2) 勤怠実データ更新
            $attendance->update([
                'clock_in'  => $toDT($request->input('clock_in')),
                'clock_out' => $toDT($request->input('clock_out')),
            ]);

            // 既存休憩を全削除→入れ直し（start/end 両方埋まっている行のみ）
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
        });

        // 成功時の戻り先振り分け
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
        // デフォルトは詳細に留まる
        return redirect()
            ->route('admin.attendance.show', $attendance)
        ->with('status', '勤怠を修正しました');
    }
}
