<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $userWithFictiveEmail = \App\Models\User::where("email", "like", "%ficitif%")->get();
        try {
            DB::beginTransaction();
            foreach ($userWithFictiveEmail as $user) {
                $user->email = str_replace("ficitif", "fictif", $user->email);
                $user->save();
            }
            $enveloppes = \App\Models\ContributionEnvelope::get();
            foreach ($enveloppes as $envelope) {
                $actual_status = $envelope->status;
                if (!$envelope->{$actual_status} && $actual_status != "to_be_delivered") {
                    $envelope->{$actual_status . "_at"} = $envelope->updated_at;
                    $envelope->save();
                }
            }
            DB::commit();
        } catch (\Exception $th) {
            DB::rollBack();
            Log::error($th);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
