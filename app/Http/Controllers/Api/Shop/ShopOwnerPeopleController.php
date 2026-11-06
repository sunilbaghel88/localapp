<?php

namespace App\Http\Controllers\Api\Shop;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Users\ShopOwnerPeopleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ShopOwnerPeopleController extends Controller
{
    public function __construct(protected ShopOwnerPeopleService $people)
    {
    }

    public function meta(Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        return response()->json($this->people->meta($actor));
    }

    public function index(Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $data = $request->validate([
            'kind' => ['required', Rule::in(['customer', 'partner'])],
            'q' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $paginator = $this->people
            ->peopleQuery($actor, $data['kind'], $data['q'] ?? null)
            ->paginate((int) ($data['per_page'] ?? 20));

        $paginator->getCollection()->transform(
            fn (User $user) => $this->people->payload($user, $actor)
        );

        return response()->json([
            'users' => $paginator,
        ]);
    }

    public function show(Request $request, User $user): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $this->people->authorizeActor($actor);
        abort_unless($actor->canAssignUserTypesTo($user), 403);
        abort_if($user->hasRole('super_admin') && ! $actor->hasRole('super_admin'), 403);

        return response()->json([
            'user' => $this->people->payload($user, $actor),
        ]);
    }

    public function storeCustomer(Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $request->merge([
            'phone' => $this->people->normalizePhone((string) $request->input('phone', '')),
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'min:10', 'max:15'],
            'shop_id' => ['nullable', 'integer', 'exists:shops,id'],
        ]);

        $user = $this->people->createCustomer($actor, $data);

        return response()->json([
            'user' => $this->people->payload($user, $actor),
        ], 201);
    }

    public function storePartner(Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $request->merge([
            'phone' => $this->people->normalizePhone((string) $request->input('phone', '')),
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'min:10', 'max:15'],
            'shop_id' => ['required', 'integer', 'exists:shops,id'],
            'user_type_id' => ['nullable', 'integer', 'exists:user_types,id'],
        ]);

        $user = $this->people->createPartner($actor, $data);

        return response()->json([
            'user' => $this->people->payload($user, $actor),
        ], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        if ($request->filled('phone')) {
            $request->merge([
                'phone' => $this->people->normalizePhone((string) $request->input('phone', '')),
            ]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'min:10', 'max:15'],
            'is_active' => ['nullable', 'boolean'],
            'user_type_ids' => ['nullable', 'array'],
            'user_type_ids.*' => ['integer', 'exists:user_types,id'],
            'shop_ids' => ['nullable', 'array'],
            'shop_ids.*' => ['integer', 'exists:shops,id'],
        ]);

        $updated = $this->people->updatePerson($actor, $user, $data);

        return response()->json([
            'user' => $this->people->payload($updated, $actor),
        ]);
    }
}
