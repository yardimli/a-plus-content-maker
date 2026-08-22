@extends('layouts.app')

@section('title', $template->exists ? 'Edit template' : 'New template')
@section('page-title', $template->exists ? 'Edit template' : 'Create template')

@section('content')
@php
    $savedModules = $template->exists ? $template->modules->keyBy('module_type') : collect();
    $selectedTypes = collect(old('modules', $savedModules->keys()->all()));
    $oldContent = old('module_content', []);
@endphp
@if($errors->any())
    <div class="flash flash-error" role="alert"><strong>The template could not be saved.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif
<form method="POST" action="{{ $template->exists ? route('admin.templates.update', $template) : route('admin.templates.store') }}" class="admin-template-layout">
    @csrf
    @if($template->exists) @method('PUT') @endif

    <section class="form-card form-stack admin-template-details">
        <p class="eyebrow">Gallery details</p>
        <h2>{{ $template->exists ? $template->name : 'New starting point' }}</h2>
        <label>Name<input name="name" value="{{ old('name', $template->name) }}" required></label>
        <label>Short summary<input name="summary" value="{{ old('summary', $template->summary) }}"></label>
        <label>Description<textarea name="description" rows="5">{{ old('description', $template->description) }}</textarea></label>
        <div class="form-grid">
            <label>Category<input name="category" value="{{ old('category', $template->category) }}"></label>
            <label>Status<select name="status"><option value="draft" @selected(old('status', $template->status ?: 'draft') === 'draft')>Draft</option><option value="published" @selected(old('status', $template->status) === 'published')>Published</option><option value="archived" @selected(old('status', $template->status) === 'archived')>Archived</option></select></label>
        </div>
        <label>Tags <small>Comma separated</small><input name="tags" value="{{ old('tags', implode(', ', $template->tags ?? [])) }}"></label>
        <label class="check-row"><input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $template->is_featured))> Feature on the landing page</label>
    </section>

    <section class="form-card admin-template-modules">
        <div class="section-heading compact">
            <div><p class="eyebrow">Module sequence</p><h2>Choose and fill the structure</h2></div>
            <span id="module-count">{{ $selectedTypes->count() }} selected</span>
        </div>
        <p class="field-hint">Select modules, then fill their placeholder copy and image paths below. Authors receive this content when they clone the template.</p>
        <div class="admin-module-picker">
            @foreach($registry as $key => $definition)
                <label>
                    <input type="checkbox" name="modules[]" value="{{ $key }}" data-template-module-toggle="{{ $key }}" @checked($selectedTypes->contains($key))>
                    <span><strong>{{ $definition['name'] }}</strong><small>{{ $definition['description'] }}</small></span>
                    @if($definition['ai_ready']) <em>AI ready</em> @endif
                </label>
            @endforeach
        </div>

        <div class="admin-template-content-list">
            @foreach($registry as $key => $definition)
                @php
                    $moduleContent = $oldContent[$key] ?? $savedModules->get($key)?->content ?? app(\App\Services\ModuleRegistry::class)->defaults($key);
                    $isSelected = $selectedTypes->contains($key);
                @endphp
                <section class="admin-template-module-content" data-template-module-content="{{ $key }}" @if(!$isSelected) hidden @endif>
                    <header><div><span>{{ str_pad((string) ($selectedTypes->search($key) !== false ? $selectedTypes->search($key) + 1 : $loop->iteration), 2, '0', STR_PAD_LEFT) }}</span><div><strong>{{ $definition['name'] }}</strong><small>Placeholder content</small></div></div></header>
                    <div class="admin-template-fields">
                        @foreach($definition['fields'] ?? [] as $field)
                            @include('admin.templates.partials.content-field', ['field' => $field, 'inputName' => "module_content[{$key}][{$field['key']}]", 'value' => $moduleContent[$field['key']] ?? null])
                        @endforeach

                        @foreach($definition['repeaters'] ?? [] as $repeater)
                            @php $rows = $moduleContent[$repeater['key']] ?? []; @endphp
                            <section class="admin-template-repeater" data-template-repeater data-min="{{ $repeater['min'] ?? 0 }}" data-max="{{ $repeater['max'] }}">
                                <div class="repeater-head"><strong>{{ $repeater['label'] }} <small>({{ $repeater['min'] ?? 0 }}–{{ $repeater['max'] }})</small></strong><button type="button" class="button button-secondary" data-template-add-row>＋ Add</button></div>
                                <div data-template-rows>
                                    @foreach($rows as $rowIndex => $row)
                                        <div class="admin-template-repeater-row" data-template-row>
                                            <span class="admin-template-row-number">{{ $loop->iteration }}</span>
                                            @foreach($repeater['fields'] as $field)
                                                @include('admin.templates.partials.content-field', ['field' => $field, 'inputName' => "module_content[{$key}][{$repeater['key']}][{$rowIndex}][{$field['key']}]", 'value' => $row[$field['key']] ?? null])
                                            @endforeach
                                            <button type="button" class="text-button admin-template-remove-row" data-template-remove-row>Remove</button>
                                        </div>
                                    @endforeach
                                </div>
                                <template data-template-row-template>
                                    <div class="admin-template-repeater-row" data-template-row>
                                        <span class="admin-template-row-number"></span>
                                        @foreach($repeater['fields'] as $field)
                                            @include('admin.templates.partials.content-field', ['field' => $field, 'inputName' => "module_content[{$key}][{$repeater['key']}][__INDEX__][{$field['key']}]", 'value' => null])
                                        @endforeach
                                        <button type="button" class="text-button admin-template-remove-row" data-template-remove-row>Remove</button>
                                    </div>
                                </template>
                            </section>
                        @endforeach

                        @if(empty($definition['fields']) && empty($definition['repeaters']))
                            <p class="field-hint">This module has no editable placeholder fields.</p>
                        @endif
                    </div>
                </section>
            @endforeach
        </div>

        <div class="form-actions admin-template-actions"><a href="{{ route('admin.templates.index') }}" class="button button-ghost">Cancel</a><button class="button button-secondary" type="button" id="admin-template-preview-open">Preview template</button><button class="button button-primary" type="submit">Save template</button></div>
    </section>
</form>
@include('admin.templates.partials.asset-dialogs')
<dialog id="admin-template-preview-dialog" class="studio-dialog admin-template-preview-dialog"><div class="dialog-shell"><header><div><p class="eyebrow">Unsaved preview</p><h2>Complete template</h2></div><button type="button" class="icon-button" data-admin-preview-close aria-label="Close">×</button></header><div class="admin-template-preview-body"><div class="preview-tools"><span>Rendered module sequence</span><div><button type="button" class="active" data-admin-preview-width="desktop" aria-pressed="true">Desktop</button><button type="button" data-admin-preview-width="mobile" aria-pressed="false">Mobile</button></div></div><div class="preview-frame" id="admin-template-preview-frame"><div id="admin-template-preview-content"></div></div></div><footer><button type="button" class="button button-secondary" data-admin-preview-close>Close preview</button></footer></div></dialog>
<script id="template-assets-data" type="application/json">{!! json_encode([
    'assets' => $assets,
    'routes' => ['store' => route('admin.template-assets.store'), 'assetBase' => url('/assets')],
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}</script>
@endsection
