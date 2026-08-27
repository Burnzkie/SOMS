<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{
    /**
     * POST /api/v1/auth/login
     * Response: { success, data: { token, user: { id, name, role, student_id, must_change_password } } }
     * See 10-Mobile-Deployment.md — Part B, Authentication Flow.
     */
    public function login(LoginRequest $request)
    {
        $credentials = $request->only('student_id', 'password');
        $user = User::where('student_id', $credentials['student_id'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            ActivityLog::record(
                $user?->id,
                'login_failed',
                null,
                null,
                ['student_id' => $credentials['student_id'], 'platform' => 'mobile'],
                $request->ip()
            );

            return response()->json([
                'success' => false,
                'message' => 'Invalid Student ID or password.',
            ], 401);
        }

        if (!$user->is_approved) {
            return response()->json([
                'success' => false,
                'message' => 'Your account is still pending Admin approval.',
            ], 403);
        }

        $token = $user->createToken('mobile')->plainTextToken;

        ActivityLog::record($user->id, 'login_success', null, null, ['platform' => 'mobile'], $request->ip());

        return response()->json([
            'success' => true,
            'data' => [
                'token' => $token,
                'user' => [
                    'id'                    => $user->id,
                    'name'                  => $user->name,
                    'role'                  => $user->role,
                    'student_id'            => $user->student_id,
                    'must_change_password'  => $user->must_change_password,
                ],
            ],
        ]);
    }

    /**
     * POST /api/v1/auth/logout
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        ActivityLog::record($request->user()->id, 'logout', null, null, ['platform' => 'mobile'], $request->ip());

        return response()->json(['success' => true, 'message' => 'Logged out.']);
    }
}
