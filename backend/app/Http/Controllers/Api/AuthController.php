<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * @group 認証・アカウント管理
 *
 * ユーザー登録、セッションログイン、ログアウト、および現在ログイン中ユーザーのプロファイル取得を行います。
 */
final class AuthController extends Controller
{
    /**
     * 新規ユーザー登録
     *
     * @unauthenticated
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => (string) $request->input('name'),
            'email' => (string) $request->input('email'),
            'password' => Hash::make((string) $request->input('password')),
        ]);

        // 登録後に自動でセッションログイン
        Auth::login($user);
        $request->session()->regenerate();

        return (new UserResource($user))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * ログイン
     *
     * @unauthenticated
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = [
            'email' => (string) $request->input('email'),
            'password' => (string) $request->input('password'),
        ];

        if (! Auth::attempt($credentials)) {
            throw ValidationException::withMessages([
                'email' => [trans('auth.failed')],
            ]);
        }

        // セッション固定攻撃防止
        $request->session()->regenerate();

        /** @var User $user */
        $user = Auth::user();

        return (new UserResource($user))->response();
    }

    /**
     * ログイン中ユーザー情報の取得
     */
    public function user(Request $request): UserResource
    {
        /** @var User $user */
        $user = $request->user();

        return new UserResource($user);
    }

    /**
     * ログアウト
     */
    public function logout(Request $request): Response
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }
}
