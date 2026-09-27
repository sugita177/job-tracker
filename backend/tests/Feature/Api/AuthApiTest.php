<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertAuthenticated;
use function Pest\Laravel\assertAuthenticatedAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

describe('Auth API (ユーザー認証 & セッション管理)', function () {
    test('POST /api/auth/register で新規ユーザーを登録でき、自動ログインされること', function () {
        $payload = [
            'name' => '転職 太郎',
            'email' => 'taro@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $response = postJson('/api/auth/register', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.name', '転職 太郎')
            ->assertJsonPath('data.email', 'taro@example.com')
            ->assertJsonMissingPath('data.password');

        assertDatabaseHas('users', [
            'email' => 'taro@example.com',
            'name' => '転職 太郎',
        ]);

        // 登録後に自動ログイン（セッション確立）されていることを検証
        assertAuthenticated();
    });

    test('POST /api/auth/register でメール重複やパスワード確認不一致は 422 エラーになること', function () {
        User::factory()->create(['email' => 'existing@example.com']);

        // 1. メール重複
        $response1 = postJson('/api/auth/register', [
            'name' => '重複 太郎',
            'email' => 'existing@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);
        $response1->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        // 2. パスワード確認不一致
        $response2 = postJson('/api/auth/register', [
            'name' => '不一致 太郎',
            'email' => 'diff@example.com',
            'password' => 'password123',
            'password_confirmation' => 'wrong_password',
        ]);
        $response2->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    });

    test('POST /api/auth/login で正しい認証情報ならログインできること', function () {
        $user = User::factory()->create([
            'email' => 'login_user@example.com',
            'password' => Hash::make('secret1234'),
        ]);

        $response = postJson('/api/auth/login', [
            'email' => 'login_user@example.com',
            'password' => 'secret1234',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.email', 'login_user@example.com');

        assertAuthenticatedAs($user);
    });

    test('POST /api/auth/login でパスワードが間違っている場合は 422 エラーになること', function () {
        User::factory()->create([
            'email' => 'login_user@example.com',
            'password' => Hash::make('secret1234'),
        ]);

        $response = postJson('/api/auth/login', [
            'email' => 'login_user@example.com',
            'password' => 'wrong_password',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        assertGuest();
    });

    test('GET /api/auth/user でログイン中のユーザー情報を取得できること', function () {
        $user = User::factory()->create(['name' => 'ログイン中のユーザー']);

        $response = actingAs($user)->getJson('/api/auth/user');

        $response->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.name', 'ログイン中のユーザー');
    });

    test('GET /api/auth/user で未認証の場合は 401 エラーになること', function () {
        $response = getJson('/api/auth/user');

        $response->assertUnauthorized();
    });

    test('POST /api/auth/logout でログアウトでき、セッションが無効化されること', function () {
        $user = User::factory()->create();

        $response = actingAs($user)->postJson('/api/auth/logout');

        $response->assertNoContent();

        assertGuest();
    });
});
