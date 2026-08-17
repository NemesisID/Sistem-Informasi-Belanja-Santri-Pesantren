<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Santri;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class WaliUserController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min((int) $request->integer('per_page', 15) ?: 15, (int) config('koin.max_per_page'));

        $query = User::where('role', 'wali')
            ->with('santris:id,nis,nama,kelas,unit')
            ->when($request->input('search'), function ($q, $search) {
                $q->where(fn ($q) => $q->where('name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%"));
            })
            ->when($request->has('status'), function ($q) use ($request) {
                $q->where('is_active', $request->boolean('status'));
            })
            ->orderBy('name');

        return UserResource::collection($query->paginate($perPage));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'      => ['required', 'string', 'max:255'],
            'username'  => ['required', 'string', Rule::unique('users', 'username')],
            'phone'     => ['nullable', 'string', 'max:30'],
            'password'  => ['required', 'string', 'min:6'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $wali = User::create($data + ['role' => 'wali']);

        return (new UserResource($wali))->response()->setStatusCode(201);
    }

    public function update(Request $request, User $user): UserResource
    {
        $data = $request->validate([
            'name'      => ['required', 'string', 'max:255'],
            'username'  => ['required', 'string', Rule::unique('users', 'username')->ignore($user->id)],
            'phone'     => ['nullable', 'string', 'max:30'],
            'password'  => ['nullable', 'string', 'min:6'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $user->update($data);

        return new UserResource($user->fresh()->load('santris:id,nis,nama'));
    }

    public function destroy(User $user): JsonResponse
    {
        if ($user->role !== 'wali') {
            return response()->json(['message' => 'User bukan wali.'], 422);
        }

        $user->santris()->detach();
        $user->delete();

        return response()->json(['message' => 'Akun wali dihapus.']);
    }

    public function linkSantri(User $user, Santri $santri): JsonResponse
    {
        if ($user->role !== 'wali') {
            return response()->json(['message' => 'User bukan wali.'], 422);
        }

        if ($user->santris()->where('santri_id', $santri->id)->exists()) {
            return response()->json(['message' => 'Santri sudah terhubung ke akun ini.'], 422);
        }

        $user->santris()->attach($santri->id);

        return response()->json(['message' => "Santri {$santri->nama} berhasil ditautkan ke akun wali."]);
    }

    public function unlinkSantri(User $user, Santri $santri): JsonResponse
    {
        if ($user->role !== 'wali') {
            return response()->json(['message' => 'User bukan wali.'], 422);
        }

        $user->santris()->detach($santri->id);

        return response()->json(['message' => "Santri {$santri->nama} berhasil dilepaskan dari akun wali."]);
    }
}
