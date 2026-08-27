<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class RegisterController extends Controller
{
    /**
     * POST /api/v1/auth/register
     * Same rules and defaults as the web registration flow —
     * see 03-Auth-Security.md Part A.
     */
    public function register(RegisterRequest $request)
    {
        $user = User::create([
            'name'                 => $request->name,
            'student_id'           => $request->student_id,
            'email'                => $request->email,
            'department'           => $request->department,
            'program'              => $request->program,
            'level'                => $request->level,
            'password'             => Hash::make('123456'),
            'role'                 => 'student',
            'is_approved'          => false,
            'must_change_password' => true,
        ]);

        ActivityLog::record($user->id, 'student_registered', User::class, $user->id, [
            'student_id' => $user->student_id,
            'platform'   => 'mobile',
        ], $request->ip());

        return response()->json([
            'success' => true,
            'message' => 'Registration submitted. Your account is pending Admin approval.',
        ], 201);
    }
}
