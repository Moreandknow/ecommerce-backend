<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\ResponseFormatter;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index()
    {
        $query = auth()->user()->orderAsSeller()->with([
            'user',
            'address',
            'items',
            'lastStatus'
        ])->isPaid();

        if (request()->last_status) {
            $query->whereHas('lastStatus', function($subQuery){
                $subQuery->where('status', request()->last_status);
            });
        }

        if (request()->search) {
            $query->whereHas('user', function($subQuery){
                $subQuery->where('name', 'LIKE', '%' . request()->search . '%');
            })->orWhere('invoice_number', 'LIKE', '%' . request()->search . '%');

            $productIds = \App\Models\Product\Product::where('name', 'LIKE', '%' . request()->search . '%')->pluck('id');
            $query->orWhereHas('items', function($subQuery) use($productIds) {
                $subQuery->whereIn('product_id', $productIds);
            });
        }

        $orders = $query->paginate(request()->per_page ?? 10);

        return ResponseFormatter::success($orders->through(function($order){
            return $order->api_response_seller;
        }));
    }

    public function show(string $uuid)
    {
        $order = auth()->user()->orderAsSeller()->with([
            'user',
            'address',
            'items',
            'lastStatus'
        ])->isPaid()->where('uuid', $uuid)->firstOrFail();

        return ResponseFormatter::success($order->api_response_seller);
    }

    public function addStatus(string $uuid)
    {
        $order = auth()->user()->orderAsSeller()->isPaid()->where('uuid', $uuid)->firstOrFail();

        $validator = \Validator::make(request()->all(), [
            'status' => 'required|in:on_processing,on_delivery,done',
            'delivery_note' => 'nullable|max:255'
        ]);

        if ($validator->fails()) {
            return ResponseFormatter::error(400, $validator->errors());
        }

        if ($order->lastStatus->status == 'paid' && request()->status != 'on_processing') {
            return ResponseFormatter::error(400, null, [
                'Hanya bisa diubah ke on_processing ketika status sudah dibayar!'
            ]);
        }

        if ($order->lastStatus->status == 'on_processing' && request()->status != 'on_delivery') {
            return ResponseFormatter::error(400, null, [
                'Hanya bisa diubah ke on_delivery ketika status sedang diproses!'
            ]);
        }

        if ($order->lastStatus->status == 'on_delivery' && request()->status != 'on_delivery') {
            return ResponseFormatter::error(400, null, [
                'Hanya bisa menambah keterangan ketika status dalam pengiriman!'
            ]);
        }

        if ($order->lastStatus->status == 'on_delivery' && is_null(request()->delivery_note)) {
            return ResponseFormatter::error(400, null, [
                'Keterangan wajib diisi ketika status dalam pengiriman!'
            ]);
        }
        
        switch (request()->status) {
            case 'on_processing':
                $description = 'Pesanan sedang diproses oleh penjual';
                break;
            case 'on_delivery':
                $description = 'Pesanan sedang dalam pengiriman';
                break;
        }

        if (request()->status == 'on_delivery' && !is_null(request()->delivery_note)) {
            $description = request()->delivery_note;
        }

        $order->status()->create([
            'status' => request()->status,
            'description' => $description
        ]);

        return $this->show($order->uuid);
    }
}
