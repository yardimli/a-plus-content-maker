<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiGeneration extends Model
{
    protected $fillable = ['user_id', 'project_id', 'project_module_id', 'kind', 'model', 'prompt', 'response_payload', 'status', 'input_tokens', 'output_tokens', 'error_message', 'completed_at'];
    protected $casts = ['response_payload' => 'array', 'completed_at' => 'datetime'];
}
