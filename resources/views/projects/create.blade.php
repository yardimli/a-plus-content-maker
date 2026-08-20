@extends('layouts.app')
@section('title', 'New project') @section('page-title', 'Create a new project')
@section('content')
<div class="wizard-layout"><section class="wizard-copy"><p class="eyebrow">Project setup</p><h2>Start with the book.</h2><p>Link an ASIN for useful context, then tell the studio who this page is for.</p><ol><li class="active">Book & project</li><li>Writing context</li><li>Build</li></ol></section><section class="form-card">
<form method="POST" action="{{ route('projects.store') }}" class="form-stack" id="project-form">@csrf
    @if($template)<div class="selected-template"><span>Selected template</span><strong>{{ $template->name }}</strong><p>{{ $template->summary }}</p></div><input type="hidden" name="template_id" value="{{ $template->id }}">@endif
    <div><label for="asin">Book ASIN <small>Optional</small></label><div class="inline-control"><input id="asin" name="asin" maxlength="10" value="{{ old('asin') }}" placeholder="B099LJNCHQ"><button class="button button-secondary" type="button" id="asin-lookup">Find book</button></div><span class="field-hint">We fetch product context securely through the server.</span><div id="asin-result"></div></div>
    <input type="hidden" name="product_snapshot" id="product_snapshot">
    <label>Project name<input name="name" value="{{ old('name', $template ? $template->name.' project' : '') }}" required placeholder="My book’s A+ page">@error('name')<span class="field-error">{{ $message }}</span>@enderror</label>
    <div class="form-grid"><label>Author or imprint<input name="author_name" value="{{ old('author_name') }}"></label><label>Genre<input name="genre" value="{{ old('genre') }}" placeholder="Fantasy, memoir…"></label></div>
    <div class="form-grid"><label>Audience<input name="audience" value="{{ old('audience') }}" placeholder="Readers who love…"></label><label>Tone<select name="tone"><option value="">Choose a tone</option><option>Atmospheric</option><option>Warm</option><option>Authoritative</option><option>Playful</option><option>Suspenseful</option></select></label></div>
    <label>Brand or writing notes<textarea name="brand_notes" rows="4" placeholder="Voice, themes, phrases to avoid, visual direction…">{{ old('brand_notes') }}</textarea></label><input type="hidden" name="marketplace" value="amazon.com">
    <div class="form-actions"><a href="{{ route('templates.index') }}" class="button button-ghost">Choose a template</a><button class="button button-primary" type="submit">Create project →</button></div>
</form></section></div>
@push('scripts')<script>window.projectCreate = { lookupUrl: @json(route('asin.lookup')) };</script>@endpush
@endsection
