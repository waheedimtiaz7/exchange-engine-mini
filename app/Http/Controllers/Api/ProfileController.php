<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user()->load('assets');

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'balance' => (string)$user->balance,
            ],
            'assets' => $user->assets->map(fn($a) => [
                'symbol' => $a->symbol,
                'amount' => (string)$a->amount,
                'locked_amount' => (string)$a->locked_amount,
            ])->values(),
        ]);
    }
}
