@if ($errors->any())
    <div class="notice error" role="alert" tabindex="-1" x-data x-init="$el.focus()">
        <strong>Проверьте введённые данные</strong>
        <ul>
            @foreach ($errors->messages() as $field => $messages)
                <li><a href="#{{ $field }}">{{ $messages[0] }}</a></li>
            @endforeach
        </ul>
    </div>
@endif
