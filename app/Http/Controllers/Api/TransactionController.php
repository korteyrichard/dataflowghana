<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    /**
     * Get all orders for authenticated user
     */
    public function index(Request $request)
    {
        $orders = Order::with(['transactions'])
            ->where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $orders
        ]);
    }

    /**
     * Get single order by ID
     */
    public function show(Request $request, $id)
    {
        $order = Order::with(['transactions'])
            ->where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $order
        ]);
    }
}
