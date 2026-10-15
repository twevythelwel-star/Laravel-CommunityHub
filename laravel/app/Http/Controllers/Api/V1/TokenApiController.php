<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CreateTokenApiRequest;
use App\Http\Resources\TokenResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TokenApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $tokens = $user->tokens()->latest()->get();

        return ApiResponse::success(
            data: TokenResource::collection($tokens),
            message: 'Tokens retrieved successfully.'
        );
    }

    public function store(CreateTokenApiRequest $request): JsonResponse
    {
        if ($beyond = $request->abilitiesBeyondAccount()) {
            return ApiResponse::error(
                message: 'This account cannot grant: '.implode(', ', $beyond).'.',
                code: 'ABILITY_NOT_PERMITTED',
                status: 422,
            );
        }

        $tokenResult = $request->user()->createToken(
            name: $request->tokenName(),
            abilities: $request->abilities(),
            expiresAt: $request->expiresAt()
        );

        return ApiResponse::success(
            data: [
                'token' => new TokenResource($tokenResult->accessToken),
                'plainTextToken' => $tokenResult->plainTextToken,
            ],
            message: 'Personal access token created successfully. Store this token securely; it cannot be shown again.',
            status: 201
        );
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $token = $user->tokens()->where('id', $id)->first();

        if (! $token) {
            return ApiResponse::error(
                message: 'Token not found.',
                code: 'TOKEN_NOT_FOUND',
                status: 404
            );
        }

        $token->delete();

        return ApiResponse::success(
            data: null,
            message: 'Token revoked successfully.'
        );
    }

    public function destroyAll(Request $request): JsonResponse
    {
        $user = $request->user();
        $count = $user->tokens()->count();
        $user->tokens()->delete();

        return ApiResponse::success(
            data: ['revoked_count' => $count],
            message: "Successfully revoked {$count} tokens."
        );
    }
}
