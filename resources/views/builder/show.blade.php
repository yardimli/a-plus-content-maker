@extends('layouts.app')
@section('title', $project->name) @section('page-title', $project->name)
@section('content')
<div id="builder" class="builder" data-project="{{ $project->uuid }}">
    <header class="builder-toolbar"><div class="builder-tabs" role="tablist"><button class="active" data-builder-tab="editor" role="tab">Editor</button><button data-builder-tab="preview" role="tab">Preview</button></div><div class="save-state" id="save-state"><span></span>All changes saved</div><div class="builder-actions"><button class="button button-secondary" id="validate-project" type="button">Check content</button><form method="POST" action="{{ route('projects.export', $project) }}">@csrf<button class="button button-primary" type="submit">Export for KDP</button></form></div></header>
    <div class="book-context">@if(data_get($project->product_snapshot, 'image_url'))<img src="{{ data_get($project->product_snapshot, 'image_url') }}" alt="">@endif<div><span>{{ $project->asin ?: 'No ASIN linked' }}</span><strong>{{ data_get($project->product_snapshot, 'title', $project->name) }}</strong></div><button class="text-button" type="button" id="edit-context">Project context</button></div>
    <div class="builder-workspace" data-panel="editor"><aside class="builder-outline"><div class="outline-head"><strong>Page outline</strong><button type="button" class="icon-button" data-open-module-dialog aria-label="Add module">＋</button></div><ol id="module-outline"></ol><button type="button" class="button button-secondary button-wide" data-open-module-dialog>＋ Add module</button><div class="asset-summary"><span>Assets</span><strong>{{ $project->assets->count() }} images</strong></div></aside><section class="builder-canvas"><div id="module-list"></div><div class="builder-empty" id="builder-empty"><div class="step-icon">＋</div><h2>Begin your page</h2><p>Choose a KDP-style module and make it yours.</p><button type="button" class="button button-primary" data-open-module-dialog>Add your first module</button></div></section></div>
    <section class="preview-panel" data-panel="preview" hidden><div class="preview-tools"><span>Page preview</span><div><button class="active" type="button" data-preview-width="desktop">Desktop</button><button type="button" data-preview-width="mobile">Mobile</button></div></div><div class="preview-frame" id="preview-frame"><div id="preview-content"></div></div></section>
</div>

<dialog id="module-dialog" class="studio-dialog"><form method="dialog" class="dialog-shell"><header><div><p class="eyebrow">Module library</p><h2>Add a module</h2></div><button value="cancel" class="icon-button" aria-label="Close">×</button></header><div class="dialog-search"><input type="search" id="module-search" placeholder="Search layouts, text, images, comparisons…"></div><div class="module-gallery" id="module-gallery"></div></form></dialog>
<dialog id="asset-dialog" class="studio-dialog asset-dialog"><form method="dialog" class="dialog-shell" id="asset-form"><header><div><p class="eyebrow">Image library</p><h2>Add an image</h2></div><button value="cancel" class="icon-button" aria-label="Close">×</button></header><div class="asset-modal-body"><label class="drop-zone" for="asset-file"><span class="image-symbol">▧</span><strong>Drag an image here</strong><small>or click to select JPG, PNG, or WebP</small><input type="file" id="asset-file" name="image" accept="image/jpeg,image/png,image/webp"></label><div id="asset-requirement" class="field-hint"></div><label>Alt text<input id="asset-alt" name="alt_text" required maxlength="250" placeholder="Describe the image for readers who cannot see it"></label><div id="asset-preview"></div><div class="ai-image-box"><p class="eyebrow">Or create an original</p><label>Image direction<textarea id="asset-ai-prompt" rows="3" placeholder="A cinematic starship crossing a violet nebula, no typography…"></textarea></label><button type="button" class="button button-secondary" id="asset-ai-generate">✦ Generate with AI</button></div></div><footer><button value="cancel" class="button button-ghost">Cancel</button><button type="submit" class="button button-primary" id="asset-submit">Upload and use</button></footer></form></dialog>
<dialog id="ai-dialog" class="studio-dialog ai-dialog"><form method="dialog" class="dialog-shell" id="ai-form"><header><div><p class="eyebrow">AI writing partner</p><h2>Draft module copy</h2></div><button value="cancel" class="icon-button" aria-label="Close">×</button></header><div class="asset-modal-body"><p>Book details and your project context will guide the draft. Review every word before applying it.</p><label>What should this module communicate?<textarea id="ai-prompt" rows="5" required placeholder="Focus on the found-family theme and the scale of the adventure…"></textarea></label><div id="ai-result"></div></div><footer><button value="cancel" class="button button-ghost">Cancel</button><button type="submit" class="button button-primary">Generate draft</button></footer></form></dialog>

<script id="builder-data" type="application/json">{!! json_encode([
    'project' => $project->only(['uuid','name','asin','author_name','genre','audience','tone','brand_notes','product_snapshot']),
    'modules' => $project->modules->map(fn($module) => $module->only(['uuid','module_type','position','content','settings','version'])),
    'assets' => $project->assets,
    'registry' => $registry,
    'routes' => [
        'moduleStore' => route('projects.modules.store', $project),
        'moduleBase' => url('/projects/'.$project->uuid.'/modules'),
        'reorder' => route('projects.modules.reorder', $project),
        'assets' => route('projects.assets.store', $project),
        'asinImport' => route('projects.asin.import', $project),
        'aiText' => route('projects.ai.text', $project),
        'aiImage' => route('projects.ai.image', $project),
    ],
], JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT) !!}</script>
@endsection
