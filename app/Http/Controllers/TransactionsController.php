<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
    use App\Models\Transaction;
use Illuminate\Support\Facades\Auth;

class TransactionsController extends Controller
{
    /**
     * Display a listing of the transactions.
     */

    public function index(Request $request)
    {
        $user = Auth::user();

        $transactions = Transaction::where('user_id', $user->id)
            ->latest()
            ->paginate(15);

        return inertia('Dashboard/transactions', [
            'transactions' => $transactions
        ]);
    }

}
