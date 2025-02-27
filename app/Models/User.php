<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'username',
        'name',
        'email',
        'phone',
        'store_name',
        'gender',
        'birth_date',
        'photo',
        'otp_register',
        'email_verified_at',
        'password',
        'social_media_provider',
        'social_media_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function getApiResponseAttribute()
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'photo_url' => $this->photo_url,
            'username' => $this->username,
            'phone' => $this->phone,
            'store_name' => $this->store_name,
            'gender' => $this->gender,
            'birth_date' => $this->birth_date,
        ];
    }

    public function getApiResponseAsSellerAttribute()
    {
        $productIds = $this->products()->pluck('id');
        
        return [
            'username' => $this->username,
            'photo_url' => $this->photo_url,
            'store_name' => $this->store_name,
            'product_count' => $this->products()->count(),
            'rating_count' => \App\Models\Product\Review::whereIn('product_id', $productIds)->count(),
            'join_date' => $this->created_at->diffForHumans(),
            'send_from' => optional($this->address()->where('is_default', true)->first())->getApiResponseAttribute()
        ];
    }

    public function getPhotoUrlAttribute()
    {
        if (is_null($this->photo)) {
            return null;
        }

        return asset('storage/' . $this->photo);
    }

    public function addresses()
    {
        return $this->hasMany(\App\Models\Address\Address::class);
    }
}
