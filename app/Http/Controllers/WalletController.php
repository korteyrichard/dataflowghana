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
                ->whereIn('type', ['topup', 'admin_credit', 'admin_debit']) // Include admin transactions
                ->select('id', 'amount', 'status', 'type', 'description', 'reference', 'created_at')
                ->latest()
                ->paginate(10),
        ]);
    }

    public function verifyPayment(Request $request)
    {
        \Log::info('verifyPayment method called', ['request_data' => $request->all(), 'user_id' => auth()->id()]);
        
        $request->validate([
            'reference' => 'required|string'
        ]);

        $reference = $request->reference;
        $userId = auth()->id();

        // Find the transaction
        \Log::info('Looking for transaction', ['reference' => $reference, 'user_id' => $userId]);
        
        $transaction = Transaction::where('reference', $reference)
            ->where('user_id', $userId)
            ->where('type', 'topup')
            ->first();

        if (!$transaction) {
            // Check if transaction exists with different criteria
            $anyTransaction = Transaction::where('reference', $reference)->first();
            $userTransactions = Transaction::where('user_id', $userId)->where('type', 'topup')->get(['id', 'reference', 'status']);
            
            \Log::info('Transaction not found', [
                'reference' => $reference,
                'user_id' => $userId,
                'any_transaction_with_reference' => $anyTransaction,
                'user_topup_transactions' => $userTransactions
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Transaction not found'
            ]);
        }

        \Log::info('Transaction found', ['transaction_id' => $transaction->id, 'status' => $transaction->status]);

        // If already completed, prevent double crediting
        if ($transaction->status === 'completed') {
            \Log::info('Transaction already completed');
            return response()->json([
                'success' => false,
                'message' => 'Transaction already verified'
            ]);
        }

        \Log::info('About to call Paystack API', ['reference' => $reference]);
        
        try {
            // Call Paystack Verify API
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . config('paystack.secret_key'),
                'Content-Type' => 'application/json',
            ])->get("https://api.paystack.co/transaction/verify/{$reference}");

            $paystackData = $response->json();
            
            \Log::info('Paystack API response', ['status_code' => $response->status(), 'response' => $paystackData]);

            if ($response->successful() && $paystackData['status'] && $paystackData['data']['status'] === 'success') {
                // Use database transaction to ensure atomicity
                DB::transaction(function () use ($transaction, $userId, $paystackData) {
                    // Update transaction status and save Paystack reference
                    $transaction->update([
                        'status' => 'completed',
                        'reference' => $paystackData['data']['reference'] ?? $transaction->reference
                    ]);
                    
                    // Update user wallet balance
                    $user = User::find($userId);
                    $user->increment('wallet_balance', $transaction->amount);
                });

                return response()->json([
                    'success' => true,
                    'message' => 'Payment verified and balance updated'
                ]);
            } else {
                // Update transaction with Paystack reference even if failed
                if (isset($paystackData['data']['reference'])) {
                    $transaction->update(['reference' => $paystackData['data']['reference']]);
                }
                
                return response()->json([
                    'success' => false,
                    'message' => 'Payment verification failed or transaction not successful',
                    'debug' => [
                        'paystack_response' => $paystackData,
                        'reference' => $reference
                    ]
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error verifying payment: ' . $e->getMessage()
            ]);
        }
    }


}

 