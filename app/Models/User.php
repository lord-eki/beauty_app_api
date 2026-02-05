<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Str;

class User extends Model
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'first_name','last_name','email', 'password','uuid','phone',
        'profile_image','user_type','is_active','last_active_at',
        'phone_verified_at'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
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
            'phone_verified_at' => 'datetime',
            'last_active_at' => 'datetime',
            'is_active' => 'boolean',
            
        ];
    }

    protected static function boot()
    {
        parent::boot();
        static::creating(function($model){
            if(empty($model->uuid))
            {
                $model->uuid =  Str::uuid();
            }
        });
    }

    public function businessProfile()
    {
        return $this->hasOne(BusinessProfile::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function customerConversations()
    {
        return $this->hasMany(ChatConversation::class,'customer_id');
    }

    public function providerConversations()
    {
        return $this->hasMany(ChatConversation::class,'provider_id');
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class,'customer_id');
    }

    public function orders()
    {
        return $this->hasMany(Order::class,'customer_id');
    }
}
