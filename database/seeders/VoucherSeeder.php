<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class VoucherSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $vouchers = [
            [
                'code' => 'DISCOUNT10',
                'name' => 'Diskon 10% Max 10k',
                'is_public' => true,
                'voucher_type' => 'discount',
                'discount_cashback_type' => 'percentage',
                'discount_cashback_value' => 10,
                'discount_cashback_max' => 10000,
                'start_date' => now(),
                'end_date' => now()->addDays(7),
            ],
            [
                'code' => 'DISCOUNT20',
                'name' => 'Diskon 20% Max 20k',
                'is_public' => true,
                'voucher_type' => 'discount',
                'discount_cashback_type' => 'percentage',
                'discount_cashback_value' => 20,
                'discount_cashback_max' => 20000,
                'start_date' => now(),
                'end_date' => now()->addDays(7),
            ],
            [
                'code' => 'DISCOUNT50',
                'name' => 'Discount 50%',
                'is_public' => true,
                'voucher_type' => 'discount',
                'discount_cashback_type' => 'percentage',
                'discount_cashback_value' => 50,
                'discount_cashback_max' => null,
                'start_date' => now(),
                'end_date' => now()->addDays(7),
            ],
            [
                'code' => 'DISCOUNT90',
                'name' => 'Discount 90%',
                'is_public' => false,
                'voucher_type' => 'discount',
                'discount_cashback_type' => 'percentage',
                'discount_cashback_value' => 90,
                'discount_cashback_max' => null,
                'start_date' => now(),
                'end_date' => now()->addDays(1),
            ],
            [
                'code' => 'DISCOUNT5000',
                'name' => 'Discount 5000',
                'is_public' => true,
                'voucher_type' => 'discount',
                'discount_cashback_type' => 'fixed',
                'discount_cashback_value' => 5000,
                'discount_cashback_max' => null,
                'start_date' => now(),
                'end_date' => now()->addDays(7),
            ],
            [
                'code' => 'DISCOUNT10000',
                'name' => 'Discount 10000',
                'is_public' => true,
                'voucher_type' => 'discount',
                'discount_cashback_type' => 'fixed',
                'discount_cashback_value' => 10000,
                'discount_cashback_max' => null,
                'start_date' => now(),
                'end_date' => now()->addDays(7),
            ],
            [
                'code' => 'CASHBACK5%',
                'name' => 'Cashback 5% Max 10k',
                'is_public' => true,
                'voucher_type' => 'cashback',
                'discount_cashback_type' => 'percentage',
                'discount_cashback_value' => 5,
                'discount_cashback_max' => 10000,
                'start_date' => now(),
                'end_date' => now()->addDays(7),
            ],
        ];

        foreach ($vouchers as $voucher) {
            if (rand(1, 0)) {
                $voucher['seller_id'] = null;
            } else {
                $voucher['seller_id'] = \App\Models\User::whereHas('products')->inRandomOrder()->first()->id;
            }

            \App\Models\Voucher::create($voucher);
        }
    }
}
