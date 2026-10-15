<?php

namespace App\Http\Controllers\Auth;

use App\Events\ElderRegistered;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserDetail;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Support\Facades\Log;

class RegisteredUserController extends Controller
{
    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): Response
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:' . User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->string('password')),
        ]);

        event(new Registered($user));

        Auth::login($user);

        return response()->noContent();
    }

    /**
     * Handle an incoming registration request of elders.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function storeInfoElder(Request $request): Response
    {
        $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'civility' => ['required', 'string', 'in:Mademoiselle,Monsieur,Madame'],
            'last_name' => ['required', 'string', 'max:255'],
            'promotion_name' => ['string', 'max:255'],
            'promotion_year' => ['required', 'integer'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:' . User::class],
            'phone' => ['required', 'string', 'max:255'],
            'espa_field' => ['required', 'string', 'max:255'],
            'nearest_city' => ['required', 'string', 'max:255'],
            'acu_previous_occupation' => ['string', 'max:255', 'nullable'],
            'first_message' => ['string', 'max:1000', 'nullable'],
            'my_job' => ['string', 'max:255', 'nullable'],
            'social_network_id' => ['string', 'max:255', 'nullable']
        ]);

        try {
            DB::beginTransaction();
            $user_details = UserDetail::create([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'promotion_name' => $request->promotion_name,
                'promotion_year' => $request->promotion_year,
                'phone' => $request->phone,
                'espa_field' => $request->espa_field,
                'nearest_city' => $request->nearest_city,
                'acu_previous_occupation' => $request->acu_previous_occupation,
                'first_message' => $request->first_message,
                'civility' => $request->civility,
                'my_job' => $request->my_job,
                'social_network_id' => $request->social_network_id,
            ]);

            $user = User::create([
                'name' => $request->first_name . " " . $request->last_name,
                'email' => $request->email,
                'password' => Hash::make(random_int(10000000, 99999999)),
                'must_change_password' => true,
                'user_detail_id' => $user_details->id
            ]);

            $user->assignRole('ancien');
            DB::commit();
            Log::info("Sending email to elder...");
            event(new ElderRegistered($user));
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error when creating user :" . $e->getMessage());
        }

        return response()->noContent();
    }
}
