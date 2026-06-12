<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{
    public function initializePayment(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'amount' => 'required|numeric|min:1|max:10000'
        ]);

        $user = auth()->user();
        if ($request->email !== $user->email) {
            throw ValidationException::withMessages([
                'email' => 'Email must match your account email'
            ]);
        }

        $reference = 'pay_' . Str::random(16);
        
        $transaction = Transaction::create([
            'user_id' => $user->id,
            'order_id' => null,
            'amount' => $request->amount,
            'status' => 'pending',
            'type' => 'topup',
            'description' => 'Wallet top-up of GHS ' . number_format($request->amount, 2),
            'reference' => $reference,
        ]);

        $response = Http::timeout(15)
            ->retry(2, 100)
            ->withHeaders([
                'Authorization' => 'Bearer ' . config('paystack.secret_key'),
                'Content-Type' => 'application/json',
            ])->post('https://api.paystack.co/transaction/initialize', [
                'email' => $request->email,
                'amount' => $request->amount * 100,
                'callback_url' => route('payment.callback'),
                'reference' => $reference,
                'metadata' => [
                    'user_id' => $user->id,
                    'transaction_id' => $transaction->id,
                    'type' => 'wallet_topup',
                    'actual_amount' => $request->amount
                ]
            ]);

        if ($response->successful()) {
            return response()->json([
                'success' => true,
                'payment_url' => $response->json('data.authorization_url')
            ]);
        }

        $transaction->update(['status' => 'failed']);
        return response()->json(['success' => false, 'message' => 'Payment initialization failed'], 400);
    }

    public function handleCallback(Request $request)
    {
        $reference = $request->reference;
        if (!$reference) {
            abort(400, 'Missing reference');
        }

        if (!$this->verifyPaystackSignature($request)) {
            abort(401, 'Invalid signature');
        }

        $response = Http::timeout(15)
            ->withHeaders([
                'Authorization' => 'Bearer ' . config('paystack.secret_key'),
            ])->get("https://api.paystack.co/transaction/verify/{$reference}");

        if ($response->successful() && $response->json('data.status') === 'success') {
            $paymentData = $response->json('data');
            $metadata = $paymentData['metadata'] ?? [];

            DB::transaction(function () use ($metadata, $paymentData) {
                if (isset($metadata['transaction_id'])) {
                    $transaction = Transaction::lockForUpdate()->find($metadata['transaction_id']);
                    
                    if (!$transaction || $transaction->status === 'completed') {
                        return;
                    }

                    $user = User::lockForUpdate()->find($metadata['user_id']);
                    if (!$user) {
                        return;
                    }

                    $amount = $metadata['actual_amount'] ?? $transaction->amount;
                    
                    $user->increment('wallet_balance', $amount);
                    $transaction->update([
                        'status' => 'completed',
                        'reference' => $paymentData['reference']
                    ]);
                }
            });

            return redirect()->route('dashboard')->with('success', 'Payment successful!');
        }

        return redirect()->route('dashboard')->with('error', 'Payment verification failed');
    }

    private function verifyPaystackSignature(Request $request)
    {
        $signature = $request->header('x-paystack-signature');
        if (!$signature) {
            return false;
        }

        // Only verify signature if we can read the raw input
        if (!function_exists('file_get_contents')) {
            return true; // Allow if function unavailable (rare case)
        }
        
        $body = file_get_contents("php://input");
        $hash = hash('sha512', $body . config('paystack.secret_key'));
        return hash_equals($hash, $signature);
    }
}
