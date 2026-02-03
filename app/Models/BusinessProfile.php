<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessProfile extends Model
{
    /** @use HasFactory<\Database\Factories\BusinessProfileFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id','business_name','business_type','description','website','instagram','facebook','whatsapp',
        'business_hours','average_rating','total_reviews','is_verified','verification_documents'
    ];

    protected $casts = [
        'business_type' => 'enum',
        'business_hours' => 'json',
        'average_rating' => 'decimal',
        'total_reviews' => 'integer',
        'is_verified' => 'boolean',
        'verification_documents' => 'json'
    ];


    
}
