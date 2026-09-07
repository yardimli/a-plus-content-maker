<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Asset extends Model
{
    protected $fillable = ['user_id', 'project_id', 'source', 'disk', 'path', 'original_name', 'mime_type', 'extension', 'size_bytes', 'width', 'height', 'alt_text', 'checksum', 'metadata'];
    protected $casts = ['metadata' => 'array'];
    protected $appends = ['url', 'thumbnail_url', 'template_path'];

    public function getUrlAttribute(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    public function getTemplatePathAttribute(): ?string
    {
        return $this->disk === 'public' && str_starts_with($this->path, 'template-assets/')
            ? '/storage/'.$this->path
            : null;
    }

    public function getThumbnailUrlAttribute(): string
    {
        $path = $this->metadata['thumbnail_path'] ?? null;
        return $path ? Storage::disk($this->disk)->url($path) : $this->url;
    }
}
