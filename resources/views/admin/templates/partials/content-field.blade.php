@php
    $fieldId = 'template-field-'.trim(preg_replace('/[^A-Za-z0-9_]+/', '-', $inputName), '-');
    $type = $field['type'];
@endphp
@if($type === 'image')
    <div class="admin-template-field wide admin-template-image-control" data-template-image-field data-width="{{ $field['width'] }}" data-height="{{ $field['height'] }}">
        <span>{{ $field['label'] }} <small>{{ $field['width'] }} × {{ $field['height'] }}px</small></span>
        <input id="{{ $fieldId }}" type="hidden" name="{{ $inputName }}" value="{{ $value }}" data-template-image-value>
        <button type="button" class="admin-template-image-picker" data-template-choose-image>
            @if($value)<img src="{{ $value }}" alt=""><span>Change image</span>@else<span><strong>Choose image</strong><small>Upload or select from the template library</small></span>@endif
        </button>
        <button type="button" class="text-button admin-template-clear-image" data-template-clear-image @if(!$value) hidden @endif>Remove image</button>
    </div>
@else
    <label class="admin-template-field {{ $type === 'richtext' ? 'wide' : '' }}" for="{{ $fieldId }}">
        <span>{{ $field['label'] }}</span>
        @if($type === 'checkbox')
            <input type="hidden" name="{{ $inputName }}" value="0">
            <span class="check-row"><input id="{{ $fieldId }}" type="checkbox" name="{{ $inputName }}" value="1" @checked((bool) $value)> Enabled</span>
        @elseif($type === 'richtext')
            <div class="rich-editor" data-admin-rich-editor>
                <div class="rich-toolbar" role="toolbar" aria-label="Format {{ strtolower($field['label']) }}"><button type="button" data-admin-rich-command="bold" aria-label="Bold">B</button><button type="button" data-admin-rich-command="italic" aria-label="Italic"><i>I</i></button><button type="button" data-admin-rich-command="underline" aria-label="Underline"><u>U</u></button><button type="button" data-admin-rich-command="insertUnorderedList" aria-label="Bulleted list">•≡</button><button type="button" data-admin-rich-command="insertOrderedList" aria-label="Numbered list">1≡</button></div>
                <div class="rich-content" contenteditable="true" data-admin-rich-content data-placeholder="Enter {{ strtolower($field['label']) }}">{!! app(\App\Services\RichTextSanitizer::class)->clean($value) !!}</div>
                <textarea id="{{ $fieldId }}" name="{{ $inputName }}" data-admin-rich-source hidden>{{ $value }}</textarea>
            </div>
        @elseif($type === 'select')
            <select id="{{ $fieldId }}" name="{{ $inputName }}">@foreach($field['options'] ?? [] as $optionValue => $optionLabel)<option value="{{ $optionValue }}" @selected((string) $value === (string) $optionValue)>{{ $optionLabel }}</option>@endforeach</select>
        @else
            <input id="{{ $fieldId }}" name="{{ $inputName }}" value="{{ $value }}" @if($type === 'asin') maxlength="20" placeholder="Amazon ASIN" @else placeholder="Add placeholder {{ strtolower($field['label']) }}…" @endif>
        @endif
    </label>
@endif
