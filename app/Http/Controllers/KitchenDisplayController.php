<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeleteKitchenOrderRequest;
use App\Http\Requests\UnlockKitchenDisplayRequest;
use App\Http\Requests\UpdateKitchenDisplayOrderRequest;
use App\Services\KitchenDisplayAccess;
use App\Services\KitchenOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class KitchenDisplayController extends Controller
{
    public function __construct(
        private readonly KitchenDisplayAccess $access,
        private readonly KitchenOrderService $orders,
    ) {}

    public function index(Request $request): Response
    {
        return response()->view('kitchen.kiosk', ['unlocked' => $this->access->isUnlocked($request)])
            ->header('Cache-Control', 'no-store');
    }

    public function unlock(UnlockKitchenDisplayRequest $request): JsonResponse|RedirectResponse
    {
        if (! $this->access->unlock($request, $request->validated('pin'))) {
            throw ValidationException::withMessages(['pin' => 'Incorrect PIN. Please try again.']);
        }

        return $request->expectsJson()
            ? response()->json(['message' => 'Kitchen display unlocked.', 'csrf_token' => csrf_token()])->header('Cache-Control', 'no-store')
            : redirect()->route('kod.index');
    }

    public function lock(Request $request): JsonResponse
    {
        $this->access->lock($request);

        return response()->json(['message' => 'Kitchen display locked.'])->header('Cache-Control', 'no-store');
    }

    public function feed(): JsonResponse
    {
        $orders = $this->orders->activeOrders()->map(fn (array $order): array => [
            ...Arr::except($order, ['hold_id', 'served_url', 'resume_url', 'stop_url']),
            'status_url' => route('kod.status', $order['id']),
            'delete_url' => route('kod.delete', $order['id']),
        ]);

        return response()->json(['orders' => $orders])->header('Cache-Control', 'no-store');
    }

    public function update(UpdateKitchenDisplayOrderRequest $request, int $order): JsonResponse
    {
        $validated = $request->validated();
        $this->orders->transition($order, $validated['status'], (int) $validated['revision']);

        return response()->json(['message' => 'Kitchen order updated.']);
    }

    public function delete(DeleteKitchenOrderRequest $request, int $order): JsonResponse
    {
        $this->orders->deleteStopped($order, (int) $request->validated('revision'));

        return response()->json(['message' => 'Stopped kitchen ticket deleted. The bill is unchanged.']);
    }
}
