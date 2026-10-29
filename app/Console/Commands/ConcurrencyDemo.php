<?php

namespace App\Console\Commands;

use App\Exceptions\InsufficientStockException;
use App\Models\Product;
use App\Services\OrderService;
use Illuminate\Console\Command;

class ConcurrencyDemo extends Command
{
    protected $signature = 'demo:concurrency {--worker= : internal, do not use} {--product=5}';

    protected $description = 'Fire two simultaneous orders for the same product to prove no overselling';

    public function handle(OrderService $service): int
    {
        
        if ($label = $this->option('worker')) {
            return $this->runWorker($service, $label);
        }

        return $this->runCoordinator();
    }

    private function runCoordinator(): int
    {
        $productId = (int) $this->option('product');

        // Stock-ah 1 ku reset
        Product::findOrFail($productId)->update(['stock' => 1]);
        $this->info("Product #{$productId} stock reset to 1");

        $startFile = storage_path('app/demo-start.flag');
        @unlink($startFile);

        $php = PHP_BINARY;
        $artisan = base_path('artisan');

        $procs = [];
        foreach (['A', 'B'] as $label) {
            $cmd = "\"{$php}\" \"{$artisan}\" demo:concurrency --worker={$label} --product={$productId}";
            $procs[$label] = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes[$label]);
        }

        
        sleep(3);
        file_put_contents($startFile, 'go');

        foreach ($procs as $label => $proc) {
            $out = stream_get_contents($pipes[$label][1]);
            $err = stream_get_contents($pipes[$label][2]);
            proc_close($proc);
            $this->line(trim($out . $err));
        }

        @unlink($startFile);

        $stock = Product::find($productId)->stock;
        $this->newLine();
        $this->info("Final stock: {$stock}");
        $this->info($stock === 0 ? 'PASS: exactly one order succeeded, no overselling.' : 'CHECK: unexpected stock value.');

        return self::SUCCESS;
    }

    private function runWorker(OrderService $service, string $label): int
    {
        $startFile = storage_path('app/demo-start.flag');

        
        while (! file_exists($startFile)) {
            usleep(10000);
        }

        try {
            $order = $service->create([
                'email' => strtolower($label) . '@demo.test',
                'name' => "Demo {$label}",
                'items' => [['product_id' => (int) $this->option('product'), 'quantity' => 1]],
            ]);
            $this->line("Worker {$label}: SUCCESS (order #{$order->id})");
        } catch (InsufficientStockException $e) {
            $this->line("Worker {$label}: FAILED cleanly - {$e->getMessage()}");
        }

        return self::SUCCESS;
    }
}