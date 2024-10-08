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

    public function getPhotoUrlAttribute()
    {
        if (is_null($this->photo)) {
            return null;
        }

        return asset('storage/' . $this->photo);
    }
}
