<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function login(LoginRequest $request)
    {
        if(Auth::attempt(['email' => $request->email, 'password' => $request->password])){
            AuditLogger::logAuth('login', $request->user(), $request->email);
            $token = $request->user()->createToken('admin');
            return [
                'user' => $request->user(),
                'token' => $token->plainTextToken
            ];
        }
        AuditLogger::logAuth('login_failed', User::where('email', $request->email)->first(), $request->email);
        return response()->json([
            'error' => 'Username or Password is invalid'
        ], 400);
    }

    public function logout(Request $request)
    {
        AuditLogger::logAuth('logout', $request->user(), $request->user()->email);
        $request->user()->currentAccessToken()->delete();
        return [
            'message' => 'you have successfully logged out'
        ];
    }
}
