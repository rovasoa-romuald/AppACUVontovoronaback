<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;

class DonorController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $pagination      = $request->get('perPage', 10);
        $search          = $request->get('search');
        $users = \App\Models\User::role('donateur')
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
            ->paginate($pagination)
            ->withQueryString()->through(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'civility' => $user->detail?->civility,
                    'type' => $user->detail?->type,
                    'first_name' => $user->detail?->first_name,
                    'my_job' => $user->detail?->my_job,
                    'phone' => gettype($user->detail?->phone_numbers) === 'string' ? json_decode($user->detail?->phone_numbers) : $user->detail?->phone_numbers,
                    'address' => $user->detail?->address,
                    "has_jubilee_envelope" => $user->jubileeEnvelopes()->count() > 0,
                ];
            })->toArray();
        return response()->json($users);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'first_name' => 'nullable|string',
            'civility' => 'nullable|string',
            'email' => 'required|string|email|unique:users,email',
            'type' => 'required|in:individual,organization',
            'phone' => 'nullable|array',
            'address' => 'nullable|string',
            'my_job' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            $user = \App\Models\User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make(rand(100000, 999999)),
            ]);

            $user->assignRole('donateur');
            $userDetail = $user->detail()->create([
                'type' => $request->type,
                'first_name' => $request->first_name,
                'civility' => $request->civility,
                'last_name' => $request->name,
                'phone_numbers' => json_encode($request->phone),
                'address' => $request->address,
                'my_job' => $request->my_job,
            ]);
            $user->user_detail_id = $userDetail->id;
            $user->save();

            DB::commit();
        } catch (\Exception $th) {
            DB::rollBack();
            Log::error($th->getMessage());
        }
        return response()->json($user, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $user = \App\Models\User::with('detail')->findOrFail($id);
        return response()->json($user);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $request->validate([
            'name' => 'required|string',
            'first_name' => 'nullable|string',
            'civility' => 'nullable|string',
            'email' => 'required|string|email|unique:users,email,' . $id,
            'type' => 'nullable|in:individual,organization',
            'phone' => 'nullable|array',
            'address' => 'nullable|string',
            'my_job' => 'nullable|string',
        ]);
        try {
            DB::beginTransaction();

            $user = \App\Models\User::findOrFail($id);
            $user->update([
                'name' => $request->name,
                'email' => $request->email,
            ]);

            $user->detail()->update([
                'type' => $request->type,
                'first_name' => $request->first_name,
                'civility' => $request->civility,
                'last_name' => $request->name,
                'phone_numbers' => json_encode($request->phone),
                'address' => $request->address,
                'my_job' => $request->my_job,
            ]);

            DB::commit();
        } catch (\Exception $th) {
            DB::rollBack();
            Log::error($th->getMessage());
        }
        return response()->json($user);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            DB::beginTransaction();
            $user = \App\Models\User::findOrFail($id);
            $user->jubileeEnvelopes()->delete();
            $user->conversations()->delete();
            $user->delete();
            DB::commit();
        } catch (\Exception $th) {
            DB::rollBack();
            Log::error($th->getMessage());
            return response()->json(["error" => $th->getMessage()], 500)->header('Content-Type', 'application/json');
        }
        return response()->json(['message' => 'User deleted successfully']);
    }
}
