<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    private $apiUrl = 'https://api.bulkclix.com/api/v1/sms-api/send';
    private $apiKey;
    private $senderId;

    public function __construct()
    {
        $this->apiKey = config('services.bulkclix.api_key');
        $this->senderId = config('services.bulkclix.sender_id');
    }

    public function sendSms(string $phoneNumber, string $message): bool
    {
        try {
            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->apiUrl, [
                'sender_id' => $this->senderId,
                'message' => $message,
                'recipients' => [$phoneNumber]
            ]);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('SMS sending failed: ' . $e->getMessage());
            return false;
        }
    }
}