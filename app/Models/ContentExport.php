<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContentExport extends Model
{
    protected $table = 'exports';
    protected $fillable = ['user_id', 'project_id', 'status', 'format', 'disk', 'path', 'size_bytes', 'manifest', 'expires_at'];
    protected $casts = ['manifest' => 'array', 'expires_at' => 'datetime'];
}
