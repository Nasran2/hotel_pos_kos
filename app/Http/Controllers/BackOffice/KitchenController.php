<?php

namespace App\Http\Controllers\BackOffice;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeleteKitchenOrderRequest;
use App\Http\Requests\RequestKitchenStopRequest;
use App\Http\Requests\UpdateKitchenOrderRequest;
use App\Services\KitchenOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class KitchenController extends Controller
{
    public function __construct(private readonly KitchenOrderService $kitchenOrders) {}

    public function index(): RedirectResponse
    {
        return redirect()->route('kod.index');
    }

    public function feed(): JsonResponse
    {
        abort_unless(Gate::allows('pos.access') || Gate::allows('kitchen.view'), 403);

        return response()->json(['orders' => $this->kitchenOrders->activeOrders()])->header('Cache-Control', 'no-store');
    }

    public function update(UpdateKitchenOrderRequest $request, int $order): JsonResponse
    {
        $validated = $request->validated();
        $this->kitchenOrders->transition($order, $validated['status'], (int) $validated['revision']);

        return response()->json(['message' => 'Kitchen order updated.']);
    }

    public function delete(DeleteKitchenOrderRequest $request, int $order): JsonResponse
    {
        $this->kitchenOrders->deleteStopped($order, (int) $request->validated('revision'));

        return response()->json(['message' => 'Stopped kitchen ticket deleted. The bill is unchanged.']);
    }

    public function requestStop(RequestKitchenStopRequest $request, int $order): JsonResponse
    {
        $this->kitchenOrders->requestStop($order, (int) $request->validated('revision'), $request->validated('reason'));

        return response()->json(['message' => 'Stop request sent. Waiting for the kitchen to confirm.']);
    }
}
