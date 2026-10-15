<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Jobs\ImportElderExcelJob;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    public function getUser($id)
    {
        $user = User::where("id", $id)->first();
        $data = $user ? [
            "id" => $user->id,
            "email" => $user->email,
            "civility" => $user->detail?->civility,
            "last_name" => $user->detail?->last_name,
            "first_name" => $user->detail?->first_name,
            "promotion_name" => $user->detail?->promotion_name,
            "promotion_year" => $user->detail?->promotion_year,
            "phone" => $user->detail?->phone,
            "espa_field" => $user->detail?->espa_field,
            "nearest_city" => $user->detail?->nearest_city,
            "first_message" => $user->detail?->first_message,
            "acu_previous_occupation" => $user->detail?->acu_previous_occupation,
            "my_job" => $user->detail?->my_job,
            "social_network_id" => $user->detail?->social_network_id,
            "has_jubilee_envelope" => $user->jubileeEnvelopes()->count() > 0,
            "is_elder_info_completed" => $user->detail?->is_elder_info_completed,
            "is_special" => $user->detail?->is_special,
        ] : [];

        return response()->json($data);
    }

    public function getUserForChecking(Request $request)
    {
        $request->validate([
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255']
        ]);
        $user = User::where("email", $request->email)->first();
        $data = $user ? [
            "email" => $user->email,
            "civility" => $user->detail?->civility,
            "last_name" => $user->detail?->last_name,
            "first_name" => $user->detail?->first_name,
            "promotion_name" => $user->detail?->promotion_name,
            "promotion_year" => $user->detail?->promotion_year,
        ] : [];

        return response()->json($data);
    }

    public function getUserList(Request $request)
    {
        $pagination                    = $request->get('perPage', 10);
        $firstLastName                 = $request->get('firstLastName');
        $promoYear                    = $request->get('promoYear');
        $elderInfoOk                    = $request->get('elderInfoOk', null);
        $isSpecial                    = $request->get('isSpecial', null);
        $isHasResponsible                    = $request->get('isHasResponsible', null);
        $users = User::whereHas("roles", function ($q) {
            $q->where("name",  "ancien");
        })->when(!!$firstLastName, function ($q) use ($firstLastName) {
            $q->where(function ($q) use ($firstLastName) {
                $q->where("name", "like", "%$firstLastName%")
                    ->orWhere("email", "like", "%$firstLastName%")
                    ->orWhereHas("detail", function ($query) use ($firstLastName) {
                        $query->where('phone', "like", "%$firstLastName%");
                    });
            });
        })
            ->when(!!$promoYear, function ($q) use ($promoYear) {
                $q->whereHas("detail", function ($query) use ($promoYear) {
                    $query->where("promotion_year", "like", "%$promoYear%");
                });
            })
            ->when($elderInfoOk !== null, function ($q) use ($elderInfoOk) {
                $q->whereHas("detail", function ($query) use ($elderInfoOk) {
                    $query->where("is_elder_info_completed", "=", $elderInfoOk === "true");
                });
            })
            ->when($isSpecial !== null, function ($q) use ($isSpecial) {
                $q->whereHas("detail", function ($query) use ($isSpecial) {
                    $query->where("is_special", "=", $isSpecial === "true");
                });
            })
            ->when($isHasResponsible !== null, function ($q) use ($isHasResponsible) {
                if ($isHasResponsible === "yes") {
                    $q->whereHas("jubileeEnvelopes");
                }
                if ($isHasResponsible === "no") {
                    $q->whereDoesntHave("jubileeEnvelopes");
                }
            })
            ->paginate($pagination)
            ->withQueryString()
            ->through(fn($user) => [
                "id" => $user->id,
                "email" => $user->email,
                "civility" => $user->detail?->civility,
                "last_name" => $user->detail?->last_name,
                "first_name" => $user->detail?->first_name,
                "promotion_name" => $user->detail?->promotion_name,
                "promotion_year" => $user->detail?->promotion_year,
                "phone" => $user->detail?->phone,
                "espa_field" => $user->detail?->espa_field,
                "nearest_city" => $user->detail?->nearest_city,
                "first_message" => $user->detail?->first_message,
                "acu_previous_occupation" => $user->detail?->acu_previous_occupation,
                "my_job" => $user->detail?->my_job,
                "social_network_id" => $user->detail?->social_network_id,
                "has_jubilee_envelope" => $user->jubileeEnvelopes()->count() > 0,
                "is_elder_info_completed" => $user->detail?->is_elder_info_completed,
                "is_special" => $user->detail?->is_special
            ])->toArray();

        return response()->json($users);
    }


    public function getUserListForOptions(Request $request)
    {
        $role = $request->get("role");
        $search = $request->get("search");
        $users = User::when(!!$role, function ($q) use ($role) {
            $q->whereHas("roles", function ($q) use ($role) {
                $q->where("name", $role);
            });
        })
            ->when(
                !!$search,
                fn($query) => $query->where(function ($query) use ($search) {
                    $query->where("name", "like", "%$search%")
                        ->orWhere("email", "like", "%$search%")
                        ->orWhereHas("detail", function ($query) use ($search) {
                            $query->where("first_name", "like", "%$search%");
                        });
                })
            )
            ->get()
            ->map(fn($user) => [
                "value" => $user->id,
                "label" => $user->detail?->first_name . " " . $user->detail?->last_name
            ])->toArray();

        return response()->json($users);
    }


    public function deleteUser(Request $request)
    {
        $request->validate([
            "ids" => ["required", "array", function ($attribute, $value, $fail) {
                if (!User::whereIn("id", $value)->exists()) {
                    $fail("The " . $attribute . " must contain existing users IDs");
                }
            }],
        ]);
        try {
            DB::beginTransaction();
            $users = User::whereIn("id", $request->ids)->get();
            $users->each(function ($user) {
                $user->jubileeEnvelopes()->update([
                    "recipient_id" => null,
                    "responsible_id" => null,
                    "status" => "lost"
                ]);
                $user->delete();
                $user->detail()->delete();
            });
            DB::commit();
        } catch (\Exception $th) {
            DB::rollBack();
            Log::error("Error when deleting user :" . $th->getMessage());
            return response()->json($th->getMessage(), 500)->header('Content-Type', 'application/json')->throwResponse();
        }
        return response()->noContent();
    }

    public function makeSpecialElders(Request $request)
    {
        $request->validate([
            "ids" => ["required", "array", function ($attribute, $value, $fail) {
                if (!User::whereIn("id", $value)->exists()) {
                    $fail("The " . $attribute . " must contain existing users IDs");
                }
            }],
        ]);
        try {
            DB::beginTransaction();
            User::whereIn("id", $request->ids)->get()->each(function ($user) {
                $user->detail()->update([
                    "is_special" => true
                ]);
            });
            DB::commit();
        } catch (\Exception $th) {
            DB::rollBack();
            Log::error("Error when marking as special user :" . $th->getMessage());
            return response()->json($th->getMessage(), 500)->header('Content-Type', 'application/json')->throwResponse();
        }
        return response()->noContent();
    }


    /**
     * Update the registration information of an elder.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function updateInfoElder(Request $request, int $id): Response
    {
        $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'civility' => ['required', 'string', 'in:Mademoiselle,Monsieur,Madame'],
            'last_name' => ['required', 'string', 'max:255'],
            'promotion_name' => ['string', 'max:255'],
            'promotion_year' => ['required', 'integer'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:' . User::class . ',email,' . $id],
            'phone' => ['required', 'string', 'max:255'],
            'espa_field' => ['string', 'max:255', 'nullable'],
            'nearest_city' => ['string', 'max:255', 'nullable'],
            'acu_previous_occupation' => ['string', 'max:255', 'nullable'],
            'first_message' => ['string', 'max:1000', 'nullable'],
            'my_job' => ['string', 'max:255', 'nullable'],
            'social_network_id' => ['string', 'max:255', 'nullable'],
            'is_elder_info_completed' => ['required', 'boolean'],
            'is_special' => ['required', 'boolean'],
        ]);

        try {
            DB::beginTransaction();
            $user = User::findOrFail($id);
            $user->update([
                'name' => $request->first_name . " " . $request->last_name,
                'email' => $request->email,
            ]);

            $user->detail->update([
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
                'is_elder_info_completed' => $request->is_elder_info_completed,
                'is_special' => $request->is_special,
            ]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error when updating user :" . $e->getMessage());
        }

        return response()->noContent();
    }

    public function createPwdForUser(Request $request)
    {
        $request->validate([
            'role' => ['required', 'string', 'in:ancien,universitaire'],
            'id' => ['required', 'integer', 'exists:users,id']
        ]);

        try {
            DB::beginTransaction();
            $newPassword = Str::random(8);  // Generate a random password

            $user = User::findOrFail($request->id);
            // Logout the user who has the password changed
            $user->tokens()->delete();

            // Make sure the user can login correctly after the changes
            $user->update([
                'email_verified_at'    => now(),
                'password'             => Hash::make($newPassword),
                'must_change_password' => false,
            ]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error when updating user :" . $e->getMessage());
        }

        return response()->json(["pwd" => $newPassword]);
    }

    public function upload(Request $request)
    {
        // Validate the incoming request
        $validator = Validator::make($request->all(), [
            'file' => [
                'required',
                'file',
                'mimes:xlsx,xls,csv', // Allow Excel and CSV files
                'max:2048', // Maximum file size of 2MB
            ]
        ], [
            'file.required' => 'Please upload an Excel file.',
            'file.mimes' => 'Only Excel files (xlsx, xls, csv) are allowed.',
            'file.max' => 'The file must not exceed 2MB.'
        ]);

        // Check if validation fails
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Get the uploaded file
            $file = $request->file('file');
            // If there are validation errors, return them
            if (!empty($validationErrors)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validationErrors
                ], 422);
            }

            // save file in storage
            $fileName = $file->getClientOriginalName();
            $file->storeAs('uploads', $fileName, 'public');

            // Dispatch ImportElderExcelJob
            $job = new ImportElderExcelJob(storage_path("app/public/uploads/" . $fileName));
            $job->delay(now()->addSeconds(10));
            dispatch($job);


            // Return success response
            return response()->json([
                'success' => true,
                'message' => 'Excel file uploaded and processed successfully'
            ], 200);
        } catch (\Exception $e) {
            // Log the error and return a generic error response
            Log::error('Excel upload error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while processing the file',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function generateFictiveEmail()
    {
        $fictif_emails = User::where("email", "like", "%fictif%")->pluck("email")->toArray();
        $max_number = count($fictif_emails) > 0 ? max(array_map(function ($email) {
            $number = explode(".", $email)[0];
            if (is_numeric($number)) {
                return $number;
            }
            return 0;
        }, $fictif_emails)) : 0;
        $new_number = $max_number + 1;
        $new_email =  $new_number  . ".fictif@acut-vontovorona.org";
        return response()->json([
            "email" => $new_email
        ]);
    }

    // /**
    //  * Validate rows from the Excel file
    //  *
    //  * @param Collection $rows
    //  * @return array
    //  */
    // private function validateRows(Collection $rows): array
    // {
    //     $errors = [];

    //     $rows->each(function ($row, $index) use (&$errors) {
    //         $rowErrors = [];

    //         // Add your specific validation rules here
    //         // Example validations:
    //         if (!isset($row['email']) || !filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
    //             $rowErrors['email'] = 'Invalid email format';
    //         }

    //         if (!isset($row['last_name']) || empty(trim($row['last_name']))) {
    //             $rowErrors['last_name'] = 'Name is required';
    //         }

    //         // Add more validation rules as needed

    //         if (!empty($rowErrors)) {
    //             $errors[] = [
    //                 'row' => $index + 2, // +2 because Excel rows start at 1 and we have a header
    //                 'errors' => $rowErrors
    //             ];
    //         }
    //     });

    //     return $errors;
    // }
}
