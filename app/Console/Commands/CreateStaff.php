<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateStaff extends Command
{
    protected $signature = 'stock:create-staff';

    protected $description = 'Create an individually identified staff account using hidden password input';

    public function handle(): int
    {
        $input = [
            'name' => $this->ask('Name'),
            'email' => $this->ask('Email'),
            'password' => $this->secret('Password (at least 12 characters)'),
        ];
        $validator = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', Password::min(12)],
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }
        $manager = $this->confirm('May this account manage catalogue and configuration?', false);
        $user = new User($validator->validated());
        $user->forceFill(['is_staff' => true, 'is_manager' => $manager])->save();
        $this->info('Staff account created. Sign in at /admin.');

        return self::SUCCESS;
    }
}
