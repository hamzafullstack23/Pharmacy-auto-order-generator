<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedUsers();

        // Load real reference data (suppliers, companies, medicines, medicine_supplier)
        $this->call([
            ReferenceDataSeeder::class,
        ]);

        $this->command->newLine();
        $this->command->info('Database seeded successfully.');
        $this->command->info('Admin users:');
        $this->command->info('  hamzafullstack23@gmail.com');
        $this->command->info('  hannan.ali0071@gmail.com');
    }

    protected function seedUsers(): void
    {
        $users = [
            [
                'name'     => 'Hamza Ali',
                'email'    => 'hamzafullstack23@gmail.com',
                'password' => 'a1b2c3d4',
            ],
            [
                'name'     => 'Hannan Ali',
                'email'    => 'hannan.ali0071@gmail.com',
                'password' => 'a1b2c3d4',
            ],
        ];

        foreach ($users as $user) {
            User::firstOrCreate(
                ['email' => $user['email']],
                [
                    'name'     => $user['name'],
                    'password' => Hash::make($user['password']),
                ]
            );
        }
    }
}