<?php

namespace App\Http\Controllers;

use \App\Models\Cart\Cart;
use App\ResponseFormatter;
use Illuminate\Http\Request;

class CartController extends Controller
{
    private function getOrCreateCart()
    {
        $cart = Cart::with(['items', 'address'])->where('user_id', auth()->user()->id)->first();
        if (is_null($cart)) {
            $cart = Cart::create([
                'user_id' => auth()->user()->id,
                'address_id' => optional(auth()->user()->addresses()->where('is_default', 1)->first())->id,
                'courier' => null,
                'courier_type' => null,
                'courier_estimation' => null,
                'courier_price' => 0,
                'voucher_id' => null,
                'voucher_value' => 0,
                'voucher_cashback' => 0,
                'service_fee' => 0,
                'total' => 0,
                'pay_with_coin' => 0,
                'payment_method' => null,
                'total_payment' => 0,
            ]);
        }

        // Calculate voucher
        if ($cart->voucher != null) {
            $voucher = $cart->voucher;
            if ($voucher->voucher_type == 'discount') {
                $cart->voucher_value = $voucher->discount_cashback_type == 'percentage' ? $cart->items->sum('total') * $voucher->discount_cashback_value / 100 : $voucher->discount_cashback_value;
                if (!is_null($voucher->discount_cashback_max) && $cart->voucher_value > $voucher->discount_cashback_max) {
                    $cart->voucher_value = $voucher->discount_cashback_max;
                }
            } elseif ($voucher->voucher_type == 'cashback') {
                $cart->voucher_cashback = $voucher->discount_cashback_type == 'percentage' ? $cart->items->sum('total') * $voucher->discount_cashback_value / 100 : $voucher->discount_cashback_value;
                if (!is_null($voucher->discount_cashback_max) && $cart->voucher_cashback > $voucher->discount_cashback_max) {
                    $cart->voucher_cashback = $voucher->discount_cashback_max;
                }
            }
        }

        // Recalculate total
        $cart->total = ($cart->items->sum('total')) + $cart->courier_price + $cart->service_fee - $cart->voucher_value;
        if ($cart->total < 0) {
            $cart->total = 0;
        }
        $cart->total_payment = $cart->total - $cart->pay_with_coin;
        $cart->save();

        return $cart;
    }

    public function getCart()
    {
        $cart = $this->getOrCreateCart();

        return ResponseFormatter::success([
            'cart' => $cart->api_response,
            'items' => $cart->items->pluck('api_response')
        ]);
    }

    public function addToCart()
    {
        $validator = \Validator::make(request()->all(), [
            'product_uuid' => 'required|exists:products,uuid',
            'qty' => 'required|numeric|min:1',
            'note' => 'nullable|string',
            'variations' => 'nullable|array',
            'variations.*.label' => 'required|exists:variations,name', 
            'variations.*.value' => 'required',
        ]);

        if ($validator->fails()) {
            return ResponseFormatter::error(400, $validator->errors());
        } 

        $cart = $this->getOrCreateCart();
        $product = \App\Models\Product\Product::where('uuid', request()->product_uuid)->firstOrFail();
        if ($product->stock < request()->qty) {
            return ResponseFormatter::error(400, null, [
                'Stock tidak cukup!'
            ]);
        }

        if ($cart->items->isNotEmpty() && $cart->items->first()->product->seller_id != $product->seller_id) {
            return ResponseFormatter::error(400, null, [
                'Keranjang hanya boleh diisi oleh produk dari penjual yang sama!'
            ]);
        }

        $cart->items()->create([
            'product_id' => $product->id,
            'variations' => request()->variations,
            'qty' => request()->qty,
            'note' => request()->note,
        ]);

        return $this->getCart(); 
    }

    public function removeItemFromCart(string $uuid)
    {
        $cart = $this->getOrCreateCart();
        $item = $cart->items()->where('uuid', $uuid)->firstOrFail();
        $item->delete();

        return $this->getCart();
    }

    public function updateItemFromCart(string $uuid)
    {
        $validator = \Validator::make(request()->all(), [
            'qty' => 'required|numeric|min:1',
            'note' => 'nullable|string',
            'variations' => 'nullable|array',
            'variations.*.label' => 'required|exists:variations,name', 
            'variations.*.value' => 'required',
        ]);

        if ($validator->fails()) {
            return ResponseFormatter::error(400, $validator->errors());
        } 

        $cart = $this->getOrCreateCart();
        $cartItem = $cart->items()->where('uuid', $uuid)->firstOrFail();
        $product = $cartItem->product;
        if ($product->stock < request()->qty) {
            return ResponseFormatter::error(400, null, [
                'Stock tidak cukup!'
            ]);
        }

        $cartItem->update([
            'variations' => request()->variations,
            'qty' => request()->qty,
            'note' => request()->note,
        ]);

        return $this->getCart(); 
    }

    public function getVoucher()
    {
        $vouchers = \App\Models\Voucher::public()->active()->get();

        return ResponseFormatter::success($vouchers->pluck('api_response'));
    }

    public function applyVoucher()
    {
        $validator = \Validator::make(request()->all(), [
            'voucher_code' => 'required|exists:vouchers,code',
        ]);

        if ($validator->fails()) {
            return ResponseFormatter::error(400, $validator->errors());
        }

        $voucher = \App\Models\Voucher::where('code', request()->voucher_code)->firstOrFail();
        if ($voucher->start_date > now() || $voucher->end_date < now()) {
            return ResponseFormatter::error(400, null, [
                'Voucher tidak bisa digunakan!'
            ]);
        }

        $cart = $this->getOrCreateCart();
        if (!is_null($voucher->seller_id) && $cart->items->count() > 0) {
            $sellerId = $cart->items->first()->product->seller_id;
            if ($sellerId != $voucher->seller_id) {
                return ResponseFormatter::error(400, null, [
                    'Voucher tidak bisa digunakan oleh penjual yang ada di keranjang belanja!'
                ]);
            }
        }

        $cart->voucher_id = $voucher->id;
        $cart->voucher_value = null;
        $cart->voucher_cashback = null;
        $cart->save();

        return $this->getCart();
    }

    public function removeVoucher()
    {
        $cart = $this->getOrCreateCart();
        $cart->voucher_id = null;
        $cart->voucher_value = null;
        $cart->voucher_cashback = null;

        return $this->getCart();
    }

    public function updateAddress()
    {
        $validator = \Validator::make(request()->all(), [
            'uuid' => 'required|exists:addresses,uuid',
        ]);

        if ($validator->fails()) {
            return ResponseFormatter::error(400, $validator->errors());
        }

        $cart = $this->getOrCreateCart();
        $cart->address_id = auth()->user()->addresses()->where('uuid', request()->uuid)->firstOrFail()->id;
        $cart->save();

        return $this->getCart();
    }

    public function getShipping()
    {
        $cart = $this->getOrCreateCart();

        // Validasi courier: jne|pos
        $validator = \Validator::make(request()->all(), [
            'courier' => 'required|in:jne,pos,tiki',
        ]);

        if ($validator->fails()) {
            return ResponseFormatter::error(400, $validator->errors());
        }

        // Validasi item di keranjang belanja
        if ($cart->items->count() == 0) {
            return ResponseFormatter::error(400, null, [
                'Keranjang belanja kosong!'
            ]);
        }

        // Validasi bahwa seller sudah mengisi alamat dia
        $seller = $cart->items->first()->product->seller;
        $sellerAddress = $seller->addresses()->where('is_default', true)->first();
        if (is_null($sellerAddress )) {
            return ResponseFormatter::error(400, null, [
                'Alamat seller belum diisi!'
            ]);
        }

        // Validasi address di cart 
        if (is_null($cart->address)) {
            return ResponseFormatter::error(400, null, [
                'Alamat tujuan belum diisi!'
            ]);
        }

        $weight = $cart->items->sum(function($item){
            return $item->qty * $item->product->weight; 
        });
        
        $result = $this->getShippingOptions(
            $sellerAddress->city->external_id, 
            $cart->address->city->external_id, 
            $weight, 
            request()->courier
        ); 

        return ResponseFormatter::success($result); 
    }

    public function updateShippingFee()
    {
        $validator = \Validator::make(request()->all(), [
            'courier' => 'required|in:jne,pos,tiki',
            'service' => 'required'
        ]);

        if ($validator->fails()) {
            return ResponseFormatter::error(400, $validator->errors());
        }

        $cart = $this->getOrCreateCart();

        // Validasi item di keranjang belanja
        if ($cart->items->count() == 0) {
            return ResponseFormatter::error(400, null, [
                'Keranjang belanja kosong!'
            ]);
        }

        // Validasi bahwa seller sudah mengisi alamat dia
        $seller = $cart->items->first()->product->seller;
        $sellerAddress = $seller->addresses()->where('is_default', true)->first();
        if (is_null($sellerAddress )) {
            return ResponseFormatter::error(400, null, [
                'Alamat seller belum diisi!'
            ]);
        }

        // Validasi address di cart 
        if (is_null($cart->address)) {
            return ResponseFormatter::error(400, null, [
                'Alamat tujuan belum diisi!'
            ]);
        }

        $weight = $cart->items->sum(function($item){
            return $item->qty * $item->product->weight; 
        });
        
        $result = $this->getShippingOptions(
            $sellerAddress->city->external_id, 
            $cart->address->city->external_id, 
            $weight, 
            request()->courier
        ); 

        $service = collect($result['cost'])->where('service', request()->service)->first();
        if (is_null($service)) {
            return ResponseFormatter::error(400, null, [
                'Service tidak ditemukan!'
            ]);
        }

        $cart->courier = request()->courier;
        $cart->courier_type = request()->service;
        $cart->courier_estimation = $service['etd'];
        $cart->courier_price = $service['value']; 
        $cart->save();

        return $this->getCart();
    }

    private function getShippingOptions(int $origin, int $destination, float $weight, string $courier)
    {
        $response = \Http::withHeaders([
            'key' => config('services.rajaongkir.key')
        ])->post(config('services.rajaongkir.base_url') . '/cost', [
            'origin' => $origin,
            'destination' => $destination,
            'weight' => $weight,
            'courier' => $courier, 
        ]); 
    
        $result = collect($response->object()->rajaongkir->results)->map(function($item){
            return [
                'service' => $item->name,
                'cost' => collect($item->costs)->map(function($cost){
                    return [
                        'service' => $cost->service,
                        'description' => $cost->description,
                        'etd' => $cost->cost[0]->etd,
                        'value' => $cost->cost[0]->value,
                    ];
                }),
            ];
        })[0];

        return $result;
    }

    public function checkout()
    {
        $validator = \Validator::make(request()->all(), [
            'payment_method' => 'required|in:qris,bni_va,bca_va,gopay',
        ]);

        if ($validator->fails()) {
            return ResponseFormatter::error(400, $validator->errors());
        }

        $cart = $this->getOrCreateCart();
        if ($cart->items->count() == 0) {
            return ResponseFormatter::error(400, null, [
                'Keranjang belanja anda kosong!'
            ]);
        }

        if (is_null($cart->courier)) {
            return ResponseFormatter::error(400, null, [
                'Anda belum memilih kurir!'
            ]);
        }

        $order = \DB::transaction(function() use($cart) {
            // Create Order
            $order = auth()->user()->orders()->create([
                'seller_id' => $cart->items->first()->product->seller_id,
                'address_id' => $cart->address_id,
                'courier' => $cart->courier,
                'courier_type' => $cart->courier_type,
                'courier_estimation' => $cart->courier_estimation,
                'courier_price' => $cart->courier_price,
                'voucher_id' => $cart->voucher_id,
                'voucher_value' => $cart->voucher_value,
                'voucher_cashback' => $cart->voucher_cashback,
                'service_fee' => $cart->service_fee,
                'total' => $cart->total,
                'pay_with_coin' => $cart->pay_with_coin,
                'payment_method' => request()->payment_method,
                'total_payment' => $cart->total_payment,
                'is_paid' => false,
            ]);

            // Create Order item
            foreach ($cart->items as $item) {
                $order->items()->create([
                    'product_id' => $item->product_id,
                    'variations' => $item->variations,
                    'qty' => $item->qty,
                    'note' => $item->note,
                ]);
            }

            // Create order status
            $order->status()->create([
                'status' => 'pending_payment',
                'description' => 'Silahkan selesaikan pembayaran Anda'
            ]);

            // Generate payment ke midtrans
            $order->refresh();
            $order->generatePayment();

            // Bersihkan cart & cart items
            $cart->items()->delete();
            $cart->delete();

            return $order;
        });

        return ResponseFormatter::success($order->api_response_detail);
    }
}
