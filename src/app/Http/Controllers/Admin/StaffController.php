<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Attendance;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StaffController extends Controller
{
    // スタッフ一覧（変更なし）
    public function index(Request $request)
    {
        $staff = User::where('role', 'user')
            ->orderBy('id')
            ->get();

        return view('admin.staff.index', compact('staff'));
    }

    // 指定スタッフの月次勤怠一覧
    public function show(Request $request, User $staff)
    {
        abort_unless($staff->role === 'user', 404);

        // ?month=YYYY-MM（なければ今月）
        $monthStr = $request->query('month');
        $cursor   = $monthStr
            ? Carbon::createFromFormat('Y-m-d', "{$monthStr}-01")
            : now();

        $start = $cursor->copy()->startOfMonth();
        $end   = $cursor->copy()->endOfMonth();

        // 当月の勤怠（休憩含む）を work_date ベースで取得
        $byDate = Attendance::with('breakTimes')
            ->where('user_id', $staff->id)
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('work_date')
            ->get()
            ->keyBy(fn($a) => $a->work_date->toDateString());

        // 行生成
        $rows = [];
        foreach (CarbonPeriod::create($start, $end) as $day) {
            $key = $day->toDateString();
            $rec = $byDate[$key] ?? null;

            $breakMin = 0;
            if ($rec && $rec->relationLoaded('breakTimes')) {
                $breakMin = $rec->breakTimes->sum(function ($b) {
                    return ($b->break_start && $b->break_end)
                        ? $b->break_end->diffInMinutes($b->break_start)
                        : 0;
                });
            }

            $workMin = ($rec && $rec->clock_in && $rec->clock_out)
                ? max($rec->clock_out->diffInMinutes($rec->clock_in) - $breakMin, 0)
                : 0;

            $rows[] = [
                'day'       => $day->copy(),
                'rec'       => $rec,
                'break_str' => $breakMin > 0 ? sprintf('%d:%02d', intdiv($breakMin,60), $breakMin%60) : null,
                'work_str'  => ($rec && $rec->clock_out)
                    ? sprintf('%d:%02d', intdiv($workMin,60), $workMin%60)
                    : null,
            ];
        }

        $prevMonth = $start->copy()->subMonth()->format('Y-m');
        $nextMonth = $end->copy()->addMonth()->format('Y-m');
        $nextDisabled = $cursor->isSameMonth(now());

        return view('admin.staff.show', compact(
            'staff', 'rows', 'cursor', 'prevMonth', 'nextMonth', 'nextDisabled'
        ));
    }

    public function exportCsv(Request $request, User $staff)
    {
        $monthStr = $request->query('month');
        $cursor   = $monthStr
            ? Carbon::createFromFormat('Y-m-d', "{$monthStr}-01")
            : now();

        $start = $cursor->copy()->startOfMonth();
        $end   = $cursor->copy()->endOfMonth();

        // CSVレスポンス生成
        $response = new StreamedResponse(function () use ($staff, $start, $end) {
            $handle = fopen('php://output', 'w');
            // ヘッダー行
            fputcsv($handle, ['日付', '出勤', '退勤', '休憩時間', '合計時間']);

            $attendances = Attendance::with('breakTimes')
                ->where('user_id', $staff->id)
                ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
                ->orderBy('work_date')
                ->get();

            foreach ($attendances as $att) {
                $breakMin = $att->breakTimes->sum(function ($b) {
                    return ($b->break_start && $b->break_end)
                        ? $b->break_end->diffInMinutes($b->break_start)
                        : 0;
                });

                $workMin = ($att->clock_in && $att->clock_out)
                    ? max($att->clock_out->diffInMinutes($att->clock_in) - $breakMin, 0)
                    : 0;

                fputcsv($handle, [
                    $att->work_date->format('Y/m/d'),
                    $att->clock_in?->format('H:i') ?? '',
                    $att->clock_out?->format('H:i') ?? '',
                    $breakMin > 0 ? sprintf('%d:%02d', intdiv($breakMin,60), $breakMin%60) : '',
                    $workMin > 0 ? sprintf('%d:%02d', intdiv($workMin,60), $workMin%60) : '',
                ]);
            }

            fclose($handle);
        });

        $fileName = "{$staff->name}_{$cursor->format('Y_m')}_attendance.csv";
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', "attachment; filename=\"$fileName\"");

        return $response;
    }
}
