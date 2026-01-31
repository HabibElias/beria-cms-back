<?php

namespace App\Http\Controllers;

use App\Http\Requests\SessionRequest;
use Illuminate\Support\Facades\Auth;
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
        return redirect(to: env('FRONT_END_URL', 'http://localhost:3000') . '/login');
    }

    public function update()
    {
        try {
            // Retrieve the token from the cookie
            $token = JWTAuth::getToken();
            // return response()->json(['token'=>$token]);

            // Check if the token exists
            if (!$token) {
                return response()->json(['status' => false, 'error' => 'Token not found'], 401);
            }

            // Validate the token
            if (!Auth::setToken($token)->check()) {
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
                    'error' => 'Token refresh failed'
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
        $attr = $request->all();
        if (!$token = JWTAuth::attempt($attr, $attr['remember'] ?? false)) {
            return response()->json(['status' => false, 'message' => 'Invalid Credentials']);
        }

        if ((Auth::user()->role === 'user')) {
            return response()->json(['status' => false, 'message' => 'User not Authorized']);
        }

        return response()->json(['status' => true, 'user' => Auth::user(), 'token' => $token]);
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
