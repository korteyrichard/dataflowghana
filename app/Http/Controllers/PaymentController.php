<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    public function initializePayment(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'amount' => 'required|numeric|min:1'
        ]);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('paystack.secret_key'),
            'Content-Type' => 'application/json',
        ])->post('https://api.paystack.co/transaction/initialize', [
            'email' => $request->email,
            'amount' => $request->amount * 100, // Convert to kobo
            'callback_url' => route('payment.callback'),
            'reference' => Str::random(16)
        ]);

        if ($response->successful()) {
            return redirect($response->json('data.authorization_url'));
        }

        return back()->with('error', 'Payment initialization failed');
    }

    public function handleCallback(Request $request)
    {
        $reference = $request->reference;
        
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('paystack.secret_key'),
        ])->get("https://api.paystack.co/transaction/verify/{$reference}");

        if ($response->successful() && $response->json('data.status') === 'success') {
            // Payment successful - update your database here
            return redirect()->route('payment.success');
        }

        return redirect()->route('payment.failed');
    }
}
