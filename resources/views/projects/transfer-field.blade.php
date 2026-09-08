@php
    $value = $value ?? $field['default'] ?? null;
    $asset = $field['type'] === 'image' ? $project->assets->firstWhere('id', $value) : null;
    $downloadUrl = $asset ? route('projects.transfer.download', [$project, $asset, 'module' => $module->uuid, 'slot' => $slot]) : null;
@endphp
<div class="transfer-field">
    <div class="transfer-field-heading"><strong>{{ $field['label'] }}</strong>@if($field['required'] ?? false)<small>Required</small>@endif</div>
    @if($field['type'] === 'image')
        <p class="transfer-hint">Upload to this image slot · {{ $field['width'] }} × {{ $field['height'] }} px</p>
        @if($asset)
            <div class="transfer-image"><img src="{{ $downloadUrl }}&preview=1" alt="{{ $asset->alt_text }}" loading="lazy"><div><a class="button button-secondary" href="{{ $downloadUrl }}">Download image</a><p class="transfer-hint">{{ $asset->width }} × {{ $asset->height }} px · {{ $asset->mime_type === 'image/webp' ? 'PNG download' : $asset->original_name }}</p></div></div>
            @if($asset->width != $field['width'] || $asset->height != $field['height'])<p class="transfer-missing">Size differs from this slot. Fit the image in the editor before uploading.</p>@endif
            <strong>Image keywords / alt text</strong>
            @include('projects.transfer-copy', ['label' => 'Image keywords / alt text', 'text' => $asset->alt_text])
        @else
            <p class="transfer-missing">No image selected. Add one in the editor before filling this slot.</p>
        @endif
    @elseif($field['type'] === 'checkbox')
        <p class="transfer-setting">{{ $value ? '☑ Check' : '☐ Leave unchecked' }} “{{ $field['label'] }}” in KDP.</p>
    @elseif($field['type'] === 'select')
        <p class="transfer-setting">Select “{{ $field['options'][$value] ?? $value }}”.</p>
    @elseif($field['type'] === 'richtext' && filled($value))
        <div class="transfer-copy-block"><div class="transfer-rich" data-copy-content data-copy-rich>{!! $sanitizer->clean($value) !!}</div><button type="button" class="button button-secondary" data-copy>Copy text</button></div>
        <p class="transfer-hint">Paste into {{ $field['label'] }}. Check paragraph breaks, bold text, and lists after pasting.</p>
    @else
        @include('projects.transfer-copy', ['label' => $field['label'], 'text' => $value, 'required' => $field['required'] ?? false])
    @endif
</div>
