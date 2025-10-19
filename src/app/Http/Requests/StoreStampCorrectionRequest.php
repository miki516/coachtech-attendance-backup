<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class StoreStampCorrectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // 時刻文字列（例 "09:30"）を Carbon に変換
            $convertToDateTime = function ($time) {
                return $time ? Carbon::createFromTimeString($time) : null;
            };

            $clockInTime  = $convertToDateTime($this->input('clock_in'));
            $clockOutTime = $convertToDateTime($this->input('clock_out'));

            // 出勤・退勤の前後チェック
            if ($clockInTime && $clockOutTime && $clockInTime->gt($clockOutTime)) {
                $validator->errors()->add('clock_in', '出勤時間もしくは退勤時間が不適切な値です');
            }

            // 休憩時間のチェック
            $breakTimes = collect($this->input('breaks', []));
            foreach ($breakTimes as $index => $break) {
                $breakStart = $convertToDateTime($break['start'] ?? null);
                $breakEnd   = $convertToDateTime($break['end'] ?? null);

                // 出勤・退勤が両方未入力なら比較スキップ
                if (!$clockInTime && !$clockOutTime) continue;

                // 休憩開始が出勤より前
                if ($breakStart && $clockInTime && $breakStart->lt($clockInTime)) {
                    $validator->errors()->add("breaks.$index.start", '休憩時間が不適切な値です');
                }

                // 休憩開始が退勤より後
                if ($breakStart && $clockOutTime && $breakStart->gt($clockOutTime)) {
                    $validator->errors()->add("breaks.$index.start", '休憩時間が不適切な値です');
                }

                // 休憩終了が退勤より後
                if ($breakEnd && $clockOutTime && $breakEnd->gt($clockOutTime)) {
                    $validator->errors()->add("breaks.$index.end", '休憩時間もしくは退勤時間が不適切な値です');
                }

                // 休憩終了が休憩開始より前（逆転）
                if ($breakStart && $breakEnd && $breakEnd->lt($breakStart)) {
                    $validator->errors()->add("breaks.$index.end", '休憩時間が不適切な値です');
                }
            }
        });
    }
}
