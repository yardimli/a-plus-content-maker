@extends('layouts.app')
@section('title', 'KDP transfer guide')
@section('page-title', $project->name)
@section('content')
<div class="transfer-guide">
    <div class="transfer-intro"><div><p class="eyebrow">Your design, step by step</p><h1>Copy your design to KDP</h1><p>Keep this guide beside Amazon’s A+ Content Manager. Add each module in order, then copy its text and upload its images.</p></div><a class="button button-secondary" href="{{ route('projects.builder', $project) }}">Back to editor</a></div>
    <nav class="transfer-nav" aria-label="Transfer steps"><a href="#transfer-start">1. Set up</a><a href="#transfer-modules">2. Add {{ $project->modules->count() }} {{ Str::plural('module', $project->modules->count()) }}</a><a href="#transfer-finish">3. Review & submit</a></nav>
    <section class="transfer-step" id="transfer-start"><p class="eyebrow">Step 1</p><h2>Open your KDP workspace</h2><p>In KDP, open Marketing → A+ Content, choose <strong>{{ $project->marketplace ?: 'your marketplace' }}</strong>, and select Manage A+ Content. Start creating A+ content.</p><a class="button button-primary" href="https://kdp.amazon.com/marketing" target="_blank" rel="noopener noreferrer">Open KDP Marketing ↗</a><div class="transfer-field"><strong>Content name</strong>@include('projects.transfer-copy', ['label' => 'Content name', 'text' => $project->name])</div><p>Choose the language your content is written in. Keep this page open as your copy-and-upload checklist.</p></section>
    <div id="transfer-modules">
    @forelse($project->modules as $module)
        @php($definition = $registry[$module->module_type])
        <section class="transfer-step transfer-module" id="transfer-module-{{ $module->uuid }}">
            <header class="transfer-module-header"><div><p class="eyebrow">Step 2 · Module {{ $loop->iteration }} of {{ $project->modules->count() }}</p><h2>{{ $definition['name'] }}</h2><p>In KDP, click <strong>Add Module</strong> and select this exact module name.</p>@include('projects.transfer-copy', ['label' => 'module name', 'text' => $definition['name']])</div><img class="transfer-reference" src="{{ asset('images/modules/'.$module->module_type.'.png') }}" alt="{{ $definition['name'] }} field layout" loading="lazy"></header>
            <p class="transfer-hint">{{ $definition['description'] }} Fill the fields below in the matching module.</p>
            @foreach($definition['fields'] ?? [] as $field)
                @include('projects.transfer-field', ['field' => $field, 'value' => $module->content[$field['key']] ?? null, 'slot' => $field['key']])
            @endforeach
            @foreach($definition['repeaters'] ?? [] as $repeater)
                <div class="transfer-group"><h3>{{ $repeater['label'] }}</h3><p class="transfer-hint">Follow the same order: left to right, then top to bottom.</p>
                @forelse($module->content[$repeater['key']] ?? [] as $rowIndex => $row)
                    <div class="transfer-row"><h4>{{ $repeater['label'] }} {{ $loop->iteration }}</h4>
                    @foreach($repeater['fields'] as $field)
                        @if($module->module_type === 'comparison_chart' && $repeater['key'] === 'metrics' && $field['key'] === 'values')
                            @php($values = explode('|', $row['values'] ?? ''))
                            @foreach($module->content['products'] ?? [] as $product)
                                <div class="transfer-field"><strong>Column {{ $loop->iteration }} · {{ ($product['title'] ?? '') ?: 'Untitled book' }}</strong>@include('projects.transfer-copy', ['label' => 'column '.$loop->iteration.' value', 'text' => trim($values[$loop->index] ?? '')])</div>
                            @endforeach
                        @else
                            @include('projects.transfer-field', ['field' => $field, 'value' => $row[$field['key']] ?? null, 'slot' => $repeater['key'].'.'.$rowIndex.'.'.$field['key']])
                        @endif
                    @endforeach
                    </div>
                @empty
                    <p class="transfer-hint">No {{ strtolower($repeater['label']) }} entries in this design.</p>
                @endforelse
                </div>
            @endforeach
            <label class="transfer-complete"><input type="checkbox"> I’ve copied module {{ $loop->iteration }} into KDP</label>
        </section>
    @empty
        <section class="transfer-step"><h2>Add your first module</h2><p>Your project has no modules yet. Return to the editor to build your design, then open this guide again.</p><a class="button button-primary" href="{{ route('projects.builder', $project) }}">Open editor</a></section>
    @endforelse
    </div>
    <section class="transfer-step" id="transfer-finish"><p class="eyebrow">Step 3</p><h2>Review, apply ASINs, and submit</h2><ol><li>Use KDP’s Preview to check desktop and mobile layouts against your design. Save as draft to keep your progress.</li><li>Choose Next: Apply ASINs. Find and select the book formats from your KDP Bookshelf, then apply them.</li><li>Continue to Review and Submit. When everything looks right, submit for approval in KDP.</li></ol><div class="transfer-field"><strong>Your project ASIN</strong>@if($project->asin)@include('projects.transfer-copy', ['label' => 'project ASIN', 'text' => $project->asin])@else<p class="transfer-hint">No ASIN linked yet. Find the book in your KDP Bookshelf.</p>@endif</div><p class="transfer-hint">The checkboxes on this guide are a checklist for this visit. Submit your content in KDP when you’re ready.</p><a class="text-link" href="https://kdp.amazon.com/en_US/help/topic/G8EP5W6H9CY7T8GS" target="_blank" rel="noopener noreferrer">Amazon’s A+ Content instructions ↗</a></section>
</div>
@endsection
