<?php

namespace App\Http\Controllers\Api\Shop;

use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Models\MeetingPhoto;
use App\Models\Shop;
use App\Services\Sms\SmsSender;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ShopMeetingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', \App\Models\Order::class);

        $perPage = min((int) $request->get('per_page', 20), 50);
        $meetings = Meeting::query()
            ->whereHas('shop', fn ($q) => $q->where('user_id', Auth::id()))
            ->with(['shop:id,name', 'photos'])
            ->latest('starts_at')
            ->paginate($perPage);

        $meetings->getCollection()->transform(fn (Meeting $meeting) => $meeting->toApiArray());

        return response()->json([
            'meetings' => $meetings,
        ]);
    }

    public function show(Meeting $meeting): JsonResponse
    {
        $this->authorize('viewAny', \App\Models\Order::class);
        $this->ensureOwned($meeting);

        $meeting->load(['shop:id,name', 'photos']);

        return response()->json([
            'meeting' => $meeting->toApiArray(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', \App\Models\Order::class);

        $data = $request->validate([
            'shop_id' => ['required', 'integer', 'exists:shops,id'],
            'title' => ['required', 'string', 'max:255'],
            'details' => ['required', 'string', 'max:5000'],
            'location' => ['nullable', 'string', 'max:255'],
            'starts_at' => ['required', 'date'],
        ]);

        $shop = Shop::query()
            ->where('user_id', Auth::id())
            ->where('id', (int) $data['shop_id'])
            ->firstOrFail();

        $meeting = Meeting::create([
            'shop_id' => $shop->id,
            'created_by' => Auth::id(),
            'title' => $data['title'],
            'details' => $data['details'],
            'location' => $data['location'] ?? null,
            'starts_at' => $data['starts_at'],
        ]);

        $meeting->load(['shop:id,name', 'photos']);
        app(SmsSender::class)->notifyMeetingCreated($meeting);

        return response()->json([
            'meeting' => $meeting->toApiArray(),
        ], 201);
    }

    public function storePhotos(Request $request, Meeting $meeting): JsonResponse
    {
        $this->authorize('create', \App\Models\Order::class);
        $this->ensureOwned($meeting);

        $request->validate([
            'photos' => ['required', 'array', 'min:1', 'max:10'],
            'photos.*' => ['file', 'image', 'max:15120', 'mimes:jpeg,jpg,png,webp'],
        ]);

        $files = $request->file('photos', []);
        if (! is_array($files)) {
            $files = $files ? [$files] : [];
        }

        foreach ($files as $file) {
            MeetingPhoto::create([
                'meeting_id' => $meeting->id,
                'uploaded_by' => Auth::id(),
                'path' => $file->store('meetings', 'public'),
            ]);
        }

        $meeting->load(['shop:id,name', 'photos']);

        return response()->json([
            'meeting' => $meeting->toApiArray(),
        ], 201);
    }

    public function destroyPhoto(Meeting $meeting, MeetingPhoto $photo): JsonResponse
    {
        $this->authorize('create', \App\Models\Order::class);
        $this->ensureOwned($meeting);

        if ((int) $photo->meeting_id !== (int) $meeting->id) {
            throw ValidationException::withMessages([
                'photo' => [__('This photo does not belong to the meeting.')],
            ]);
        }

        $photo->delete();
        $meeting->load(['shop:id,name', 'photos']);

        return response()->json([
            'meeting' => $meeting->toApiArray(),
        ]);
    }

    protected function ensureOwned(Meeting $meeting): void
    {
        $owned = Shop::query()
            ->where('user_id', Auth::id())
            ->where('id', $meeting->shop_id)
            ->exists();

        if (! $owned) {
            abort(403);
        }
    }
}
