<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Models\Order\Order;
use App\Mail\NewOrderToSeller;

class MidtransController extends Controller
{
    public function callback()
{
    \Midtrans\Config::$serverKey = config('services.midtrans.server_key');
    \Midtrans\Config::$isProduction = config('app.env') == 'production';

    try {
        $notification = new \Midtrans\Notification();
        
        $transactionStatus = $notification->transaction_status;
        $orderId = $notification->order_id;
        $fraudStatus = $notification->fraud_status;

        $order = Order::where('uuid', $orderId)->first();

        // Kalo order nggak ketemu, stop.
        if (!$order) {
            Log::warning('Midtrans Notification: Order not found.', ['order_id' => $orderId]);
            return response()->json(['message' => 'Order not found.'], 404);
        }

        // Kalo order udah lunas, jangan proses lagi buat hindarin duplikasi.
        if ($order->is_paid) {
            Log::info('Midtrans Notification: Order already processed.', ['order_id' => $orderId]);
            return response()->json(['message' => 'Notification has been processed.'], 200);
        }

        if ($transactionStatus == 'settlement') {
            // Cek status fraud. Kalo 'challenge', anggap pending. Kalo 'accept', baru lunas.
            if ($fraudStatus == 'accept') {
                // Sukses, update database
                DB::transaction(function() use ($order) {
                    $order->update([
                        'is_paid' => true,
                        'payment_expired_at' => null
                    ]);
        
                    $order->status()->create([
                        'status' => 'paid',
                        'description' => 'Pembayaran berhasil, menunggu proses pengiriman'
                    ]);
        
                    foreach ($order->items as $item) {
                        if ($item->product) {
                            $item->product->decrement('stock', $item->qty);
                        }
                    }
        
                    if ($order->seller) {
                        Mail::to($order->seller->email)->send(new NewOrderToSeller($order));
                    } else {
                        Log::warning('Seller not found for order, cannot send email.', ['order_id' => $order->uuid]);
                    }
                });

            }
        } else if ($transactionStatus == 'cancel' || $transactionStatus == 'deny' || $transactionStatus == 'expire') {
            $order->status()->create([
                'status' => 'failed',
                'description' => 'Pembayaran gagal atau kedaluwarsa.'
            ]);
        }
        
        return response()->json(['message' => 'Notification handled successfully.'], 200);

    } catch (\Exception $e) {
            Log::error('Midtrans Notification Error: ' . $e->getMessage(), [
                'order_id' => $orderId ?? 'N/A',
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return response()->json(['message' => 'Error handling notification.'], 500);
    }
}
}
