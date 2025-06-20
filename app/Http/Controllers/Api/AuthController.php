<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Http\Traits\ApiResponseTrait;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    use ApiResponseTrait;

    /**
     * User registration.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create($request->validated());

        return $this->createTokenResponse($user, 'User has been registered successfully.', 201);
    }

    /**
     * Login user.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        if (!Auth::attempt($request->only('email', 'password'))) {
            return $this->unauthorizedResponse('Invalid credentials.');
        }

        return $this->createTokenResponse(Auth::user(), 'User has been logged in successfully.');
    }

    /**
     * Get authenticated user info.
     */
    public function me(): JsonResponse
    {
        return $this->successResponse(new UserResource(auth()->user()), 'Authenticated user info.');
    }

    /**
     * Refresh authentication token.
     */
    public function refreshToken(): JsonResponse
    {
        return $this->createTokenResponse(auth()->user(), 'Token refreshed successfully.');
    }

    /**
     * Logout user.
     */
    public function logout(): JsonResponse
    {
        auth()->user()->tokens()->delete();

        return $this->noContentResponse('Logged out successfully.');
    }

    /**
     * Create a token response.
     *
     * @param  \App\Models\User  $user
     * @param  string  $message
     * @param  int  $statusCode
     * @return \Illuminate\Http\JsonResponse
     */
    private function createTokenResponse(User $user, string $message, int $statusCode = 200): JsonResponse
    {
        $token = $user->createToken('late-api')->accessToken;

        $data = [
            'user' => new UserResource($user),
            'token' => $token,
        ];

        return $this->successResponse($data, $message, $statusCode);
    }
}
