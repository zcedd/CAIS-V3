<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Actions\Mobile\IssueMobileToken;
use App\Actions\Mobile\VerifyMobileTwoFactorChallenge;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Mobile\MobileLoginRequest;
use App\Http\Requests\Api\Mobile\MobileTwoFactorRequest;
use App\Http\Resources\Api\Mobile\MobileUserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function login(MobileLoginRequest $request, IssueMobileToken $issue): JsonResponse
    {
        $result = $issue(
            $request->validated('email'),
            $request->validated('password'),
            $request->validated('device_name'),
        );

        return response()->json($this->tokenPayload($result));
    }

    public function twoFactor(MobileTwoFactorRequest $request, VerifyMobileTwoFactorChallenge $verify): JsonResponse
    {
        $result = $verify(
            $request->validated('challenge_token'),
            $request->validated('code'),
            $request->validated('device_name'),
        );

        return response()->json($this->tokenPayload($result));
    }

    public function logout(Request $request): Response
    {
        $token = $request->user()?->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return response()->noContent();
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $user->load('department:id,name,slug');

        return response()->json([
            'user' => new MobileUserResource($user),
            'department' => $user->department === null ? null : [
                'id' => $user->department->id,
                'name' => $user->department->name,
                'slug' => $user->department->slug,
            ],
            'permissions' => $user->grantedPermissionNames(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function tokenPayload(array $result): array
    {
        if (($result['two_factor'] ?? false) === true) {
            return [
                'two_factor' => true,
                'challenge_token' => $result['challenge_token'],
            ];
        }

        return [
            'token' => $result['token'],
            'token_type' => $result['token_type'],
            'user' => new MobileUserResource($result['user']),
        ];
    }
}
