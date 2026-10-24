<?php

use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('rental:admin', function () {
    $name = $this->ask('Administrator name');
    $email = $this->ask('Administrator email');
    $password = $this->secret('Password (12+ characters, mixed case, number and symbol)');
    $v = Validator::make(compact('name', 'email', 'password'), ['name' => 'required|string|max:150', 'email' => 'required|email|unique:users,email', 'password' => ['required', Password::min(12)->mixedCase()->numbers()->symbols()]]);
    if ($v->fails()) {
        foreach ($v->errors()->all() as $error) {
            $this->error($error);
        }

        return 1;
    }
    User::create(compact('name', 'email', 'password') + ['role' => 'admin', 'active' => true, 'must_change_password' => true]);
    $this->info('Administrator created. Sign in and change the temporary password.');
})->purpose('Create an administrator securely without public registration');

Artisan::command('rental:reset-password {email}', function (string $email) {
    $user = User::where('email', $email)->first();
    if (! $user) {
        $this->error('Account not found.');

        return 1;
    }$password = $this->secret('New temporary password');
    $v = Validator::make(['password' => $password], ['password' => ['required', Password::min(12)->mixedCase()->numbers()->symbols()]]);
    if ($v->fails()) {
        $this->error($v->errors()->first());

        return 1;
    }$user->update(['password' => $password, 'must_change_password' => true]);
    DB::table('sessions')->where('user_id', $user->id)->delete();
    $this->info('Password reset. Existing sessions revoked.');
})->purpose('Recover an account password from the trusted local console');
