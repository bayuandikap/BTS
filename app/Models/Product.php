<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'price',
        'description',
        'category',
        'images',
        'created_by',
        'created_by_id',
        'updated_by',
        'updated_by_id',
    ];

    protected function casts(): array
    {
        return [
            'images' => 'array',
            'price'  => 'float',
        ];
    }
}
