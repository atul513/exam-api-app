<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $users = [
            [
                'name'     => 'Super Admin',
                'email'    => 'superadmin@example.com',
                'password' => Hash::make('password'),
                'role'     => User::ROLE_SUPERADMIN,
            ],
            [
                'name'     => 'Admin User',
                'email'    => 'admin@example.com',
                'password' => Hash::make('password'),
                'role'     => User::ROLE_ADMIN,
            ],
            [
                'name'     => 'Teacher User',
                'email'    => 'teacher@example.com',
                'password' => Hash::make('password'),
                'role'     => User::ROLE_TEACHER,
            ],
            [
                'name'     => 'Student User',
                'email'    => 'student@example.com',
                'password' => Hash::make('password'),
                'role'     => User::ROLE_STUDENT,
            ],
            [
                'name'     => 'Parent User',
                'email'    => 'parent@example.com',
                'password' => Hash::make('password'),
                'role'     => User::ROLE_PARENT,
            ],
        ];

        foreach ($users as $userData) {
            User::firstOrCreate(
                ['email' => $userData['email']],
                $userData
            );
        }
    }
}
