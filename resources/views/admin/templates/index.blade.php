@extends('layouts.app')
@section('title', 'Manage templates') @section('page-title', 'Template studio')
@section('content')
<div class="page-lead"><div><p class="eyebrow">Administration</p><h2>Curate the starting gallery.</h2><p>Publish thoughtful module sequences for every kind of author.</p></div><a href="{{ route('admin.templates.create') }}" class="button button-primary">＋ New template</a></div>
<div class="table-card"><table><thead><tr><th>Template</th><th>Category</th><th>Modules</th><th>Status</th><th>Updated</th><th></th></tr></thead><tbody>@foreach($templates as $template)<tr><td><strong>{{ $template->name }}</strong><small>{{ $template->summary }}</small></td><td>{{ $template->category }}</td><td>{{ $template->modules_count }}</td><td><span class="status status-{{ $template->status }}">{{ ucfirst($template->status) }}</span></td><td>{{ $template->updated_at->diffForHumans() }}</td><td><a href="{{ route('admin.templates.edit', $template) }}">Edit →</a></td></tr>@endforeach</tbody></table></div>{{ $templates->links() }}
@endsection
