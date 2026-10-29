<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Jobs\SendOrderConfirmation;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function create(array $data): Order
    {
        $order = DB::transaction(function () use ($data) {
            $customer = Customer::firstOrCreate(
                ['email' => $data['email']],
                ['name' => $data['name']]
            );

            // Same product rendu thadava vandha quantity-ah serthukkom
            $quantities = [];
            foreach ($data['items'] as $item) {
                $id = $item['product_id'];
                $quantities[$id] = ($quantities[$id] ?? 0) + $item['quantity'];
            }

            // Rows-ah lock pannrom (concurrency safety)
            $products = Product::whereIn('id', array_keys($quantities))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $subtotal = 0;
            $tax = 0;
            $lines = [];

            foreach ($quantities as $productId => $qty) {
                $product = $products[$productId];

                if ($product->stock < $qty) {
                    throw new InsufficientStockException(
                        "Not enough stock for {$product->name}. Available: {$product->stock}, requested: {$qty}"
                    );
                }

                $lineSubtotal = round($product->price * $qty, 2);
                $lineTax = round($lineSubtotal * $product->tax_percent / 100, 2);

                $subtotal += $lineSubtotal;
                $tax += $lineTax;

                $lines[] = [
                    'product_id' => $product->id,
                    'quantity' => $qty,
                    'unit_price' => $product->price,        // snapshot
                    'tax_percent' => $product->tax_percent, // snapshot
                    'line_total' => $lineSubtotal + $lineTax,
                ];

                $product->decrement('stock', $qty);
            }

            $order = Order::create([
                'customer_id' => $customer->id,
                'subtotal' => $subtotal,
                'tax' => $tax,
                'grand_total' => $subtotal + $tax,
            ]);

            $order->items()->createMany($lines);

            return $order;
        });

        
        SendOrderConfirmation::dispatch($order);

        return $order->load('customer', 'items.product');
    }
}