<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AsinLookup extends Model
{
    protected $fillable = ['user_id', 'asin', 'marketplace', 'was_successful', 'status_code', 'response_snapshot', 'error_code', 'expires_at'];
    protected $casts = ['was_successful' => 'boolean', 'response_snapshot' => 'array', 'expires_at' => 'datetime'];
}
