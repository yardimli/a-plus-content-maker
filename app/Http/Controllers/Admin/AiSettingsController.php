<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiSetting;
use App\Services\OpenRouterService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AiSettingsController extends Controller
{
    public function edit(OpenRouterService $service)
    {
        $settings = AiSetting::current();
        $models = [];
        $catalogError = null;

        try {
            $models = $service->models();
        } catch (\Throwable $exception) {
            report($exception);
            $catalogError = 'The OpenRouter model catalog is temporarily unavailable. Existing defaults are shown below.';
        }

        return view('admin.ai.settings', compact('settings', 'models', 'catalogError'));
    }

    public function update(Request $request, OpenRouterService $service)
    {
        $models = $service->models(true);
        $textModels = collect($models)->where('supports_text', true)->pluck('id')->all();
        $imageModels = collect($models)->where('supports_image', true)->pluck('id')->all();
        $data = $request->validate([
            'text_model' => ['required', 'string', Rule::in($textModels)],
            'image_model' => ['required', 'string', Rule::in($imageModels)],
        ]);

        AiSetting::query()->updateOrCreate(['id' => 1], [...$data, 'updated_by' => $request->user()->id]);

        return back()->with('success', 'Default AI models updated.');
    }
}
