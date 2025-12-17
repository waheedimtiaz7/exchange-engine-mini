<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $symbol  = strtoupper($request->query('symbol', 'BTC'));
        $perPage = (int) $request->query('per_page', 10);

        $buy = Order::query()
            ->where('symbol', $symbol)
            ->where('status', 'open')
            ->where('side', 'buy')
            ->orderByDesc('price')
            ->orderBy('created_at')
            ->paginate($perPage, ['*'], 'buy_page', (int) $request->query('buy_page', 1));

        $sell = Order::query()
            ->where('symbol', $symbol)
            ->where('status', 'open')
            ->where('side', 'sell')
            ->orderBy('price')
            ->orderBy('created_at')
            ->paginate($perPage, ['*'], 'sell_page', (int) $request->query('sell_page', 1));

        return response()->json([
            'symbol' => $symbol,
            'buy'  => $buy,   // paginator JSON (data, links, meta)
            'sell' => $sell,
        ]);
    }

    public function store(Request $request, OrderService $service)
    {
        $data = $request->validate([
            'symbol' => ['required','string','max:10'],
            'side'   => ['required', Rule::in(['buy','sell'])],
            'price'  => ['required','numeric','gt:0'],
            'amount' => ['required','numeric','gt:0'],
        ]);

        $result = $service->place($request->user(), [
            'symbol' => strtoupper($data['symbol']),
            'side'   => $data['side'],
            'price'  => (string)$data['price'],
            'amount' => (string)$data['amount'],
        ]);

        return response()->json($result, 201);
    }

    public function cancel(Request $request, $orderId, OrderService $service)
    {
        $order = Order::whereKey($orderId)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        return response()->json($service->cancel($request->user(), $order));
    }
}
