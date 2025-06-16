<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

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

        $order = \App\Models\Order\Order::where('uuid', $orderId)->first();

        // Kalo order nggak ketemu, stop.
        if (!$order) {
            \Log::warning('Midtrans Notification: Order not found.', ['order_id' => $orderId]);
            return response('Order not found.', 404);
        }

        // Kalo order udah lunas, jangan proses lagi buat hindarin duplikasi.
        if ($order->is_paid) {
            return response('Notification has been processed.', 200);
        }

        if ($transactionStatus == 'settlement') {
            // Cek status fraud. Kalo 'challenge', anggap pending. Kalo 'accept', baru lunas.
            if ($fraudStatus == 'accept') {
                // Sukses, update database
                \DB::transaction(function() use($order) {
                    $order->status()->create([
                        'status' => 'paid',
                        'description' => 'Pembayaran berhasil, menunggu proses pengiriman'
                    ]);
        
                    $order->update([
                        'is_paid' => true,
                        'payment_expired_at' => null
                    ]);
        
                    foreach ($order->items as $item) {
                        $item->product->decrement('stock', $item->qty); 
                    }
        
                    \Mail::to($order->seller->email)->send(new \App\Mail\NewOrderToSeller($order));
                });
            }
        } elseif ($transactionStatus == 'cancel' || $transactionStatus == 'deny' || $transactionStatus == 'expire') {
            // Pembayaran gagal
            $order->status()->create([
                'status' => 'failed',
                'description' => 'Pembayaran gagal atau kedaluwarsa.'
            ]);
        }
        
        return response('Notification handled successfully', 200);

    } catch (\Exception $e) {
        // Catat error ke log Laravel
        \Log::error('Midtrans Notification Error: ' . $e->getMessage(), ['order_id' => $orderId ?? 'N/A']);
        // Kasih respons 500 biar Midtrans tau ada masalah dan akan coba kirim ulang
        return response('Error handling notification.', 500);
    }
}
}
