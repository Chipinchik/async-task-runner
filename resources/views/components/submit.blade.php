@props(['label', 'icon' => 'plus'])
<button {{ $attributes->class(['button']) }} :disabled="submitting" :aria-busy="submitting"><x-icon
        :name="$icon" /><span
        x-text="submitting ? 'Подождите…' : @js($label)">{{ $label }}</span></button>
