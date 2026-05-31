<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportedOperation extends Model
{
    protected $fillable = [
        'bank',
        'description',
        'amount',
        'date',
        'category'
    ];
}
