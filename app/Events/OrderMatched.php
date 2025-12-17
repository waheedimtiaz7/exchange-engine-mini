<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderMatched implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $symbol,
        public int $buyOrderId,
        public int $sellOrderId,
        public int $buyerId,
        public int $sellerId,
        public string $price,
        public string $amount,
        public string $usdValue,
        public string $commission,
        public ?int $tradeId = null,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("user.{$this->buyerId}"),
            new PrivateChannel("user.{$this->sellerId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'OrderMatched';
    }

    public function broadcastWith(): array
    {
        return [
            'symbol' => $this->symbol,
            'buy_order_id' => $this->buyOrderId,
            'sell_order_id' => $this->sellOrderId,
            'buyer_id' => $this->buyerId,
            'seller_id' => $this->sellerId,
            'price' => $this->price,
            'amount' => $this->amount,
            'usd_value' => $this->usdValue,
            'commission' => $this->commission,
            'trade_id' => $this->tradeId,
        ];
    }
}
