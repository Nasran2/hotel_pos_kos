<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\OrderToken;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class KitchenOrderService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function send(OrderToken $token, array $payload): int
    {
        OrderToken::query()->whereKey($token->id)->lockForUpdate()->firstOrFail();
        $items = collect($payload['items'])->map(fn (array $item): array => [
            'product_id' => (int) $item['id'],
            'name' => $item['name'],
            'quantity' => (float) $item['quantity'],
            'note' => $item['note'] ?? null,
        ])->all();
        $order = DB::table('kitchen_orders')->where('order_token_id', $token->id)->lockForUpdate()->first();
        if ($order && (in_array($order->status, ['cancelled', 'stop_requested', 'stopped'], true) || $order->deleted_at)) {
            throw ValidationException::withMessages(['order' => 'This kitchen ticket is stopped or awaiting a stop confirmation. Start a new order to send more items.']);
        }
        if ($order && json_decode($order->items, true) === $items && $order->note === ($payload['note'] ?? null)) {
            return $order->id;
        }
        $values = [
            'items' => json_encode($items, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION),
            'note' => $payload['note'] ?? null,
            'status' => 'queued',
            'ready_at' => null,
            'served_at' => null,
            'updated_at' => now(),
        ];
        if ($order) {
            DB::table('kitchen_orders')->where('id', $order->id)->update([
                ...$values, 'previous_items' => $order->items, 'revision' => $order->revision + 1,
            ]);
            $id = $order->id;
        } else {
            $id = DB::table('kitchen_orders')->insertGetId([
                ...$values, 'order_token_id' => $token->id, 'created_by' => auth()->id(),
                'sent_at' => now(), 'created_at' => now(),
            ]);
        }
        ActivityLog::record('send', 'kitchen', 'Order sent to kitchen.', ['kitchen_order_id' => $id, 'token' => $token->token_number]);

        return $id;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function activeOrders(): Collection
    {
        $orders = DB::table('kitchen_orders')
            ->join('order_tokens', 'order_tokens.id', '=', 'kitchen_orders.order_token_id')
            ->leftJoin('users as stop_requesters', 'stop_requesters.id', '=', 'kitchen_orders.stop_requested_by')
            ->whereNull('kitchen_orders.deleted_at')
            ->whereIn('kitchen_orders.status', ['queued', 'preparing', 'ready', 'stop_requested', 'stopped'])
            ->orderBy('kitchen_orders.sent_at')->orderBy('kitchen_orders.id')
            ->select('kitchen_orders.*', 'order_tokens.token_number', 'order_tokens.token_date', 'stop_requesters.name as stop_requester')->get();
        $tokens = $orders->pluck('order_token_id');
        $holds = DB::table('hold_orders')->leftJoin('restaurant_tables', 'restaurant_tables.id', '=', 'hold_orders.restaurant_table_id')
            ->leftJoin('waiters', 'waiters.id', '=', 'hold_orders.waiter_id')
            ->whereIn('hold_orders.order_token_id', $tokens)
            ->select('hold_orders.id', 'hold_orders.order_token_id', 'hold_orders.status', 'hold_orders.deleted_at', 'restaurant_tables.number as table_number', 'waiters.name as waiter_name')
            ->orderBy('hold_orders.id')->get()->keyBy('order_token_id');
        $sales = DB::table('sales')->leftJoin('restaurant_tables', 'restaurant_tables.id', '=', 'sales.restaurant_table_id')
            ->leftJoin('waiters', 'waiters.id', '=', 'sales.waiter_id')
            ->whereIn('sales.order_token_id', $tokens)->whereNull('sales.deleted_at')
            ->select('sales.order_token_id', 'restaurant_tables.number as table_number', 'waiters.name as waiter_name')
            ->get()->keyBy('order_token_id');

        return $orders->map(function (object $order) use ($holds, $sales): array {
            $latestHold = $holds->get($order->order_token_id);
            $activeHold = $latestHold && ! $latestHold->deleted_at && in_array($latestHold->status, ['hold', 'payment_pending'], true) ? $latestHold : null;
            $source = $activeHold ?? $sales->get($order->order_token_id) ?? $latestHold;

            return [
                'id' => $order->id, 'status' => $order->status, 'revision' => $order->revision,
                'token' => str_pad((string) $order->token_number, 2, '0', STR_PAD_LEFT),
                'token_date' => $order->token_date, 'table' => $source?->table_number,
                'waiter' => $source?->waiter_name, 'hold_id' => $activeHold?->id,
                'items' => json_decode($order->items, true),
                'previous_items' => $order->previous_items ? json_decode($order->previous_items, true) : null,
                'note' => $order->note, 'sent_at' => Carbon::parse($order->sent_at)->toIso8601String(),
                'ready_at' => $order->ready_at,
                'stop_reason' => $order->stop_reason, 'stop_requester' => $order->stop_requester,
                'stop_requested_at' => $order->stop_requested_at, 'stopped_at' => $order->stopped_at,
                'stop_url' => route('pos.kitchen.stop', $order->id),
                'delete_url' => route('kitchen.delete', $order->id),
                'status_url' => route('kitchen.status', $order->id),
                'served_url' => route('pos.kitchen.served', $order->id),
                'resume_url' => $activeHold ? route('pos.resume-held-order', $activeHold->id) : null,
            ];
        });
    }

    public function transition(int $id, string $status, int $revision): void
    {
        DB::transaction(function () use ($id, $status, $revision): void {
            $order = DB::table('kitchen_orders')->where('id', $id)->whereNull('deleted_at')->lockForUpdate()->first();
            abort_if(! $order, 404);
            if ((int) $order->revision !== $revision) {
                throw ValidationException::withMessages(['revision' => 'This order changed. Refresh and review the latest ticket.']);
            }
            if ($order->status === $status) {
                return;
            }
            $allowed = [
                'queued' => ['preparing', 'ready', 'cancelled', 'stopped'],
                'preparing' => ['ready', 'cancelled', 'stopped'],
                'ready' => ['served', 'cancelled'],
                'stop_requested' => ['stopped'],
            ];
            if (! in_array($status, $allowed[$order->status] ?? [], true)) {
                throw ValidationException::withMessages(['status' => 'This ticket has already moved to another stage. Refresh the kitchen board.']);
            }
            DB::table('kitchen_orders')->where('id', $id)->update([
                'status' => $status, 'updated_at' => now(),
                ...($status === 'ready' ? ['ready_at' => now()] : []),
                ...($status === 'served' ? ['served_at' => now()] : []),
                ...($status === 'stopped' ? ['stopped_at' => now()] : []),
            ]);
            ActivityLog::record($status, 'kitchen', 'Kitchen ticket marked '.$status.'.', ['kitchen_order_id' => $id]);
        });
    }

    public function requestStop(int $id, int $revision, ?string $reason): void
    {
        DB::transaction(function () use ($id, $revision, $reason): void {
            $order = DB::table('kitchen_orders')->where('id', $id)->whereNull('deleted_at')->lockForUpdate()->first();
            abort_if(! $order, 404);
            if ((int) $order->revision !== $revision) {
                throw ValidationException::withMessages(['revision' => 'This order changed. Refresh and review the latest ticket.']);
            }
            if ($order->status === 'stop_requested') {
                return;
            }
            if (! in_array($order->status, ['queued', 'preparing'], true)) {
                throw ValidationException::withMessages(['status' => 'Only waiting or preparing tickets can be stopped. Refresh the kitchen queue.']);
            }
            $this->recordStopRequest($order, $reason ?: 'Cashier requested preparation to stop.');
        });
    }

    public function cancelForToken(?int $tokenId): void
    {
        DB::transaction(function () use ($tokenId): void {
            $order = DB::table('kitchen_orders')->where('order_token_id', $tokenId)->whereNull('deleted_at')->lockForUpdate()->first();
            if (! $order) {
                return;
            }
            if (in_array($order->status, ['queued', 'preparing'], true)) {
                $this->recordStopRequest($order, 'Held bill cancelled by cashier. Stop preparing this order.');
            } elseif ($order->status === 'ready') {
                DB::table('kitchen_orders')->where('id', $order->id)->update(['status' => 'cancelled', 'updated_at' => now()]);
            }
        });
    }

    public function deleteStopped(int $id, int $revision): void
    {
        DB::transaction(function () use ($id, $revision): void {
            $order = DB::table('kitchen_orders')->where('id', $id)->whereNull('deleted_at')->lockForUpdate()->first();
            abort_if(! $order, 404);
            if ((int) $order->revision !== $revision) {
                throw ValidationException::withMessages(['revision' => 'This order changed. Refresh and review the latest ticket.']);
            }
            if ($order->status !== 'stopped') {
                throw ValidationException::withMessages(['status' => 'Stop preparation before deleting this kitchen ticket.']);
            }
            DB::table('kitchen_orders')->where('id', $id)->update(['deleted_at' => now(), 'updated_at' => now()]);
            ActivityLog::record('delete', 'kitchen', 'Stopped kitchen ticket removed from the display.', ['kitchen_order_id' => $id]);
        });
    }

    private function recordStopRequest(object $order, string $reason): void
    {
        DB::table('kitchen_orders')->where('id', $order->id)->update([
            'status' => 'stop_requested', 'stop_requested_by' => auth()->id(),
            'stop_reason' => $reason, 'stop_requested_at' => now(), 'updated_at' => now(),
        ]);
        ActivityLog::record('stop_request', 'kitchen', 'Cashier requested kitchen preparation to stop.', ['kitchen_order_id' => $order->id, 'reason' => $reason]);
    }
}
