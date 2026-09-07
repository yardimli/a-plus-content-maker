@extends('layouts.app')
@section('title', 'New template')
@section('page-title', 'Create template')
@section('content')
<div class="page-lead"><div><p class="eyebrow">Administration</p><h2>Start a new gallery template.</h2><p>Create its gallery entry, then arrange and edit modules in the shared builder.</p></div></div>
<form method="POST" action="{{ route('admin.templates.store') }}" class="form-card form-stack template-create-form">
    @csrf
    <label>Name<input name="name" value="{{ old('name') }}" required maxlength="120"></label>
    <label>Short summary<input name="summary" value="{{ old('summary') }}" maxlength="240"></label>
    <label>Description<textarea name="description" rows="4" maxlength="3000">{{ old('description') }}</textarea></label>
    <div class="form-grid"><label>Category<input name="category" value="{{ old('category') }}" maxlength="80"></label><label>Status<select name="status"><option value="draft">Draft</option><option value="published">Published</option><option value="archived">Archived</option></select></label></div>
    <label>Tags <small>Comma separated</small><input name="tags" value="{{ old('tags') }}"></label>
    <label class="check-row"><input type="checkbox" name="is_featured" value="1" @checked(old('is_featured'))> Feature on the landing page</label>
    <div class="form-actions"><a href="{{ route('admin.templates.index') }}" class="button button-ghost">Cancel</a><button class="button button-primary" type="submit">Create and open builder</button></div>
</form>
@endsection
