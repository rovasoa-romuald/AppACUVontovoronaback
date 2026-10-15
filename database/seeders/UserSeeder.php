<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            'admin',
            'aumonie',
            'aine_spirituel',
            'universitaire',
            'chretien',
            'president_acu',
            'president_communaute',
            'ancien',
            'secretaire',
            'commissaire_compte',
            'user',
            'donateur',
        ];

        foreach ($roles as $role) {
            if (! Role::where('name', $role)->exists()) {
                Role::create(['name' => $role]);
            }
        }

        $user = User::where('email', 'sacre.coeur@acut-vontovorona.org')->first();

        if (! $user) {
            User::factory()->create(
                [
                    'name'                 => 'admin',
                    'email'                => 'sacre.coeur@acut-vontovorona.org',
                    'email_verified_at'    => now(),
                    'password'             => Hash::make('password'),
                    'must_change_password' => false,
                ]
            )->assignRole('admin');
        }
    }
}
