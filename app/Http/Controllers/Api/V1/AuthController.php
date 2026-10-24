<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        if (is_string($request->input('email'))) {
            $request->merge(['email' => mb_strtolower(trim($request->input('email')))]);
        }
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['prohibited'],
            'password' => ['required', 'string', 'confirmed', 'max:72', Password::min(8)->letters()->numbers()],
            'device_name' => ['required', 'string', 'max:80'],
            'is_admin' => ['prohibited'],
        ], ['phone.prohibited' => 'Telefon raqamini SMS kod orqali tasdiqlab ulang.']);

        return DB::transaction(function () use ($data) {
            $user = User::create(collect($data)->only(['name', 'email', 'password'])->all());

            return response()->json([
                'user' => new UserResource($user->refresh()),
                'token' => $user->createToken($data['device_name'])->plainTextToken,
                'token_type' => 'Bearer',
            ], 201);
        });
    }

    public function login(Request $request)
    {
        if (is_string($request->input('email'))) {
            $request->merge(['email' => mb_strtolower(trim($request->input('email')))]);
        }
        $data = $request->validate([
            'email' => ['required', 'email'], 'password' => ['required', 'string', 'max:72'],
            'device_name' => ['required', 'string', 'max:80'],
        ]);
        $user = User::where('email', $data['email'])->first();
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => 'Email yoki parol noto‘g‘ri.']);
        }

        return DB::transaction(function () use ($user, $data) {
            $user->tokens()->where('name', $data['device_name'])->delete();

            return response()->json([
                'user' => new UserResource($user), 'token' => $user->createToken($data['device_name'])->plainTextToken,
                'token_type' => 'Bearer',
            ]);
        });
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    public function logout(Request $request)
    {
        $token = $request->user()->currentAccessToken();
        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return response()->noContent();
    }
}
