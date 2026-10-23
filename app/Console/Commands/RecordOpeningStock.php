<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\StockService;
use Illuminate\Console\Command;

class RecordOpeningStock extends Command
{
    protected $signature = 'megamall:opening-stock';

    protected $description = 'Write "Opening stock" into the stock history for products that have none yet (run once after upgrading)';

    public function handle(StockService $stock): int
    {
        $count = 0;
        Product::with('variants')->chunkById(100, function ($products) use ($stock, &$count) {
            foreach ($products as $product) {
                $stock->recordOpeningStock($product);
                $count++;
            }
        });

        $this->info("Checked {$count} products. Stock history now explains every current stock level.");

        return self::SUCCESS;
    }
}
