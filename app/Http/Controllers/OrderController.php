<?php

namespace App\Http\Controllers;

use App\ResponseFormatter;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index()
    {
        $query = auth()->user()->orders()->with([
            'seller',
            'address',
            'items',
            'lastStatus'
        ]);

        if (request()->last_status) {
            $query->whereHas('lastStatus', function($subQuery){
                $subQuery->where('status', request()->last_status);
            });
        }

        if (request()->search) {
            $query->whereHas('seller', function($subQuery){
                $subQuery->where('store_name', 'LIKE', '%' . request()->search . '%');
            })->orWhere('invoice_number', 'LIKE', '%' . request()->search . '%');

            $productIds = \App\Models\Product\Product::where('name', 'LIKE', '%' . request()->search . '%')->pluck('id');
            $query->orWhereHas('items', function($subQuery) use($productIds) {
                $subQuery->whereIn('product_id', $productIds);
            });
        }

        $orders = $query->paginate(request()->per_page ?? 10);

        return ResponseFormatter::success($orders->through(function($order){
            return $order->api_response;
        }));
    }

    public function show(string $uuid)
    {
        $order = auth()->user()->orders()->with([
            'seller',
            'address',
            'items',
            'lastStatus'
        ])->where('uuid', $uuid)->firstOrFail();

        return ResponseFormatter::success($order->api_response_detail);
    }

    public function markAsDone(string $uuid)
    {
        $order = auth()->user()->orders()->with([
            'seller',
            'address',
            'items',
            'lastStatus'
        ])->where('uuid', $uuid)->firstOrFail();

        if ($order->lastStatus->status != 'on_delivery') {
            return ResponseFormatter::error(400, null, [
                'Status order belum dikirim!'
            ]);
        }

        $order->markAsDone();
        $order->refresh();

        return ResponseFormatter::success($order->api_response_detail);
    }

    public function addReview()
    {
        $validator = \Validator::make(request()->all(), [
            'order_item_uuid' => 'required|exists:order_items,uuid',
            'star_seller' => 'required|numeric|min:1|max:5',
            'star_courier' => 'required|numeric|min:1|max:5',
            'desccription' => 'nullable|max:255',
            'attachment' => 'array',
            'attachment.*' => 'file|mimes:jpg,jpeg,png,mp4,mov,ogg,mkv|max:10000',
            'show_username' => 'required|in:1,0',
        ]);

        if ($validator->fails()) {
            return ResponseFormatter::error(400, $validator->errors());
        }

        $orderItem = \App\Models\Order\OrderItem::where('uuid', request()->order_item_uuid)->firstOrFail();
        $order = $orderItem->order;
        if ($order->user_id != auth()->user()->id) {
            return ResponseFormatter::error(403, null, [
                'Bukan milik anda!'
            ]);
        }

        if ($order->lastStatus->status != 'done') {
            return ResponseFormatter::error(400, null, [
                'Status order belum selesai!'
            ]);
        }

        if (!is_null($orderItem->review)) {
            return ResponseFormatter::error(400, null, [
                'Anda sudah review produk ini!'
            ]);
        }

        $attachments = [];
        if (is_array(request()->attachments) && count(request()->attachments) > 0) {
            foreach (request()->attachments as $attachment) {
                $attachments[] = $attachment->store('attachments', 'public');
            }
        }

        $review = \DB::transaction(function() use($order, $orderItem, $attachments) {
            $review = $orderItem->review()->create([
                'product_id' => $orderItem->product_id,
                'user_id' => $order->user_id,
                'star_seller' => request()->star_seller,
                'star_courier' => request()->star_courier,
                'variations' => collect($orderItem->variations)->map(function($variation){
                    return $variation['label'] . ': ' . $variation['value'];
                })->implode(', '),
                'description' => request()->description,
                'attachments' => $attachments,
                'show_username' => request()->show_username,
            ]);

            // Add coin to buyer
            $coin = 25000;
            auth()->user()->deposit($coin, [
                'description' => 'Review produk ' . $orderItem->product->name
            ]);

            return $review;
        });

        return ResponseFormatter::success($review->api_response);
    }
}
