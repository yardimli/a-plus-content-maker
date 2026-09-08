<div id="toast-region" class="toast-region" aria-label="Notifications" aria-live="polite" aria-relevant="additions">
    @foreach(['success' => session('success') ?: ($statusMessage ?? null), 'error' => session('error')] as $type => $message)
        @if($message)
            <div class="toast toast-{{ $type }}" role="{{ $type === 'error' ? 'alert' : 'status' }}" data-toast>
                <div class="toast-copy"><strong>{{ $type === 'error' ? 'Something went wrong' : 'Success' }}</strong><span>{{ $message }}</span></div>
                <button type="button" class="toast-close" aria-label="Dismiss notification">×</button>
            </div>
        @endif
    @endforeach
</div>
