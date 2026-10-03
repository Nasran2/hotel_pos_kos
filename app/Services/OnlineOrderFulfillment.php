<?php

namespace App\Services;

use App\Models\OnlineOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OnlineOrderFulfillment
{
    public function lockForToken(int $tokenId): ?OnlineOrder
    {
        return OnlineOrder::query()->where('order_token_id', $tokenId)->lockForUpdate()->first();
    }

    public function syncKitchenStatus(?OnlineOrder $order, string $status): void
    {
        if (! $order || in_array($order->order_status, ['cancelled', 'delivered', 'out_for_delivery'], true)) {
            return;
        }
        $onlineStatus = match ($status) {
            'preparing' => 'preparing',
            'ready', 'served' => 'ready',
            default => null,
        };
        if (! $onlineStatus) {
            return;
        }
        $this->reduceStock($order);
        $order->update(['order_status' => $onlineStatus]);
        DB::table('sales')->where('id', $order->sale_id)->update(['online_order_status' => $onlineStatus, 'updated_at' => now()]);
    }

    public function reduceStock(OnlineOrder $order): void
    {
        if ($order->stock_reduced_at) {
            return;
        }
        foreach ($order->items()->orderBy('product_id')->get() as $item) {
            $product = DB::table('products')->where('id', $item->product_id)->lockForUpdate()->first();
            if (! $product || ! $product->maintain_stock) {
                continue;
            }
            if ((float) $product->stock_quantity < (float) $item->qty) {
                throw ValidationException::withMessages(['order' => $product->name.' does not have enough stock.']);
            }
            $balance = (float) $product->stock_quantity - (float) $item->qty;
            DB::table('products')->where('id', $product->id)->update(['stock_quantity' => $balance, 'updated_at' => now()]);
            DB::table('stock_movements')->insert([
                'product_id' => $product->id, 'type' => 'online_order', 'quantity' => -(float) $item->qty,
                'balance_after' => $balance, 'source_type' => 'online_order', 'source_id' => $order->id,
                'note' => 'Online order stock deduction', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $order->update(['stock_reduced_at' => now()]);
        DB::table('sales')->where('id', $order->sale_id)->update(['stock_reduced_at' => now(), 'updated_at' => now()]);
    }
}
