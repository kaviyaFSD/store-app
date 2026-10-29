<?php

namespace App\Jobs;

use App\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendOrderConfirmation implements ShouldQueue
{
    use Queueable;

    public function __construct(public Order $order)
    {
    }

    public function handle(): void
    {
        $this->order->loadMissing('customer');

        Log::info("Order confirmation sent to {$this->order->customer->email} for order #{$this->order->id}, total {$this->order->grand_total}");
    }
}