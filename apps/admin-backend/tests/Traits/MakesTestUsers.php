<?php

namespace Tests\Traits;

use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

trait MakesTestUsers
{
    protected function seedBase(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->seed(SystemSettingSeeder::class);
    }

    protected function makeUserWithRole(string $role, array $attrs = []): User
    {
        $user = User::create(array_merge([
            'name' => 'Test ' . $role,
            'email' => $role . '@example.com',
            'password' => Hash::make('password123'),
        ], $attrs));
        $user->roles()->attach(Role::where('name', $role)->firstOrFail()->id);
        return $user;
    }

    protected function makeEmployee(array $attrs = []): Employee
    {
        $user = $this->makeUserWithRole(Role::EMPLOYEE, ['email' => 'emp' . uniqid() . '@example.com']);
        return Employee::create(array_merge([
            'employee_code' => 'EMP-' . str_pad((string) (Employee::count() + 1), 3, '0', STR_PAD_LEFT),
            'user_id' => $user->id,
            'name' => 'Budi Santoso',
            'employment_status' => 'aktif',
        ], $attrs));
    }

    protected function tokenFor(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    /**
     * Fake the tDocs resumable upload protocol (folders, init, chunks, complete).
     */
    protected function fakeTdocs(): void
    {
        $counter = 0;
        Http::fake(function ($request) use (&$counter) {
            $path = $request->url();
            if (str_contains($path, '/api/upload/complete')) {
                $counter++;
                return Http::response(['id' => 'tdocs-file-' . $counter . '-' . uniqid(), 'name' => 'selfie.jpg'], 201);
            }
            if (str_contains($path, '/api/upload/init')) {
                return Http::response(['session_id' => 'sess-1', 'chunk_size' => 5 * 1024 * 1024], 200);
            }
            if (str_contains($path, '/api/upload/chunk')) {
                return Http::response(['ok' => true], 200);
            }
            if (str_contains($path, '/api/folders')) {
                return Http::response(['id' => 'folder-' . substr(md5($path . microtime()), 0, 8)], 201);
            }
            if (str_contains($path, '/api/health')) {
                return Http::response(['status' => 'ok'], 200);
            }
            if (str_contains($path, '/api/files/')) {
                return Http::response('fake-bytes', 200);
            }
            return Http::response(['ok' => true], 200);
        });
    }

    protected function authHeaders(User $user): array
    {
        return ['Authorization' => 'Bearer ' . $this->tokenFor($user)];
    }

    /**
     * Sanctum's RequestGuard caches the resolved user on the guard instance,
     * and the AuthManager singleton survives across requests inside one test.
     * Forget guards before every authenticated request so each request
     * authenticates its own Bearer token (production boots fresh per request).
     */
    protected function refreshAuth(): void
    {
        $this->app['auth']->forgetGuards();
    }

    protected function authGet(User $user, string $uri, array $headers = [])
    {
        $this->refreshAuth();
        return $this->getJson($uri, array_merge($this->authHeaders($user), $headers));
    }

    protected function authPost(User $user, string $uri, array $data = [], array $headers = [])
    {
        $this->refreshAuth();
        return $this->postJson($uri, $data, array_merge($this->authHeaders($user), $headers));
    }

    protected function authPut(User $user, string $uri, array $data = [], array $headers = [])
    {
        $this->refreshAuth();
        return $this->putJson($uri, $data, array_merge($this->authHeaders($user), $headers));
    }

    protected function authPatch(User $user, string $uri, array $data = [], array $headers = [])
    {
        $this->refreshAuth();
        return $this->patchJson($uri, $data, array_merge($this->authHeaders($user), $headers));
    }

    protected function authDelete(User $user, string $uri, array $headers = [])
    {
        $this->refreshAuth();
        return $this->deleteJson($uri, array_merge($this->authHeaders($user), $headers));
    }
}
