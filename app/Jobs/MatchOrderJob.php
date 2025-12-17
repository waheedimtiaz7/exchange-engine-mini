<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class MatchOrderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 10;

    public function __construct(public int $orderId) {}

    public function handle(OrderService $service): void
    {
        $order = Order::find($this->orderId);
        if (!$order || $order->status !== 'open') {
            return;
        }

        $lock = Cache::lock("orderbook:{$order->symbol}", 5);

        if (!$lock->get()) {
            return;
        }

        try {
            $service->matchOpenOrder($order->id);
        } finally {
            optional($lock)->release();
        }
    }
}
