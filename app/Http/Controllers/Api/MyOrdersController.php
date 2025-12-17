<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;

class MyOrdersController extends Controller
{
    public function index(Request $request)
    {
        $symbol = strtoupper($request->query('symbol'));

        $orders = Order::query()
            ->where('user_id', $request->user()->id)
            ->where('symbol', $symbol)
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'orders' => $orders,
        ]);
    }
}
