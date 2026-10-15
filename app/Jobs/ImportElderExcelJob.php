<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Spatie\SimpleExcel\SimpleExcelReader;
use App\Models\User;
use App\Models\UserDetail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ImportElderExcelJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public string $pathToFile)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info("Elder importation started");
        $rows = SimpleExcelReader::create($this->pathToFile)->getRows();
        $rows->each(function (array $rowProperties) {
            try {
                DB::beginTransaction();
                Log::info("Importation of the data of " . $rowProperties['last_name']);
                $names = explode(" ", $rowProperties['last_name']);
                foreach ($names as $name) {
                    $dplUsr = UserDetail::where('last_name', $name)->first();
                    if ($dplUsr) {
                        break;
                    }
                }
                $last_nm = $names[0];
                unset($names[0]);
                $first_nm = implode(" ", $names);
                $usr = User::where('email', $rowProperties['email'])->first();
                $phoneNbr = $rowProperties['phone'];
                $rnNm = $rowProperties['last_name'];
                $usrDtl = UserDetail::where(function ($q) use ($phoneNbr, $rnNm) {
                    $q->where('phone', "like", "%$phoneNbr%")
                        ->orWhere('first_name', "like", "%$rnNm")
                        ->orWhere('last_name', "like", "$rnNm%");
                })->first();
                if (!$usr && !$usrDtl && !$dplUsr) {
                    $user_details = UserDetail::create([
                        "phone" => $rowProperties['phone'],
                        "first_name"    => $first_nm,
                        "last_name"    => $last_nm,
                        "promotion_name" => $rowProperties['promotion_year'],
                        "nearest_city" => $rowProperties['nearest_city'],
                        "my_job" => $rowProperties['my_job'],
                        "espa_field" => $rowProperties['espa_field'],
                        "social_network_id" => $rowProperties['social_network_id'],
                        "acu_previous_occupation" => $rowProperties['acu_previous_occupation'],
                        "first_message" => $rowProperties['first_message'],
                    ]);

                    $user = User::create([
                        'name' => $rowProperties['last_name'],
                        'email' => $rowProperties['email'],
                        'password' => Hash::make(random_int(10000000, 99999999)),
                        'must_change_password' => true,
                        'user_detail_id' => $user_details->id
                    ]);

                    $user->assignRole('ancien');
                } else {
                    Log::info($rowProperties['last_name'] . " User already exists");
                }
                DB::commit();
            } catch (\Exception $th) {
                Log::error($th->getMessage());
                DB::rollBack();
            }
        });
        Log::info("Elder importation finished ");
    }
}
