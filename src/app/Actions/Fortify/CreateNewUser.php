<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'name'                  => ['required', 'string', 'max:255'],
            'email'                 => ['required', 'string', 'email', 'unique:users,email'],
            'password'              => ['required', 'string', 'min:8'],
            'password_confirmation' => ['required', 'same:password'],
        ], [
            // 未入力
            'name.required'                  => 'お名前を入力してください',
            'email.required'                 => 'メールアドレスを入力してください',
            'password.required'              => 'パスワードを入力してください',
            // 規則
            'password.min'                   => 'パスワードは8文字以上で入力してください',
            // 確認用
            'password_confirmation.required' => 'パスワードと一致しません',
            'password_confirmation.same'     => 'パスワードと一致しません',
        ])->validate();

        $user = User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => Hash::make($input['password']),
        ]);

        event(new Registered($user));

        return $user;
    }
}
