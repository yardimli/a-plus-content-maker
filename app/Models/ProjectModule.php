<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectModule extends Model
{
    protected $fillable = ['uuid', 'project_id', 'module_type', 'position', 'content', 'settings', 'version'];
    protected $casts = ['content' => 'array', 'settings' => 'array'];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}
