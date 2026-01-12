<?php

// Test script to verify MTN Express bulk order functionality
require_once 'vendor/autoload.php';

use App\Models\Product;
use App\Models\ProductVariant;

// Initialize Laravel
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== MTN Express Bulk Order Test ===\n\n";

// Test 1: Check if MTN Express products exist
echo "1. Checking MTN Express products:\n";
$mtnExpressProducts = Product::where('name', 'LIKE', '%MTN Express%')->get();
foreach ($mtnExpressProducts as $product) {
    echo "   - Product ID: {$product->id}, Name: {$product->name}, Network: {$product->network}, Type: {$product->product_type}\n";
}

// Test 2: Check variants for each product type
echo "\n2. Checking MTN Express variants by product type:\n";
foreach (['agent_product', 'dealer_product', 'elite_product'] as $productType) {
    echo "   {$productType}:\n";
    $product = Product::where('name', 'LIKE', '%MTN Express%')
        ->where('product_type', $productType)
        ->first();
    
    if ($product) {
        $variants = ProductVariant::where('product_id', $product->id)->get();
        foreach ($variants as $variant) {
            $size = $variant->variant_attributes['size'] ?? 'N/A';
            echo "     - Size: {$size}, Price: {$variant->price}, Status: {$variant->status}\n";
        }
    } else {
        echo "     - No MTN Express product found for {$productType}\n";
    }
}

// Test 3: Simulate bulk order logic
echo "\n3. Testing bulk order logic:\n";
$networkId = 18; // Agent MTN Express
$networkMap = [
    5 => 'MTN',      // Agent MTN
    6 => 'TELECEL',  // Agent Telecel
    7 => 'ISHARE',   // Agent Ishare
    8 => 'BIGTIME',  // Agent Bigtime
    9 => 'MTN',      // Dealer MTN
    10 => 'TELECEL', // Dealer Telecel
    11 => 'ISHARE',  // Dealer Ishare
    12 => 'BIGTIME', // Dealer Bigtime
    13 => 'MTN',     // Elite MTN
    14 => 'TELECEL', // Elite Telecel
    15 => 'ISHARE',  // Elite Ishare
    16 => 'BIGTIME', // Elite Bigtime
];

// Test with Agent MTN Express (network_id = 5, is_express = true)
$networkId = 5;
$networkName = $networkMap[$networkId];
$isMtnExpress = true; // Simulating is_express = true
$productType = 'agent_product';

echo "   Testing: Network ID {$networkId} ({$networkName}), Express: " . ($isMtnExpress ? 'Yes' : 'No') . ", Type: {$productType}\n";

$productQuery = Product::where('network', $networkName)
    ->where('product_type', $productType);

if ($isMtnExpress) {
    $productQuery->where('name', 'LIKE', '%MTN Express%');
} else {
    $productQuery->where('name', 'NOT LIKE', '%MTN Express%');
}

$product = $productQuery->first();

if ($product) {
    echo "   ✓ Product found: {$product->name} (ID: {$product->id})\n";
    
    // Test finding a variant
    $variant = ProductVariant::where('product_id', $product->id)
        ->whereJsonContains('variant_attributes->size', '1GB')
        ->where('status', 'IN STOCK')
        ->first();
    
    if ($variant) {
        echo "   ✓ 1GB variant found: Price {$variant->price}, Status: {$variant->status}\n";
    } else {
        echo "   ✗ 1GB variant not found\n";
    }
} else {
    echo "   ✗ Product not found\n";
}

echo "\n=== Test Complete ===\n";