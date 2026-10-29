<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientStockException;
use App\Http\Requests\StoreOrderRequest;
use App\Models\Customer;
use App\Models\Product;
use App\Services\OrderService;

class OrderController extends Controller
{
    public function __construct(private OrderService $orderService)
    {
    }

    public function store(StoreOrderRequest $request)
    {
        try {
            $order = $this->orderService->create($request->validated());
        } catch (InsufficientStockException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($order, 201);
    }

    public function history(string $email)
    {
        $customer = Customer::where('email', $email)->firstOrFail();

        return response()->json(
            $customer->orders()->with('items.product')->latest()->get()
        );
    }

    public function lowStock()
    {
        $products = Product::where('stock', '<', config('store.low_stock_threshold'))
            ->orderBy('stock')
            ->get();

        return response()->json($products);
    }
}