<?php

namespace App\Services;

use App\Events\OrderMatched;
use App\Models\Asset;
use App\Models\Order;
use App\Models\Trade;
use App\Models\User;
use App\Traits\Decimals;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    use Decimals;

    private const COMMISSION_RATE = '0.015';

    public function place(User $user, array $payload): array
    {
        return DB::transaction(function () use ($user, $payload) {

            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();

            $symbol = $payload['symbol'];
            $side   = $payload['side'];
            $price  = $this->decimal($payload['price']);
            $amount = $this->decimal($payload['amount']);

            /**
             * BUY ORDER
             */
            if ($side === 'buy') {
                $requiredUsd = $this->multiplyDecimal($price, $amount);

                if ($this->isLessThan($user->balance, $requiredUsd)) {
                    throw ValidationException::withMessages([
                        'balance' => 'Insufficient USD balance.',
                    ]);
                }

                // Lock USD by deducting
                $user->balance = $this->subtractDecimal($user->balance, $requiredUsd);
                $user->save();
            }

            /**
             * SELL ORDER
             */
            else {
                $asset = Asset::where('user_id', $user->id)
                    ->where('symbol', $symbol)
                    ->lockForUpdate()
                    ->first();

                if (!$asset || $this->isLessThan($asset->amount, $amount)) {
                    throw ValidationException::withMessages([
                        'assets' => "Insufficient {$symbol} balance.",
                    ]);
                }

                // Lock asset
                $asset->amount        = $this->subtractDecimal($asset->amount, $amount);
                $asset->locked_amount = $this->addDecimal($asset->locked_amount, $amount);
                $asset->save();
            }

            $order = Order::create([
                'user_id'          => $user->id,
                'symbol'           => $symbol,
                'side'             => $side,
                'price'            => $price,
                'amount'           => $amount,
                'remaining_amount' => $amount,
                'status'           => 'open',
            ]);

            dispatch(new \App\Jobs\MatchOrderJob($order->id));

            return [
                'order' => $order->fresh(),
                'match' => null,
            ];
        });
    }

    public function cancel(User $user, Order $order): array
    {
        return DB::transaction(function () use ($user, $order) {

            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($order->status !== 'open') {
                return ['order' => $order];
            }

            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();

            $amount = $this->decimal($order->remaining_amount);
            $price  = $this->decimal($order->price);

            if ($order->side === 'buy') {
                // Refund remaining USD
                $refund = $this->multiplyDecimal($price, $amount);
                $user->balance = $this->addDecimal($user->balance, $refund);
                $user->save();
            } else {
                // Unlock asset
                $asset = Asset::where('user_id', $user->id)
                    ->where('symbol', $order->symbol)
                    ->lockForUpdate()
                    ->firstOrFail();

                $asset->locked_amount = $this->subtractDecimal($asset->locked_amount, $amount);
                $asset->amount        = $this->addDecimal($asset->amount, $amount);
                $asset->save();
            }

            $order->update([
                'status'        => 'cancelled',
                'cancelled_at'  => now(),
            ]);

            return ['order' => $order->fresh()];
        });
    }

    public function matchOpenOrder(int $orderId): void
    {
        DB::transaction(function () use ($orderId) {

            $order = Order::whereKey($orderId)
                ->lockForUpdate()
                ->first();

            if (!$order || $order->status !== 'open') {
                return;
            }

            $this->executeMatching($order);
        });
    }



    private function executeMatching(Order $newOrder): ?array
    {
        $newOrder = Order::whereKey($newOrder->id)->lockForUpdate()->firstOrFail();
        if ($newOrder->status !== 'open') {
            return null;
        }

        $counterOrder = $this->findCounterOrder($newOrder);
        if (!$counterOrder) {
            return null;
        }

        $buyOrder  = $newOrder->side === 'buy'  ? $newOrder : $counterOrder;
        $sellOrder = $newOrder->side === 'sell' ? $newOrder : $counterOrder;

        $amount = $this->decimal($buyOrder->remaining_amount);

        $tradePrice = $newOrder->side === 'buy'
            ? $this->decimal($sellOrder->price)
            : $this->decimal($buyOrder->price);

        $usdValue   = $this->multiplyDecimal($tradePrice, $amount);
        $commission = $this->multiplyDecimal($usdValue, self::COMMISSION_RATE);

        $buyer  = User::whereKey($buyOrder->user_id)->lockForUpdate()->firstOrFail();
        $seller = User::whereKey($sellOrder->user_id)->lockForUpdate()->firstOrFail();

        // Release seller locked asset
        $sellerAsset = Asset::where('user_id', $seller->id)
            ->where('symbol', $sellOrder->symbol)
            ->lockForUpdate()
            ->firstOrFail();

        $sellerAsset->locked_amount = $this->subtractDecimal($sellerAsset->locked_amount, $amount);
        $sellerAsset->save();

        // Credit seller USD minus commission
        $seller->balance = $this->addDecimal(
            $seller->balance,
            $this->subtractDecimal($usdValue, $commission)
        );
        $seller->save();

        // Credit buyer asset
        $buyerAsset = Asset::firstOrCreate(
            ['user_id' => $buyer->id, 'symbol' => $buyOrder->symbol],
            ['amount' => '0', 'locked_amount' => '0']
        );

        $buyerAsset->amount = $this->addDecimal($buyerAsset->amount, $amount);
        $buyerAsset->save();

        // Refund buyer price improvement
        $reserved = $this->multiplyDecimal($buyOrder->price, $amount);
        $refund   = $this->subtractDecimal($reserved, $usdValue);

        if ($this->isGreaterThan($refund, '0')) {
            $buyer->balance = $this->addDecimal($buyer->balance, $refund);
            $buyer->save();
        }

        $this->markOrderFilled($buyOrder);
        $this->markOrderFilled($sellOrder);

        $trade = $this->recordTrade($buyOrder, $sellOrder, $tradePrice, $amount, $usdValue, $commission);

        event(new OrderMatched(
            symbol: $buyOrder->symbol,
            buyOrderId: $buyOrder->id,
            sellOrderId: $sellOrder->id,
            buyerId: $buyer->id,
            sellerId: $seller->id,
            price: $tradePrice,
            amount: $amount,
            usdValue: $usdValue,
            commission: $commission,
            tradeId: $trade?->id
        ));

        return ['trade' => $trade?->toArray()];
    }


    private function findCounterOrder(Order $order): ?Order
    {
        $query = Order::where('symbol', $order->symbol)
            ->where('status', 'open')
            ->where('remaining_amount', $order->remaining_amount)
            ->lockForUpdate();

        return $order->side === 'buy'
            ? $query->where('side', 'sell')->where('price', '<=', $order->price)->orderBy('price')->orderBy('created_at')->first()
            : $query->where('side', 'buy')->where('price', '>=', $order->price)->orderByDesc('price')->orderBy('created_at')->first();
    }

    private function markOrderFilled(Order $order): void
    {
        $order->update([
            'remaining_amount' => '0',
            'status' => 'filled',
            'filled_at' => now(),
        ]);
    }

    private function recordTrade(
        Order $buy,
        Order $sell,
        string $price,
        string $amount,
        string $usdValue,
        string $commission
    ): ?Trade {
        if (!class_exists(Trade::class) || !\Schema::hasTable('trades')) {
            return null;
        }

        return Trade::create([
            'symbol' => $buy->symbol,
            'buy_order_id' => $buy->id,
            'sell_order_id' => $sell->id,
            'buyer_id' => $buy->user_id,
            'seller_id' => $sell->user_id,
            'price' => $price,
            'amount' => $amount,
            'usd_value' => $usdValue,
            'commission' => $commission,
        ]);
    }
}
