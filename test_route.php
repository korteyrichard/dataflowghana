<?php
// Simple test to check if the application is working
// Run this with: php test_route.php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';

// Test database connection
try {
    $pdo = new PDO('sqlite:' . database_path('database.sqlite'));
    echo "✅ Database connection successful\n";
    
    // Check if products table exists
    $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='products'");
    if ($stmt->fetch()) {
        echo "✅ Products table exists\n";
        
        // Check table structure
        $stmt = $pdo->query("PRAGMA table_info(products)");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "📋 Products table columns:\n";
        foreach ($columns as $column) {
            echo "  - {$column['name']} ({$column['type']})\n";
        }
    } else {
        echo "❌ Products table does not exist\n";
    }
    
    // Check if product_variants table exists
    $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='product_variants'");
    if ($stmt->fetch()) {
        echo "✅ Product variants table exists\n";
    } else {
        echo "❌ Product variants table does not exist\n";
    }
    
} catch (Exception $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "\n";
}

// Test route registration
try {
    $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
    echo "✅ Application kernel loaded\n";
} catch (Exception $e) {
    echo "❌ Application kernel failed: " . $e->getMessage() . "\n";
}

echo "\n🔍 Check the Laravel logs at storage/logs/laravel.log for more details\n";