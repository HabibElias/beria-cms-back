<?php

namespace App\Http\Controllers;

use App\Http\Requests\SessionRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;

class SessionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
        return redirect(to: env('FRONT_END_URL', 'http://localhost:3000').'/login');
    }

    public function update()
    {
        try {
            // Retrieve the token from the cookie
            $token = JWTAuth::getToken();
            // return response()->json(['token'=>$token]);

            // Check if the token exists
            if (! $token) {
                return response()->json(['status' => false, 'error' => 'Token not found'], 401);
            }

            // Validate the token
            if (! Auth::setToken($token)->check()) {
                return response()->json(['status' => false, 'error' => 'Invalid token'], 401);
            }

            // Refresh the token
            $newToken = JWTAuth::setToken($token)->refresh();

            // Return the new token and set it in the cookie
            return response()
                ->json(['status' => true, 'user' => Auth::user(), 'token' => $newToken]);
        } catch (JWTException $e) {
            return response()->json(
                [
                    'status' => false,
                    'error' => 'Token refresh failed',
                ],
                401
            );
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(SessionRequest $request)
    {
        //
        $attr = $request->safe()->only(['email', 'password']);
        $attr['email'] = User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower($attr['email'])])->value('email') ?? $attr['email'];
        if (! $token = JWTAuth::attempt($attr)) {
            return response()->json(['status' => false, 'message' => 'Incorrect email or password.'], 401);
        }

        return response()->json(['status' => true, 'user' => Auth::user(), 'token' => $token]);
    }

    public function register(Request $request)
    {
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        $attributes = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'min:10', 'max:30'],
            'password' => ['required', 'string', 'min:12', 'max:72', 'confirmed', function ($attribute, $value, $fail) {
                if (strlen($value) > 72) {
                    $fail('Password is too long. Please use a shorter password.');
                }
            }],
            'role' => ['prohibited'],
        ]);
        if (User::query()->whereRaw('LOWER(email) = ?', [$attributes['email']])->exists()) {
            throw ValidationException::withMessages(['email' => 'This email address already has an account.']);
        }
        $user = User::create([
            'name' => trim($attributes['name']), 'email' => $attributes['email'],
            'phone' => trim($attributes['phone']), 'password' => $attributes['password'], 'role' => 'user',
        ]);

        return response()->json(['status' => true, 'user' => $user, 'token' => JWTAuth::fromUser($user)], 201);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy()
    {
        Auth::logout();

        return response()->json(['status' => true, 'message' => 'successfully logged out']);
    }
}
