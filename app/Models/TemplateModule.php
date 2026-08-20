<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TemplateModule extends Model
{
    protected $fillable = ['template_id', 'module_type', 'position', 'content', 'settings'];
    protected $casts = ['content' => 'array', 'settings' => 'array'];

    public function template()
    {
        return $this->belongsTo(ContentTemplate::class, 'template_id');
    }
}
