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
            ->paginate(15);

        $allTimeSales = Transaction::where('user_id', $user->id)
            ->where('type', 'order')
            ->where('status', 'completed')
            ->sum('amount');

        $dailySales = Transaction::where('user_id', $user->id)
            ->where('type', 'order')
            ->where('status', 'completed')
            ->whereDate('created_at', Carbon::today())
            ->sum('amount');

        return inertia('Dashboard/transactions', [
            'transactions' => $transactions,
            'allTimeSales' => $allTimeSales,
            'dailySales' => $dailySales
        ]);
    }

}
