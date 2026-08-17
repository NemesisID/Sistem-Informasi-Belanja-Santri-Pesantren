<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StaffRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StaffController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min((int) $request->integer('per_page', 15) ?: 15, (int) config('koin.max_per_page'));

        $query = User::where('role', 'staff')
            ->when($request->input('search'), function ($q, $search) {
                $q->where(fn ($q) => $q->where('name', 'like', "%{$search}%")
                    ->orWhere('nip', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%"));
            })
            ->when($request->input('jabatan'), fn ($q, $jabatan) => $q->where('jabatan', $jabatan))
            ->when($request->has('status'), function ($q) use ($request) {
                $q->where('is_active', $request->boolean('status'));
            })
            ->orderBy('name');

        return UserResource::collection($query->paginate($perPage));
    }

    public function store(StaffRequest $request): JsonResponse
    {
        $staff = User::create($request->validated() + ['role' => 'staff']);

        return (new UserResource($staff))->response()->setStatusCode(201);
    }

    public function update(StaffRequest $request, User $user): UserResource
    {
        $data = $request->validated();

        // Password kosong = tidak diganti
        if (empty($data['password'])) {
            unset($data['password']);
        }

        $user->update($data);

        return new UserResource($user->fresh());
    }

    public function destroy(User $user): JsonResponse
    {
        if ($user->role !== 'staff') {
            return response()->json(['message' => 'User bukan staff.'], 422);
        }

        $user->delete();

        return response()->json(['message' => 'Staff dihapus.']);
    }
}
