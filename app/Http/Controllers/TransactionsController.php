<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Models\Transaction;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class TransactionsController extends Controller
{
    /**
     * Display a listing of the transactions.
     */

    public function index(Request $request)
    {
        $user = Auth::user();

        $transactions = Transaction::where('user_id', $user->id)
            ->where('status', 'completed')
            ->with('order')
            ->select('id', 'type', 'amount', 'balance_before', 'balance_after', 'description', 'created_at', 'order_id')
            ->latest()
            ->orderByDesc('id')
            ->get()
            ->map(fn($t) => [
                'id' => $t->id,
                'type' => $t->type,
                'amount' => $t->amount,
                'balance_before' => $t->balance_before,
                'balance_after' => $t->balance_after,
                'description' => $t->description,
                'created_at' => $t->created_at,
                'order' => $t->order ? ['is_api_order' => $t->order->is_api_order] : null,
            ])
            ->values();

        return inertia('Dashboard/transactions', [
            'transactions' => [
                'data' => $transactions,
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => count($transactions),
                'total' => count($transactions),
            ]
        ]);
    }

}
