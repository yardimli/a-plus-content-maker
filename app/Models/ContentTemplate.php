<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContentTemplate extends Model
{
    use HasFactory;

    protected $table = 'templates';

    protected $fillable = ['image_filters', 'created_by', 'name', 'slug', 'summary', 'description', 'category', 'tags', 'preview_image', 'status', 'is_featured', 'published_at'];

    protected $casts = ['image_filters' => 'array', 'tags' => 'array', 'is_featured' => 'boolean', 'published_at' => 'datetime'];

    public function modules()
    {
        return $this->hasMany(TemplateModule::class, 'template_id')->orderBy('position');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
