<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Apikey extends Model
{
    use HasFactory;

    /**
     * @param $key
     * @return mixed
     */
    public static function fromApiKey($key)
    {
        return static::where('apikey', $key)->first();
    }
}
