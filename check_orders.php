<?php

require_once 'vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as Capsule;

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Set up database connection
$capsule = new Capsule;
$capsule->addConnection([
    'driver'    => 'sqlite',
    'database'  => __DIR__ . '/database/database.sqlite',
    'prefix'    => '',
]);
$capsule->setAsGlobal();
$capsule->bootEloquent();

echo "=== Checking Database for DataSource Orders ===\n";

try {
    // Check orders with reference_id that have MTN products
    $orders = Capsule::table('orders')
        ->select('orders.id', 'orders.status', 'orders.reference_id', 'orders.created_at')
        ->join('order_product', 'orders.id', '=', 'order_product.order_id')
        ->join('products', 'order_product.product_id', '=', 'products.id')
        ->whereNotNull('orders.reference_id')
        ->where('products.name', 'like', '%mtn%')
        ->groupBy('orders.id', 'orders.status', 'orders.reference_id', 'orders.created_at')
        ->orderBy('orders.created_at', 'desc')
        ->limit(10)
        ->get();

    if ($orders->count() > 0) {
        echo "Found " . $orders->count() . " orders with reference IDs:\n\n";
        
        foreach ($orders as $order) {
            echo "Order ID: {$order->id}\n";
            echo "Status: {$order->status}\n";
            echo "Reference ID: {$order->reference_id}\n";
            echo "Created: {$order->created_at}\n";
            echo "---\n";
        }
        
        // Use the first order for testing
        $testOrder = $orders->first();
        echo "\n=== Selected Order for Testing ===\n";
        echo "Order ID: {$testOrder->id}\n";
        echo "Reference ID: {$testOrder->reference_id}\n";
        echo "Status: {$testOrder->status}\n";
        
        // Store the test order data in a file for the test script
        file_put_contents('test_order_data.json', json_encode([
            'order_id' => $testOrder->id,
            'reference_id' => $testOrder->reference_id,
            'status' => $testOrder->status
        ]));
        
    } else {
        echo "No orders found with reference IDs and MTN products.\n";
        
        // Check all orders with reference_id
        $allWithRef = Capsule::table('orders')
            ->whereNotNull('reference_id')
            ->count();
        echo "Total orders with reference_id: {$allWithRef}\n";
        
        // Check orders with MTN products
        $mtnOrders = Capsule::table('orders')
            ->join('order_product', 'orders.id', '=', 'order_product.order_id')
            ->join('products', 'order_product.product_id', '=', 'products.id')
            ->where('products.name', 'like', '%mtn%')
            ->count();
        echo "Orders with MTN products: {$mtnOrders}\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}