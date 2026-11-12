<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shop extends Model
{
    protected $table = 'shops';

    public $timestamps = false;

    protected $fillable = ['name', 'description'];

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }
}
