<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class WalletController extends Controller
{
    public function index()
    {
        return Inertia::render('Dashboard/Wallet', [
            'transactions' => Transaction::where('user_id', auth()->id())
                ->whereIn('type', ['topup', 'admin_credit', 'admin_debit'])
                ->whereIn('status', ['completed', 'pending'])
                ->select('id', 'amount', 'status', 'type', 'description', 'reference', 'balance_before', 'balance_after', 'created_at')
                ->latest()
                ->paginate(10),
        ]);
    }

    public function verifyPayment(Request $request)
    {
        \Log::info('Payment verification initiated', ['user_id' => auth()->id()]);
        
        $request->validate([
            'reference' => 'required|string|max:100'
        ]);

        $reference = $request->reference;
        $userId = auth()->id();

        try {
            $response = Http::timeout(15)
                ->retry(2, 100)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . config('paystack.secret_key'),
                    'Content-Type' => 'application/json',
                ])->get("https://api.paystack.co/transaction/verify/{$reference}");

            $paystackData = $response->json();
            
            if (!($response->successful() && $paystackData['status'] && $paystackData['data']['status'] === 'success')) {
                \Log::warning('Payment verification failed on Paystack', [
                    'user_id' => $userId,
                    'paystack_status' => $paystackData['status'] ?? 'unknown'
                ]);
                return response()->json(['success' => false, 'message' => 'Payment verification failed']);
            }

            // Only acquire lock AFTER Paystack verification succeeds
            DB::transaction(function () use ($reference, $userId, $paystackData) {
                $transaction = Transaction::where('reference', $reference)
                    ->where('user_id', $userId)
                    ->where('type', 'topup')
                    ->lockForUpdate()
                    ->first();

                if (!$transaction) {
                    \Log::warning('Transaction not found during verification', ['reference' => $reference, 'user_id' => $userId]);
                    throw new \Exception('Transaction not found');
                }

                if ($transaction->status === 'completed') {
                    \Log::info('Transaction already completed', ['transaction_id' => $transaction->id]);
                    throw new \Exception('Transaction already verified');
                }
                
                $user = User::lockForUpdate()->find($userId);
                if (!$user) {
                    throw new \Exception('User not found');
                }

                $transaction->update([
                    'status' => 'completed',
                    'reference' => $paystackData['data']['reference'] ?? $transaction->reference
                ]);
                
                $user->increment('wallet_balance', $transaction->amount);
                
                \Log::info('Wallet balance updated', [
                    'user_id' => $userId,
                    'amount' => $transaction->amount,
                    'transaction_id' => $transaction->id
                ]);
            });

            return response()->json(['success' => true, 'message' => 'Payment verified and balance updated']);
        } catch (\Exception $e) {
            \Log::error('Payment verification error', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'reference' => $reference
            ]);
            return response()->json(['success' => false, 'message' => 'Error verifying payment'], 500);
        }
    }


}

 