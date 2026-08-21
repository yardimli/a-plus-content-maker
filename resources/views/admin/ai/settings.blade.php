@extends('layouts.app')
@section('title', 'AI settings') @section('page-title', 'AI settings')
@section('content')
@php
    $moneyPerMillion = fn($value) => $value === null || $value === '' ? '—' : '$'.number_format((float) $value * 1000000, 2).'/1M';
    $unitPrice = fn($value, $unit) => $value === null || $value === '' ? '—' : '$'.rtrim(rtrim(number_format((float) $value, 8), '0'), '.').'/'.$unit;
    $textModels = collect($models)->where('supports_text', true);
    $imageModels = collect($models)->where('supports_image', true);
@endphp
<div class="page-lead"><div><p class="eyebrow">Administration</p><h2>Choose the models used across the studio.</h2><p>The catalog and prices are pulled from OpenRouter and cached for 15 minutes.</p></div><a href="{{ route('admin.ai.logs') }}" class="button button-secondary">View AI call logs</a></div>
@if($catalogError)<div class="flash flash-error" role="alert">{{ $catalogError }}</div>@endif
<form method="POST" action="{{ route('admin.ai.settings.update') }}" class="form-card ai-settings-form">@csrf @method('PUT')
    <div class="settings-grid">
        <label>Default text model<select name="text_model" required>
            @if($settings->text_model && !$textModels->contains('id', $settings->text_model))<option value="{{ $settings->text_model }}" selected>{{ $settings->text_model }} (current)</option>@endif
            @foreach($textModels as $model)<option value="{{ $model['id'] }}" @selected(old('text_model', $settings->text_model) === $model['id'])>{{ $model['name'] }} — {{ $moneyPerMillion(data_get($model, 'pricing.prompt')) }} in · {{ $moneyPerMillion(data_get($model, 'pricing.completion')) }} out</option>@endforeach
        </select><small>Used for module copy generation.</small></label>
        <label>Default image model<select name="image_model" required>
            @if($settings->image_model && !$imageModels->contains('id', $settings->image_model))<option value="{{ $settings->image_model }}" selected>{{ $settings->image_model }} (current)</option>@endif
            @foreach($imageModels as $model)<option value="{{ $model['id'] }}" @selected(old('image_model', $settings->image_model) === $model['id'])>{{ $model['name'] }} — {{ $unitPrice(data_get($model, 'pricing.request'), 'request') }} · {{ $moneyPerMillion(data_get($model, 'pricing.completion')) }} out</option>@endforeach
        </select><small>Used for generated creative assets.</small></label>
    </div>
    @error('text_model')<span class="field-error">{{ $message }}</span>@enderror @error('image_model')<span class="field-error">{{ $message }}</span>@enderror
    <div class="form-actions"><span class="field-hint">OpenRouter prices are estimates from the live catalog; actual cost is recorded from each response.</span><button class="button button-primary" type="submit" @disabled(!$models)>Save defaults</button></div>
</form>

<div class="section-heading compact ai-catalog-heading"><div><p class="eyebrow">Live catalog</p><h2>{{ count($models) }} available models</h2></div><input type="search" id="ai-model-filter" placeholder="Filter models…" aria-label="Filter models"></div>
<div class="table-card"><table id="ai-model-table"><thead><tr><th>Model</th><th>Output</th><th>Input</th><th>Output</th><th>Request</th><th>Image unit</th></tr></thead><tbody>
@forelse($models as $model)<tr><td><strong>{{ $model['name'] }}</strong><small>{{ $model['id'] }}</small></td><td>{{ collect([$model['supports_text'] ? 'Text' : null, $model['supports_image'] ? 'Image' : null])->filter()->join(' + ') }}</td><td>{{ $moneyPerMillion(data_get($model, 'pricing.prompt')) }}</td><td>{{ $moneyPerMillion(data_get($model, 'pricing.completion')) }}</td><td>{{ $unitPrice(data_get($model, 'pricing.request'), 'request') }}</td><td>{{ $unitPrice(data_get($model, 'pricing.image'), 'image') }}</td></tr>
@empty<tr><td colspan="6">No model catalog is available.</td></tr>@endforelse
</tbody></table></div>
@endsection
