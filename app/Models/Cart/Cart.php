<?php

namespace App\Models\Cart;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'address_id',
        'courier',
        'courier_type',
        'courier_estimation',
        'courier_price',
        'voucher_id',
        'voucher_value',
        'voucher_cashback',
        'service_fee',
        'total',
        'pay_with_coin',
        'payment_method',
        'total_payment',
    ];

    protected $casts = [
        'courier_price' => 'float',
        'voucher_value' => 'float',
        'voucher_cashback' => 'float',
        'service_fee' => 'float',
        'total' => 'float',
        'pay_with_coin' => 'float',
        'total_payment' => 'float',
    ];

    protected $appends = ['subtotal', 'total_gross'];

    public function items()
    {
        return $this->hasMany(CartItem::class);
    }

    public function address()
    {
        return $this->belongsTo(\App\Models\Address\Address::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function voucher()
    {
        return $this->hasOne(\App\Models\Voucher::class, 'id', 'voucher_id');
    }

    public function getSubtotalAttribute()
    {
        return $this->items->sum('total');
    }

    public function getTotalGrossAttribute()
    {
        return $this->getSubtotalAttribute() + $this->courier_price + $this->service_fee;
    }

    public function getApiResponseAttribute()
    {
        return [
            'uuid' => $this->uuid,
            'address' => optional($this->address)->api_response,
            'courier' => $this->courier,
            'courier_type' => $this->courier_type,
            'courier_estimation' => $this->courier_estimation,
            'courier_price' => $this->courier_price,
            'voucher' => optional($this->voucher)->api_response,
            'subtotal' => $this->subtotal,             
            'total_gross' => $this->total_gross, 
            'voucher_value' => $this->voucher_value,
            'voucher_cashback' => $this->voucher_cashback,
            'service_fee' => $this->service_fee,
            'total' => $this->total,
            'pay_with_coin' => $this->pay_with_coin,
            'total_payment' => $this->total_payment,
        ];
    }
}
