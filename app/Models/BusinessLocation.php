<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BusinessLocation extends Model
{
    /** @use HasFactory<\Database\Factories\BusinessLocationFactory> */
    use HasFactory;


    protected $fillable = [
        'business_profile_id','name','address','city','county','postal_code','latitude','longitude',
        'is_primary','is_active',
    ];

    protected $casts = [
        'latitude' => 'decimal',
        'longitude' => 'decimal',
        'is_primary' => 'boolean',
        'is_active' => 'boolean'
    ];


}
