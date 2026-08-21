@extends('layouts.public')

@section('title', $template->name.' · Templates')

@section('content')
<section class="template-detail">
    <a href="{{ route('templates.index') }}" class="auth-back template-detail-back">← Back to gallery</a>
    <div class="template-detail-copy">
        <span class="pill">{{ $template->category }}</span>
        <h1>{{ $template->name }}</h1>
        <p>{{ $template->description ?: $template->summary }}</p>
        <div class="template-facts">
            <span><strong>{{ $template->modules->count() }}</strong> modules</span>
            <span><strong>{{ in_array('Series', $template->tags ?? []) ? '3+' : '1' }}</strong> book layout</span>
        </div>
        @auth
            <a href="{{ route('projects.create', ['template' => $template->slug]) }}" class="button button-primary button-large">Clone this template</a>
        @else
            <a href="{{ route('register') }}" class="button button-primary button-large">Create account to use</a>
        @endauth
    </div>
    <div>
        <img class="template-detail-image" src="{{ $template->preview_image }}" alt="{{ $template->name }} publishing campaign">
        <div class="template-sequence">
            <p class="eyebrow">Page sequence</p>
            @foreach($template->modules as $module)
                <div class="sequence-module">
                    <span>{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                    <div>
                        <strong>{{ config('modules.'.$module->module_type.'.name') }}</strong>
                        <small>{{ config('modules.'.$module->module_type.'.description') }}</small>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

<section class="template-live-preview" aria-labelledby="template-preview-title">
    <div class="template-live-preview-heading">
        <div>
            <p class="eyebrow">Rendered template</p>
            <h2 id="template-preview-title">See every module as a complete page</h2>
            <p>Switch between desktop and mobile to preview how this template adapts before you use it.</p>
        </div>
        <div class="preview-tools template-preview-tools" role="group" aria-label="Template preview size">
            <button type="button" class="active" data-template-preview-width="desktop" aria-pressed="true">Desktop</button>
            <button type="button" data-template-preview-width="mobile" aria-pressed="false">Mobile</button>
        </div>
    </div>
    <div class="preview-frame template-preview-frame" id="template-preview-frame">
        <div id="template-preview-content" aria-live="polite"></div>
    </div>
</section>

<script id="template-preview-data" type="application/json">{!! json_encode(
    $template->modules->map(fn ($module) => [
        'module_type' => $module->module_type,
        'position' => $module->position,
        'content' => $module->content,
        'settings' => $module->settings,
    ])->values(),
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
) !!}</script>
@endsection
