<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MoolreSmsService
{
    private $apiUrl = 'https://api.moolre.com/open/sms/send';
    private $apiKey;
    private $senderId;

    public function __construct()
    {
        $this->apiKey = config('services.moolre.api_key');
        $this->senderId = 'jaybart Svc';
    }

    public function sendSms(string $phoneNumber, string $message): bool
    {
        try {
            $response = Http::withHeaders([
                'X-API-VASKEY' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->apiUrl, [
                'type' => 1,
                'senderid' => "DF-Ghana",
                'messages' => [
                    [
                        'recipient' => $phoneNumber,
                        'message' => $message
                    ]
                ]
            ]);

            $responseData = $response->json();
            return $response->successful() && isset($responseData['status']) && $responseData['status'] === 1;
        } catch (\Exception $e) {
            Log::error('SMS sending failed: ' . $e->getMessage());
            return false;
        }
    }
}