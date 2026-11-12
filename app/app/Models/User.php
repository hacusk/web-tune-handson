<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Model
{
    protected $table = 'users';

    public $timestamps = false;

    protected $fillable = ['name'];

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }
}
