<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiSetting extends Model
{
    protected $fillable = ['text_model', 'image_model', 'updated_by'];

    public static function current(): self
    {
        return static::query()->firstOrNew(['id' => 1], [
            'text_model' => config('services.openrouter.text_model'),
            'image_model' => config('services.openrouter.image_model'),
        ]);
    }

    public static function textModel(): ?string
    {
        return static::query()->value('text_model') ?: config('services.openrouter.text_model');
    }

    public static function imageModel(): ?string
    {
        return static::query()->value('image_model') ?: config('services.openrouter.image_model');
    }
}
