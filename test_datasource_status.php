<?php

require_once 'vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Configuration
$baseUrl = $_ENV['DATASOURCE_BASE_URL'];
$apiKey = $_ENV['DATASOURCE_API_KEY'];
$secretKey = $_ENV['DATASOURCE_SECRET_KEY'];

// Test data
$orderid = '01KTN4H9WX5BC66V3KZCS98GXZ';
$dataSize = 1;

// API endpoint
$endpoint = '/api/v1/order/bulk/status';
$method = 'POST';
$timestamp = time();

// Request body
$bodyArray = [
    'orderid' => $orderid,
    'data_size' => $dataSize
];
$body = json_encode($bodyArray, JSON_UNESCAPED_SLASHES);

// Create signature
$signatureString = $timestamp . $method . $endpoint . $body;
$signature = hash_hmac('sha256', $signatureString, $secretKey);

// Full URL
$fullUrl = $baseUrl . $endpoint;

echo "=== DataSource Order Status Test ===\n";
echo "URL: " . $fullUrl . "\n";
echo "Method: " . $method . "\n";
echo "Order ID: " . $orderid . "\n";
echo "Data Size: " . $dataSize . "\n";
echo "Timestamp: " . $timestamp . "\n";
echo "Signature String: " . $signatureString . "\n";
echo "Signature: " . $signature . "\n";
echo "Request Body: " . $body . "\n";
echo "\n=== Headers ===\n";
echo "X-API-KEY: " . $apiKey . "\n";
echo "X-Timestamp: " . $timestamp . "\n";
echo "X-Signature: " . $signature . "\n";
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
    echo "Response:\n" . $response . "\n";
    
    // Try to decode JSON response
    $decodedResponse = json_decode($response, true);
    if ($decodedResponse) {
        echo "\n=== Decoded Response ===\n";
        print_r($decodedResponse);
    }
}

echo "\n=== Test Complete ===\n";