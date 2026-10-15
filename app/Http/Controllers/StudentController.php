<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Level;
use App\Models\Major;
use App\Models\Census;
use App\Models\UserDetail;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;

class StudentController extends Controller
{
    public function getStudedntList(Request $request)
    {
        $pagination                    = $request->get('perPage', 10);
        $firstLastName                 = $request->get('firstLastName');
        // $promoYear                    = $request->get('promoYear');
        // $elderInfoOk                  = $request->get('elderInfoOk', null);
        // $isSpecial                    = $request->get('isSpecial', null);
        // $isHasResponsible             = $request->get('isHasResponsible', null);
        $users = User::whereHas("roles", function ($q) {
            $q->where("name",  "universitaire");
        })->when(!!$firstLastName, function ($q) use ($firstLastName) {
            $q->where("name", "like", "%$firstLastName%");
        })
            ->orderBy('created_at', 'asc')
            ->paginate($pagination)
            ->withQueryString()
            ->through(fn($user) => [
                "id" => $user->id,
                "email" => $user->email,
                "civility" => $user->detail?->civility,
                "last_name" => $user->detail?->last_name,
                "first_name" => $user->detail?->first_name
            ])->toArray();

        return response()->json($users);
    }

    public function getCensusContent(Request $request)
    {
        $pagination                    = $request->get('perPage', 10);
        $firstLastName                 = $request->get('firstLastName');
        // $promoYear                    = $request->get('promoYear');
        // $elderInfoOk                  = $request->get('elderInfoOk', null);
        // $isSpecial                    = $request->get('isSpecial', null);
        // $isHasResponsible             = $request->get('isHasResponsible', null);
        $users = User::whereHas("roles", function ($q) {
            $q->where("name",  "universitaire");
        })->when(!!$firstLastName, function ($q) use ($firstLastName) {
            $q->where("name", "like", "%$firstLastName%");
        })->paginate($pagination)
            ->withQueryString()
            ->through(function ($usr) {
                $cns = $usr->censuses()->where("census_year", Carbon::now()->year)->first();
                return [
                    "id" => $usr->id,
                    "full_name" => $usr->detail->civility . " " . $usr->detail->last_name . " " . $usr->detail->first_name,
                    "email" => $usr->email,
                    "phone" => $usr->detail?->phone,
                    "census_id" => $cns?->id,
                    "major_name" => $cns?->major->name,
                    "major_abbr" => $cns?->major->abbreviation,
                    "major_id" => $cns?->major->id,
                    "level_name" => $cns?->level->name,
                    "level_abbr" => $cns?->level->abbreviation,
                    "level_id" => $cns?->level->id,
                    "block" => $cns?->block,
                    "door_number" => $cns?->door_number,
                    "is_off_campus" => $cns?->is_off_campus,
                    "is_stay_weekend" => $cns?->is_stay_weekend,
                ];
            })->toArray();

        return response()->json($users);
    }
    public function signUpStudent(Request $request)
    {
        $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'civility' => ['required', 'string', 'in:Mademoiselle,Monsieur,Madame'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:' . User::class],
            'phone' => ['required', 'string', 'max:255'],
            'diocese' => ['nullable', 'string', 'max:255'],
            'social_network_id' => ['string', 'max:255', 'nullable']
        ]);

        try {
            DB::beginTransaction();
            $user_details = UserDetail::create([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'phone' => $request->phone,
                'civility' => $request->civility,
                'diocese_of_origin' => $request->diocese,
                'social_network_id' => $request->social_network_id,
            ]);

            $user = User::create([
                'name' => $request->first_name . " " . $request->last_name,
                'email' => $request->email,
                'password' => Hash::make(random_int(10000000, 99999999)),
                'must_change_password' => true,
                'user_detail_id' => $user_details->id
            ]);

            $user->assignRole('universitaire');
            DB::commit();
            return response()->json($user, 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error when creating user :" . $e->getMessage());
        }

        return response()->noContent();
    }

    public function updateStudentInfoPerso(Request $request)
    {
        $request->validate([
            'id' => ['required', 'integer', 'exists:users,id'],
            'first_name' => ['required', 'string', 'max:255'],
            'civility' => ['required', 'string', 'in:Mademoiselle,Monsieur,Madame'],
            'last_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:255'],
            'diocese' => ['nullable', 'string', 'max:255'],
            'social_network_id' => ['string', 'max:255', 'nullable']
        ]);

        try {
            DB::beginTransaction();
            $user = User::where('id', $request->id)->first();
            $user->detail()->update([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'phone' => $request->phone,
                'civility' => $request->civility,
                'diocese_of_origin' => $request->diocese,
                'social_network_id' => $request->social_network_id,
            ]);

            DB::commit();
            return response()->json($user, 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error when updating user :" . $e->getMessage());
        }

        return response()->noContent();
    }

    public function getSudentPerso($id)
    {
        $user = User::where("id", $id)->first();
        $data = $user ? [
            "id" => $user->id,
            "email" => $user->email,
            "civility" => $user->detail?->civility,
            "last_name" => $user->detail?->last_name,
            "first_name" => $user->detail?->first_name,
            "diocese" => $user->detail?->diocese_of_origin
        ] : [];

        return response()->json($data);
    }

    public function getCensusById($id)
    {
        $cns = Census::where("id", $id)->first();
        $data = $cns ? [
            "id" => $cns->id,
            "level_id" => $cns->id_level,
            "major_id" => $cns->id_major,
            "user_id" => $cns->id_user,
            "block" => $cns->block,
            "door_number" => $cns->door_number,
            "is_off_campus" => $cns->is_off_campus,
            "is_stay_weekend" => $cns->is_stay_weekend,
            "census_year" => $cns->census_year
        ] : [];

        return response()->json($data);
    }

    public function edit($id)
    {
        // edit a student
        // return "this is the edit method for a student with id of " . $id;
    }

    public function makeCensus(Request $request)
    {
        $request->validate([
            'block' => ['nullable', 'integer', 'max:255'],
            'door_number' => ['nullable', 'integer'],
            'is_off_campus' => ['required', 'boolean'],
            'is_stay_weekend' => ['required', 'boolean'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'major_id' => ['required', 'integer', 'exists:majors,id'],
            'level_id' => ['required', 'integer', 'exists:levels,id'],
            'census_year' => ['required', 'integer']
        ]);

        try {
            DB::beginTransaction();
            Census::create([
                'block' => $request->block,
                'door_number' => $request->door_number,
                'is_off_campus' => $request->is_off_campus,
                'is_stay_weekend' => $request->is_stay_weekend,
                'id_major' => $request->major_id,
                'id_level' => $request->level_id,
                'id_user' => $request->user_id,
                'census_year' => $request->census_year
            ]);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error when creating census :" . $e->getMessage());
        }

        return response()->noContent();
    }

    public function updateCensus(Request $request, $id)
    {
        $request->validate([
            'block' => ['required_if:is_off_campus,false', 'nullable', 'integer', 'max:255'],
            'door_number' => ['required_if:is_off_campus,false', 'nullable', 'integer'],
            'is_off_campus' => ['required', 'boolean'],
            'is_stay_weekend' => ['required', 'boolean'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'major_id' => ['required', 'integer', 'exists:majors,id'],
            'level_id' => ['required', 'integer', 'exists:levels,id'],
            'census_year' => ['required', 'integer']
        ]);

        try {
            DB::beginTransaction();
            Census::where('id', $id)->update([
                'block' => $request->block,
                'door_number' => $request->door_number,
                'is_off_campus' => $request->is_off_campus,
                'is_stay_weekend' => $request->is_stay_weekend,
                'id_major' => $request->major_id,
                'id_level' => $request->level_id,
                'id_user' => $request->user_id,
                'census_year' => $request->census_year
            ]);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error when updating census :" . $e->getMessage());
        }

        return response()->noContent();
    }


    public function getMajorList()
    {
        $majors = Major::get()->map(fn($major) => [
            "value" => $major->id,
            "label" => $major->abbreviation,
            "name" => $major->name
        ])->toArray();

        return response()->json($majors ?? []);
    }

    public function getLevelList()
    {
        $levels = Level::get()->map(fn($level) => [
            "value" => $level->id,
            "label" => $level->abbreviation,
            "name" => $level->name
        ])->toArray();

        return response()->json($levels ?? []);
    }

    public function getStudentListForOptions()
    {
        $students = User::whereHas("roles", function ($q) {
            $q->where("name",  "universitaire");
        })->get()->map(fn($user) => [
            "value" => $user->id,
            "label" => $user->detail->first_name . " " . $user->detail->last_name
        ])->toArray();

        return response()->json($students ?? []);
    }
}
