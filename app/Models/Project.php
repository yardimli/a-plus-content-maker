<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    use HasFactory;

    public const MAX_MODULES = 5;

    protected $fillable = ['image_filters', 'uuid', 'user_id', 'source_template_id', 'name', 'status', 'marketplace', 'asin', 'product_snapshot', 'author_name', 'genre', 'audience', 'tone', 'brand_notes', 'last_saved_at'];
    protected $casts = ['image_filters' => 'array', 'product_snapshot' => 'array', 'last_saved_at' => 'datetime'];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function sourceTemplate()
    {
        return $this->belongsTo(ContentTemplate::class, 'source_template_id');
    }

    public function modules()
    {
        return $this->hasMany(ProjectModule::class)->orderBy('position');
    }

    public function assets()
    {
        return $this->hasMany(Asset::class);
    }
}
