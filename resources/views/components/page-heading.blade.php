@props(['title', 'description' => null, 'eyebrow' => null])
<div class="page-heading">
    <div>
        @if ($eyebrow)
            <p class="eyebrow">{{ $eyebrow }}</p>
        @endif
        <h1>
            {{ $title }}</h1>
        @if ($description)
            <p class="muted">{{ $description }}</p>
        @endif
    </div>
    <div class="heading-actions">{{ $slot }}</div>
</div>
