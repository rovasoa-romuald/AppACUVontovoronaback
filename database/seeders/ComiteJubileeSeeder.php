<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use App\Models\UserDetail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ComiteJubileeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $emails = [
            "dassiseolivier@gmail.com",
            "mosajalex@gmail.com",
            "rojoolyvia@gmail.com",
            "haingotinadaniella@gmail.com",
            "mirindralarissa@gmail.com",
            "jorandriambololona@gmail.com",
            "ririniarivomi@gmail.com",
            "randriamihajawillyjb@gmail.com",
            "randrianarivelo.francky@gmail.com",
        ];
        foreach ($emails as $email) {
            try {
                DB::beginTransaction();
                $user = User::where('email', $email)->first();
                if (! $user) {
                    $user_details = UserDetail::create([
                        'first_name' => 'A modifier',
                        'last_name' => 'A modifier',
                        'phone' => 'A modifier',
                    ]);

                    $usr = User::factory()->create(
                        [
                            'user_detail_id'       => $user_details->id,
                            'name'                 => 'A modifier',
                            'email'                => $email,
                            'email_verified_at'    => now(),
                            'password'             => Hash::make('password'),
                            'must_change_password' => false,
                        ]
                    );
                    $usr->assignRole('ancien');
                } else {
                    User::where('email', $email)->update([
                        'email_verified_at'    => now(),
                        'password'             => Hash::make('password'),
                        'must_change_password' => false,
                    ]);
                }
                DB::commit();
            } catch (\Exception $e) {
                Log::error($e->getMessage());
                DB::roolback();
            }
        }
    }
}
