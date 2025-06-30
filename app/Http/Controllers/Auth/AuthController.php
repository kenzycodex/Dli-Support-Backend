<?php
// app/Http/Controllers/Auth/AuthController.php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Login user and return token
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials'
            ], 401);
        }

        if (!$user->isActive()) {
            return response()->json([
                'success' => false,
                'message' => 'Account is ' . $user->status . '. Please contact administrator.'
            ], 403);
        }

        // Update last login
        $user->updateLastLogin();

        // Create token
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'status' => $user->status,
                    'last_login_at' => $user->last_login_at,
                ],
                'token' => $token,
                'token_type' => 'Bearer'
            ]
        ], 200);
    }

    /**
     * Demo login for testing
     */
    public function demoLogin(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'role' => 'required|in:student,counselor,advisor,admin',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid role specified',
                'errors' => $validator->errors()
            ], 422);
        }

        // Create or get demo user based on role
        $demoUsers = [
            'student' => [
                'name' => 'Ada Balogun',
                'email' => 'student@university.edu',
                'role' => 'student'
            ],
            'counselor' => [
                'name' => 'Dr. Sarah Wilson',
                'email' => 'counselor@university.edu',
                'role' => 'counselor'
            ],
            'advisor' => [
                'name' => 'Prof. Michael Chen',
                'email' => 'advisor@university.edu',
                'role' => 'advisor'
            ],
            'admin' => [
                'name' => 'Admin User',
                'email' => 'admin@university.edu',
                'role' => 'admin'
            ]
        ];

        $demoData = $demoUsers[$request->role];
        
        $user = User::where('email', $demoData['email'])->first();
        
        if (!$user) {
            $user = User::create([
                'name' => $demoData['name'],
                'email' => $demoData['email'],
                'password' => Hash::make('password'),
                'role' => $demoData['role'],
                'status' => User::STATUS_ACTIVE,
                'email_verified_at' => now()
            ]);
        }

        // Update last login
        $user->updateLastLogin();

        // Create token
        $token = $user->createToken('demo-auth-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Demo login successful',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'status' => $user->status,
                    'last_login_at' => $user->last_login_at,
                ],
                'token' => $token,
                'token_type' => 'Bearer'
            ]
        ], 200);
    }

    /**
     * Register new user (admin only)
     */
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|in:student,counselor,advisor,admin',
            'phone' => 'nullable|string|max:20',
            'student_id' => 'nullable|string|unique:users,student_id',
            'employee_id' => 'nullable|string|unique:users,employee_id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'phone' => $request->phone,
            'student_id' => $request->student_id,
            'employee_id' => $request->employee_id,
            'status' => User::STATUS_ACTIVE,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User created successfully',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'status' => $user->status,
                ]
            ]
        ], 201);
    }

    /**
     * Get authenticated user details
     */
    public function user(Request $request): JsonResponse
    {
        $user = $request->user();
        
        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'status' => $user->status,
                    'phone' => $user->phone,
                    'profile_photo' => $user->profile_photo,
                    'last_login_at' => $user->last_login_at,
                    'created_at' => $user->created_at,
                ]
            ]
        ]);
    }

    /**
     * Logout user and revoke token
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully'
        ]);
    }

    /**
     * Logout from all devices
     */
    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out from all devices successfully'
        ]);
    }

    /**
     * Refresh token
     */
    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->currentAccessToken()->delete();
        
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Token refreshed successfully',
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer'
            ]
        ]);
    }
}