<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Product;

$products = Product::orderBy('created_at', 'desc')->get();

echo "Total Products: " . $products->count() . "\n";
foreach ($products as $p) {
    echo "ID: {$p->id} | Name: {$p->name} | Created: {$p->created_at}\n";
}

// Logic to keep the newest one (created within last 1 hour) and delete others?
// Or just let me see the output first.
