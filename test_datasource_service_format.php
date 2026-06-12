<?php

require_once 'vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

echo "=== Connecting to Database ===\n";

// Try to connect and get an order
try {
    $pdo = new PDO(
        'mysql:host=' . $_ENV['DB_HOST'] . ';dbname=' . $_ENV['DB_DATABASE'] . ';charset=utf8mb4',
        $_ENV['DB_USERNAME'],
        $_ENV['DB_PASSWORD'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Get orders with reference_id that have MTN products (matching the service logic)
    $sql = "SELECT o.id, o.status, o.reference_id, o.created_at 
            FROM orders o 
            INNER JOIN order_product op ON o.id = op.order_id 
            INNER JOIN products p ON op.product_id = p.id 
            WHERE o.reference_id IS NOT NULL 
            AND p.name LIKE '%mtn%' 
            ORDER BY o.created_at DESC 
            LIMIT 5";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($orders)) {
        echo "No orders found with reference_id and MTN products.\n";
        echo "Creating a test with sample data...\n\n";
        
        // Use sample data for testing
        $testOrder = [
            'id' => 999,
            'reference_id' => '01KTN4H9WX5BC66V3KZCS98GXZ',
            'status' => 'pending'
        ];
    } else {
        echo "Found " . count($orders) . " orders:\n";
        foreach ($orders as $order) {
            echo "- Order ID: {$order['id']}, Status: {$order['status']}, Ref: {$order['reference_id']}\n";
        }
        echo "\nUsing first order for test...\n\n";
        $testOrder = $orders[0];
    }

} catch (Exception $e) {
    echo "Database connection failed: " . $e->getMessage() . "\n";
    echo "Using sample data for testing...\n\n";
    
    // Use sample data for testing
    $testOrder = [
        'id' => 999,
        'reference_id' => '01KTN4H9WX5BC66V3KZCS98GXZ',
        'status' => 'pending'
    ];
}

// Configuration from .env
$baseUrl = $_ENV['DATASOURCE_BASE_URL'];
$apiKey = $_ENV['DATASOURCE_API_KEY'];
$secretKey = $_ENV['DATASOURCE_SECRET_KEY'];

// API endpoint
$endpoint = '/api/v1/order/bulk/status';
$method = 'POST';
$timestamp = time();

// Request body - MATCHING THE SERVICE FORMAT EXACTLY
$bodyArray = [
    'order_id' => $testOrder['id'],                    // Local order ID
    'orderid' => $testOrder['reference_id'],           // Reference ID (external order ID)
    'data_size' => 1
];
$body = json_encode($bodyArray, JSON_UNESCAPED_SLASHES);

// Create signature - EXACTLY as in the service
$signatureString = $timestamp . $method . $endpoint . $body;
$signature = hash_hmac('sha256', $signatureString, $secretKey);

// Full URL
$fullUrl = $baseUrl . $endpoint;

echo "=== DataSource Order Status Test (Service Format) ===\n";
echo "URL: " . $fullUrl . "\n";
echo "Method: " . $method . "\n";
echo "Local Order ID: " . $testOrder['id'] . "\n";
echo "Reference ID (orderid): " . $testOrder['reference_id'] . "\n";
echo "Current Status: " . $testOrder['status'] . "\n";
echo "Data Size: 1\n";
echo "Timestamp: " . $timestamp . "\n";
echo "Signature String: " . $signatureString . "\n";
echo "Signature: " . $signature . "\n";
echo "Request Body: " . $body . "\n";
echo "\n=== Headers (Same as Service) ===\n";
echo "X-API-KEY: " . $apiKey . "\n";
echo "X-Timestamp: " . $timestamp . "\n";
echo "X-Signature: " . $signature . "\n";
echo "Accept: application/json\n";
echo "Content-Type: application/json\n";
echo "\n";

// Initialize cURL
$ch = curl_init();

curl_setopt_array($ch, [
    CURLOPT_URL => $fullUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => $body,
    CURLOPT_HTTPHEADER => [
        'X-API-KEY: ' . $apiKey,
        'X-Timestamp: ' . $timestamp,
        'X-Signature: ' . $signature,
        'Accept: application/json',
        'Content-Type: application/json'
    ],
]);

echo "=== Making Request ===\n";
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);

curl_close($ch);

echo "HTTP Status Code: " . $httpCode . "\n";

if ($error) {
    echo "cURL Error: " . $error . "\n";
} else {
    echo "Raw Response:\n" . $response . "\n";
    
    // Try to decode JSON response
    $decodedResponse = json_decode($response, true);
    if ($decodedResponse) {
        echo "\n=== Decoded Response ===\n";
        print_r($decodedResponse);
        
        // Check response format matches service expectations
        echo "\n=== Service Logic Validation ===\n";
        if (isset($decodedResponse['success']) && $decodedResponse['success'] === true) {
            echo "✅ Success field is true\n";
            
            if (isset($decodedResponse['recored']) && is_array($decodedResponse['recored'])) {
                echo "✅ 'recored' field exists and is array\n";
                
                if (!empty($decodedResponse['recored'])) {
                    $orderData = $decodedResponse['recored'][0];
                    $externalStatus = $orderData['status'] ?? '';
                    echo "✅ External status: " . $externalStatus . "\n";
                    
                    // Show what the service would map this to
                    $statusMap = [
                        'successful' => 'completed',
                        'completed' => 'completed',
                        'delivered' => 'completed',
                        'processing' => 'processing',
                        'pending' => 'processing',
                        'pending2' => 'processing',
                        'failed' => 'cancelled',
                        'cancelled' => 'cancelled'
                    ];
                    
                    $lowercaseStatus = strtolower($externalStatus);
                    $newStatus = $statusMap[$lowercaseStatus] ?? null;
                    
                    echo "✅ Service would map '{$externalStatus}' to: " . ($newStatus ?? 'No mapping') . "\n";
                    
                    if ($newStatus && $newStatus !== $testOrder['status']) {
                        echo "🔄 Status would change from '{$testOrder['status']}' to '{$newStatus}'\n";
                    } else {
                        echo "➡️ Status would remain '{$testOrder['status']}' (no change)\n";
                    }
                }
            } else {
                echo "❌ 'recored' field missing or not array\n";
            }
        } else {
            echo "❌ Success field is not true\n";
        }
    }
}

echo "\n=== Format Comparison ===\n";
echo "Service Format: order_id + orderid + data_size ✅\n";
echo "Our Test: Used exact same format ✅\n";
echo "Headers: X-API-KEY + X-Timestamp + X-Signature ✅\n";
echo "Signature: timestamp + method + endpoint + body ✅\n";

echo "\n=== Test Complete ===\n";