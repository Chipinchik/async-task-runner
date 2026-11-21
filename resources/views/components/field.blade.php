@props(['name', 'label', 'type' => 'text', 'hint' => null, 'value' => null])
@php
    $previousValue = old($name, $value);
    $previousValue = is_scalar($previousValue) ? $previousValue : '';
@endphp
<div class="field" @if ($type === 'password') x-data="{ visible: false }" @endif>
    <label for="{{ $name }}">{{ $label }}</label>
    <div @class(['input-wrap', 'password-wrap' => $type === 'password'])>
        <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}"
            @if ($type === 'password') :type="visible ? 'text' : 'password'" @else value="{{ $previousValue }}" @endif
            aria-invalid="{{ $errors->has($name) ? 'true' : 'false' }}"
            aria-describedby="{{ $name }}-hint {{ $name }}-error" {{ $attributes }}>
        @if ($type === 'password')
            <button class="icon-button password-toggle" type="button" @click="visible = !visible"
                :aria-label="visible ? 'Скрыть пароль' : 'Показать пароль'" :aria-pressed="visible"><x-icon
                    name="eye" /></button>
        @endif
    </div>
    <small class="help" id="{{ $name }}-hint">{{ $hint }}</small>
    <span class="field-error" id="{{ $name }}-error">
        @error($name)
            {{ $message }}
        @enderror
    </span>
</div>
