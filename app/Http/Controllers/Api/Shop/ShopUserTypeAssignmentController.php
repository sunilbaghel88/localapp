<?php

namespace App\Http\Controllers\Api\Shop;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShopUserTypeAssignmentController extends Controller
{
    protected function actor(): User
    {
        /** @var User $user */
        $user = request()->user();

        abort_unless($user->canAssignUserTypes(), 403);

        return $user;
    }

    public function users(Request $request): JsonResponse
    {
        $this->actor();

        $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'user_type_id' => ['nullable', 'integer', 'exists:user_types,id'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $q = trim((string) $request->query('q', ''));
        $perPage = (int) $request->query('per_page', 20);

        $query = User::query()
            ->with('userTypes')
            ->orderByName();

        if ($q !== '') {
            $needle = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $q).'%';
            $query->where(function ($builder) use ($needle) {
                $builder->whereNameLike($needle)
                    ->orWhere('email', 'like', $needle)
                    ->orWhere('phone', 'like', $needle);
            });
        }

        if ($request->filled('user_type_id')) {
            $query->withUserTypeId((int) $request->query('user_type_id'));
        }

        $paginator = $query->paginate($perPage);

        $paginator->getCollection()->transform(fn (User $user) => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'user_types' => $user->userTypesPayload(),
        ]);

        return response()->json(['users' => $paginator]);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $actor = $this->actor();

        abort_unless($actor->canAssignUserTypesTo($user), 403);

        $validated = $request->validate([
            'user_type_ids' => ['present', 'array'],
            'user_type_ids.*' => ['integer', 'exists:user_types,id'],
        ]);

        $typeIds = collect($validated['user_type_ids'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $user->userTypes()->sync($typeIds);
        $user->load('userTypes');

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'user_types' => $user->userTypesPayload(),
            ],
        ]);
    }
}
