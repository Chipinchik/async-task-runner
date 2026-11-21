<section class="panel">
    <div class="panel-heading">
        <h2>Журнал событий</h2><label class="event-filter"><span class="sr-only">Фильтр событий</span><select
                x-model="eventFilter">
                <option value="important">Ключевые события</option>
                <option value="all">Все события</option>
                <option value="errors">Только ошибки</option>
            </select></label>
    </div>
    <div class="event-list"><template x-for="event in visibleEvents()" :key="event.id">
            <div class="event-row" :class="event.event_type"><span class="status-dot"></span>
                <div><strong x-text="eventLabel(event.event_type)"></strong><a :href="taskUrl(event.task_id)"
                        x-text="'#' + event.task_id.slice(-8)"></a>
                    <p x-text="event.message"></p>
                    <p class="mono" x-show="event.worker_id" x-text="event.worker_id"></p>
                </div><time :title="date(event.created_at)" x-text="time(event.created_at)"></time>
            </div>
        </template>
        <div class="empty-state compact" x-show="visibleEvents().length === 0"><x-icon name="activity" />
            <p>Пока нет событий для этого фильтра.</p>
        </div>
    </div>
    <div class="diagram-footer">Последние 100 полученных событий · показано до 50 · местное время</div>
</section>
