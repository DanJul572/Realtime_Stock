<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function login(LoginRequest $request)
    {
        if(Auth::attempt(['email' => $request->email, 'password' => $request->password])){
            $token = $request->user()->createToken('admin');
            return [
                'user' => $request->user(),
                'token' => $token->plainTextToken
            ];
        }
        return response()->json([
            'error' => 'Username or Password is invalid'
        ], 400);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return [
            'message' => 'you have successfully logged out'
        ];
    }
}
