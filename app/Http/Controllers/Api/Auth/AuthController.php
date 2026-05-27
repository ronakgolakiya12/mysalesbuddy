<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Traits\ApiResponses;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    use ApiResponses;

    public function login(LoginRequest $request): JsonResponse
    {
        $user = $request->authenticate();
        $user->load('notetakerConfig');

        return $this->successResource(new UserResource($user));
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = $request->createUser();
        $user->load('notetakerConfig');

        $response = (new UserResource($user))->response();
        $response->setStatusCode(201);

        return $response;
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return $this->noContent();
    }

    public function user(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        return $this->successResource(new UserResource($user->load('notetakerConfig')));
    }
}
