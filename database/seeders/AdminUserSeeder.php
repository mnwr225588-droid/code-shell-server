<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@codeshell.com'],
            [
                'name' => 'Code Shell Admin',
                'phone' => '01000000000',
                'password' => Hash::make('Admin@123456'),
                'is_admin' => true, // إذا كان لديك عمود للتمييز
            ]
        );
    }
}