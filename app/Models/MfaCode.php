<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MfaCode extends Model
{
    protected $fillable = ['user_id', 'type', 'code', 'expires_at', 'used_at'];
    protected $casts    = ['expires_at' => 'datetime', 'used_at' => 'datetime'];
}
