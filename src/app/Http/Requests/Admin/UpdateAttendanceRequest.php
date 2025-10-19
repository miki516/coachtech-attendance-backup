<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class UpdateAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'date'           => ['required', 'date_format:Y-m-d'],
            'attendance_id'  => ['nullable', 'integer'],
            'clock_in'       => ['nullable', 'date_format:H:i'],
            'clock_out'      => ['nullable', 'date_format:H:i'],
            'breaks'         => ['nullable', 'array'],
            'breaks.*.start' => ['nullable', 'date_format:H:i'],
            'breaks.*.end'   => ['nullable', 'date_format:H:i'],
            'note'           => ['required', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'date.required'              => '対象日付が不正です',
            'date.date_format'           => '対象日付の形式が不正です',
            'clock_in.date_format'       => '出勤はHH:mm形式で入力してください',
            'clock_out.date_format'      => '退勤はHH:mm形式で入力してください',
            'breaks.*.start.date_format' => '休憩はHH:mm形式で入力してください',
            'breaks.*.end.date_format'   => '休憩はHH:mm形式で入力してください',
            'note.required'              => '備考を記入してください',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $date = Carbon::createFromFormat('Y-m-d', $this->input('date'));

            $toDT = function (?string $t) use ($date) {
                if (!$t) return null;
                [$h,$m] = explode(':', $t);
                return $date->copy()->setTime((int)$h,(int)$m);
            };

            $in  = $toDT($this->input('clock_in'));
            $out = $toDT($this->input('clock_out'));

            if ($in && $out && $in->gt($out)) {
                $v->errors()->add('clock_in', '出勤時間もしくは退勤時間が不適切な値です');
            }

            foreach (collect($this->input('breaks', [])) as $i => $b) {
                $bs = $toDT($b['start'] ?? null);
                $be = $toDT($b['end']   ?? null);

                if ($bs && $in && $bs->lt($in)) {
                    $v->errors()->add("breaks.$i.start", '休憩時間が不適切な値です');
                }
                if ($bs && $out && $bs->gt($out)) {
                    $v->errors()->add("breaks.$i.start", '休憩時間が不適切な値です');
                }
                if ($be && $out && $be->gt($out)) {
                    $v->errors()->add("breaks.$i.end", '休憩時間もしくは退勤時間が不適切な値です');
                }
                if ($bs && $be && $be->lt($bs)) {
                    $v->errors()->add("breaks.$i.end", '休憩時間が不適切な値です');
                }
            }
        });
    }
}

