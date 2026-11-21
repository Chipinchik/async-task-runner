@props(['status'])
<span class="badge {{ $status }}"><span class="status-dot"></span>{{ \App\Models\Task::STATUSES[$status] }}</span>
