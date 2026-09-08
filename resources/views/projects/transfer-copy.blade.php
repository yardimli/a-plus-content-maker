<div class="transfer-copy-block">
    @if(filled($text))
        <div class="transfer-text" data-copy-content>{{ $text }}</div><button type="button" class="button button-secondary" data-copy aria-label="Copy {{ $label }}">Copy text</button>
    @else
        <p class="{{ ($required ?? false) ? 'transfer-missing' : 'transfer-hint' }}">{{ ($required ?? false) ? 'Missing — fill this in the editor.' : 'Empty — leave this field blank in KDP.' }}</p>
    @endif
</div>
