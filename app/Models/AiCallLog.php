<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiCallLog extends Model
{
    protected $fillable = [
        'user_id', 'project_id', 'ai_generation_id', 'kind', 'model', 'location', 'status',
        'input_tokens', 'output_tokens', 'total_tokens', 'cost_usd', 'provider_request_id',
        'duration_ms', 'error_message', 'metadata',
    ];

    protected $casts = ['cost_usd' => 'decimal:8', 'metadata' => 'array'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function generation()
    {
        return $this->belongsTo(AiGeneration::class, 'ai_generation_id');
    }
}
