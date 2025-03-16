<?php

namespace App\Http\Controllers\Seller;

use App\ResponseFormatter;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class WalletController extends Controller
{
    public function index()
    {
        $query = auth()->user()->transactions()->orderBy('id', 'desc');
        if (!is_null(request()->search)) {
            $query->where('meta', 'LIKE', '%' . request()->search . '%');
        }

        $transactions = $query->paginate(request()->per_page ?? 10);

        return ResponseFormatter::success($transactions->through(function($transaction){
            return [
                'uuid' => $transaction->uuid,
                'type' => $transaction->type,
                'amount' => (float) $transaction->amount,
                'description' => isset($transaction->meta['description']) ? $transaction->meta['description'] : null,
                'meta' => $transaction->meta,
                'time' => $transaction->created_at->format('Y-m-d H:i:s')
            ];
        }));
    }
}
