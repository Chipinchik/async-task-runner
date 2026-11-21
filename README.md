# TaskFlow

Практическая реализация распределённой асинхронной обработки задач на Laravel 13, PHP 8.3, RabbitMQ и PostgreSQL. Интерфейс — Blade, Tailwind CSS, Alpine.js и Vite. Все задания действительно выполняются в отдельных процессах, без frontend-имитации.

## Быстрый запуск на Windows

Нужен запущенный Docker Desktop с Linux containers (WSL2). PHP, Composer и Node на хосте для Docker-запуска не требуются.

В PowerShell из корня проекта:

```powershell
powershell -ExecutionPolicy Bypass -File .\docker\setup.ps1
docker compose up -d --build --scale worker=3
docker compose exec app php artisan taskflow:admin admin@example.test
```

Скрипт создаёт игнорируемый Git файл `.env` из `.env.example` и криптографически случайный APP_KEY. Существующий ключ сохраняется. Команда создания администратора спрашивает пароль скрытым вводом, минимум 12 символов; не передавайте пароль в аргументах командной строки. Существующий аккаунт не перезаписывается.

- Приложение: http://localhost:8000
- RabbitMQ Management: http://localhost:15672
- В явно локальном Compose-профиле пользователь RabbitMQ — `taskflow`, пароль по умолчанию — `local-taskflow-broker`. Это демонстрационные настройки, не реальные секреты.
- PostgreSQL и AMQP не опубликованы на хост. HTTP-порты привязаны только к `127.0.0.1`.
- Обычного пользователя можно зарегистрировать через интерфейс. Созданные через UI пользователи всегда имеют роль `user`.

Первый старт собирает frontend в Node stage, устанавливает Composer-зависимости, поднимает БД и брокер, автоматически выполняет миграции и объявляет durable очередь. Worker и publisher стартуют после healthcheck приложения.

На Linux/macOS: скопируйте `.env.example` в `.env`, задайте APP_KEY (например, `php artisan key:generate` при установленных зависимостях), затем используйте те же команды Compose.

## Возможности

- Регистрация, вход, выход, редактирование имени/email; CSRF, ограничения частоты запросов, серверные политики.
- Пользователь видит только свои задания. Администратор видит все задачи, мониторинг, статистику и может повторить окончательно неудачное задание.
- Dashboard, фильтры по статусу/типу, пагинация, карточка со входом, результатом, временем, ошибкой и историей.
- **DemoDelay:** 3–30 секунд, измеряемая длительность и прогресс каждую секунду.
- **TextAnalysis:** до 100 000 Unicode-символов; число символов, слов, строк, уникальных слов и топ-10 частот. Регистр игнорируется, дефис и апостроф внутри слова сохраняются. Символы считаются как Unicode code points, не графемы.
- **CsvAnalysis:** UTF-8 до 5 МиБ, BOM допустим, разделители запятая/точка с запятой/табуляция, quoted fields, до 100 уникальных непустых заголовков. Файл читается построчно; неверная ширина записи и кодировка завершают попытку ошибкой. Пустые значения не участвуют в арифметике. Столбец считается числовым, только если все непустые значения числовые и есть хотя бы одно число. Десятичный разделитель — точка; статистика использует float, не денежную арифметику.
- Мониторинг с HTTP polling 1,5 с, SVG-анимацией новых событий, реальными Worker ID, heartbeat TTL 15 с и журналом. Во время ошибки polling данные помечаются устаревшими. Коротким заданиям искусственный процент не назначается.
- Метрики `messages_ready`, `messages_unacknowledged`, `consumers` читаются сервером через Management API. Нет доступа — «нет данных», а не нули. Management API сам обновляет статистику с задержкой; статусы PostgreSQL показаны отдельно.

## Архитектура и гарантии

```text
Browser → Laravel HTTP → PostgreSQL (task + outbox в одной транзакции)
                             ↓
                     отдельный publisher
                             ↓ publisher confirm
                         RabbitMQ
                             ↓ basic_consume, prefetch=1
                     независимые Workers
                             ↓
                PostgreSQL (состояние, результат, события)
                             ↑
                    HTTP polling браузера
```

| Сервис | Назначение |
| --- | --- |
| app | HTTP, авторизация, валидация, интерфейс и JSON |
| publisher | Outbox, публикация, повторы публикации и восстановление просроченных выполнений |
| worker | Laravel Job, получение сообщений через RabbitMQ basic_consume |
| postgres | Пользователи, задания, события, outbox, heartbeat, сессии |
| rabbitmq | Durable очередь, persistent сообщения, management plugin |

HTTP сохраняет `task_created` и запись outbox, быстро возвращает ответ и не ждёт брокер или обработчик. Диспетчер опрашивает outbox раз в секунду. `task_queued` и `queued_at` появляются только после publisher confirm. Публикация проводится под короткой блокировкой task/outbox, чтобы Worker не записал старт раньше регистрации подтверждения. Нет транзакции на всё выполнение задания.

Сообщение содержит UUID задачи и номер поколения; вход и результат остаются в PostgreSQL, CSV — в приватном общем volume. Используется стандартная сериализация Laravel Job.

Доставка **at least once**, без обещания exactly-once. Возможен дубль, если процесс завершился после подтверждения RabbitMQ, но до фиксации БД. Получение защищено транзакционной блокировкой строки, поколением сообщения и execution token. Завершённые, уже выполняющиеся и устаревшие поколения игнорируются. Токен не позволяет старому исполнителю перезаписать результат новой попытки. Эти обработчики вычисляют результат без внешних побочных эффектов; добавляя отправку писем/платежи, нужно отдельно проектировать их идемпотентность.

Три попытки обработки в одном цикле. При исключении сохраняется `task_failed`; если лимит не исчерпан — `task_retried`, новое поколение и outbox с задержкой 5/15 секунд. После третьей ошибки статус остаётся `failed`. Ручной повтор допускается только администратором и только из `failed`, сбрасывает счётчик текущего цикла; вся история сохраняется. Повторы обработки управляются устойчивой записью в БД, а не повторным исполнением из HTTP.

Таймаут Job — 60 с; lease выполнения — 90 с, продлевается при прогрессе. Убитый Worker восстанавливается через publisher после истечения lease. Heartbeat сообщает только о недавней активности процесса; просроченный heartbeat не считается доказательством жизни. RabbitMQ не получает заранее назначенный Worker.

При недоступности брокера outbox остаётся в БД, записывается понятная ошибка, повтор публикации — через 5 с без лимита. Worker-процессы перезапускаются Docker. После длительного сбоя restart backoff Docker может добавить до минуты к восстановлению. Удаление очереди администратором брокера или потеря persistent volumes не восстанавливаются автоматически.

### Совместимость драйвера

В lock-файле зафиксированы Laravel 13.43 и `vladimir-yuldashev/laravel-queue-rabbitmq` 15.0.2. В `app/Queue/TaskflowConsumer.php` адаптированы изменённые сигнатуры timeout/stop Laravel 13. В `ConfirmedRabbitMQQueue.php` добавлены publisher confirms, обработка nack/returned message и переподключение. Confirm mode включается один раз на AMQP channel. Файлы `vendor` не меняются. При обновлении framework/driver обязательно повторять реальный интеграционный тест.

Первичные источники: [драйвер RabbitMQ](https://github.com/vyuldashev/laravel-queue-rabbitmq), [Laravel Queues](https://laravel.com/docs/13.x/queues), [RabbitMQ confirms](https://www.rabbitmq.com/docs/confirms).

## Настройки

| Переменные | Использование |
| --- | --- |
| APP_KEY | Обязательный локальный ключ шифрования приложения |
| TASKFLOW_DB_PASSWORD | Пароль PostgreSQL в Compose |
| TASKFLOW_RABBITMQ_PASSWORD | Пароль RabbitMQ и его Management API в Compose |
| DB_* | Настройки подключения Laravel к PostgreSQL |
| RABBITMQ_HOST/PORT/USER/PASSWORD/VHOST/QUEUE | Подключение драйвера RabbitMQ |
| RABBITMQ_MANAGEMENT_URL | Серверный URL Management API, секреты браузеру не отправляются |
| SESSION_DRIVER / CACHE_STORE | В Compose оба используют database |
| config/taskflow.php | TTL heartbeat, lease, интервалы повторов |

Compose явно задаёт сетевые имена `postgres` и `rabbitmq`, очередь `taskflow` и локальные сервисные настройки; произвольные значения из старого Laravel `.env` их не подменяют. Меняя имя очереди в Compose, измените также команды объявления/consumer в `docker/entrypoint.sh`. Пароли после первого запуска volume нужно менять согласованно внутри самих БД/брокера: изменение env не переинициализирует существующие данные.

Данные сохраняются в named volumes `postgres_data`, `rabbitmq_data`, `task_storage`. Загруженные CSV не находятся в public. `.env*`, vendor, node_modules, SQLite и сборочные артефакты игнорируются Git; `.env.example` и lock-файлы сохраняются.

## Демонстрация и эксплуатация

```powershell
docker compose up -d --scale worker=3
docker compose ps
docker compose logs -f worker publisher
```

Войдите администратором, откройте «Администрирование» или «Мониторинг», нажмите «Создать 6 тестовых задач». Ожидаются три одновременно работающих Worker и две волны примерно по 10 с. Допустимы накладные расходы и задержки polling.

Для сравнения последовательной обработки, дождитесь пустой очереди:

```powershell
docker compose up -d --scale worker=1
# Создайте 6 задач: ожидается около 60 секунд.
docker compose up -d --scale worker=3
```

После изменений исходников нужна пересборка — рабочая директория не bind-mounted:

```powershell
docker compose up -d --build --scale worker=3
docker compose exec app php artisan migrate --force
docker compose exec app php artisan taskflow:publish --once
docker compose exec rabbitmq rabbitmqctl list_queues name messages_ready messages_unacknowledged consumers
docker compose down
```

`down` сохраняет volumes. `down -v` удаляет все данные — для обычной остановки не используйте его.

Проверка сбоя брокера: `docker compose stop rabbitmq`, создайте задание и убедитесь, что оно ожидает публикации; затем `docker compose start rabbitmq`. Дождитесь переподключения Worker и завершения. Событие `task_queued` не должно появляться при неудачной публикации.

## Тесты

Редизайн использует локально установленный UI UX Pro Max 2.15.0. Источники, выполненные поисковые запросы, принятые решения, палитра и компоненты описаны в [design-system/MASTER.md](design-system/MASTER.md). Для работы приложения навык не требуется. Inter поставляется локально через Vite; запросов к внешнему CDN шрифтов нет.

Безопасная смена пароля **существующего** администратора:

```powershell
docker compose exec app php artisan taskflow:admin-password admin@example.test
```

Команда дважды запрашивает пароль скрытым вводом (минимум 12 символов) и завершает предыдущие сессии пользователя. Пароль не передаётся аргументом и не сохраняется в исходниках. Создание нового администратора по-прежнему выполняется через `taskflow:admin`.

Проверки логики визуализации без браузера (дубликаты событий, подтверждённый Worker, отсутствие выдуманного прогресса, истечение heartbeat):

```powershell
node --test tests/monitor-state.test.js
```

Быстрые Unit/Feature тесты используют SQLite в памяти и не трогают данные Compose:

```powershell
docker compose exec app php vendor/bin/phpunit
```

Без Docker (нужны Composer-зависимости и расширения PHP; на данном Windows ZIP/sockets/SQLite включаются параметрами процесса):

```powershell
php -d extension=sockets -d extension=pdo_sqlite vendor/bin/phpunit
php vendor/bin/pint --test
```

Отдельный реальный тест, после запуска трёх Worker:

```powershell
docker compose exec app php vendor/bin/phpunit -c phpunit.integration.xml
```

Проверка последовательной обработки и аварийного завершения (на свободной локальной очереди):

```powershell
docker compose up -d --scale worker=1
docker compose exec -e TASKFLOW_TEST_WORKERS=1 app php vendor/bin/phpunit -c phpunit.integration.xml --filter test_six_real_jobs
docker compose up -d --scale worker=3
# PowerShell 7.3+: тест сам создаёт данные, убивает workers после старта задачи,
# восстанавливает сервисы, проверяет повтор и удаляет только собственные данные.
pwsh -File tests/Integration/worker-crash.ps1
```

Unit/Feature конфигурация принудительно устанавливает тестовые env/server переменные. Дополнительно `Tests/TestCase.php` блокирует `RefreshDatabase` для любой БД, кроме SQLite `:memory:`. Реальный интеграционный набор не запускает миграции с очисткой.

Этот тест не использует Queue::fake или RefreshDatabase. Создаёт собственных пользователей/задания, ждёт реальные сообщения, измеряет параллельность, проверяет метрики, дубль доставки, текст, общий CSV, ограничение попыток и ручной повтор. В конце удаляет только свои данные. Запускайте на свободной локальной очереди: сторонние задания могут исказить временной порог. Прерванный тест может оставить собственные тестовые записи.

## Ограничения

Это локальный демонстрационный стек: PHP development server, один PostgreSQL и один RabbitMQ, без HA, TLS, резервного копирования и production reverse proxy. Для публичного развёртывания нужны отдельная конфигурация, собственные секреты и эксплуатационные меры. Redis, Horizon и WebSockets намеренно не используются.

Мониторинг доступен администратору; пользователь отслеживает собственную задачу в её карточке. JSON выдаёт не более 100 текущих задач/событий, журнал в БД хранится полностью. Автоматическая очистка загруженных CSV и архива событий не реализована: они остаются для повторов и аудита. Период хранения выбирает владелец приложения.

После редизайна проверены Docker-сборка, frontend, миграции, **25 Unit/Feature тестов (152 assertions)**, **2 реальных интеграционных теста (50 assertions)** и **4 JavaScript-теста визуализации**. Последний замер 6 задач по 10 с: **22,10 с на трёх Worker**, три одновременно выполняемые задачи, три разных Worker ID и три unacknowledged сообщения. Предыдущие замеры: 21,15 с на трёх Worker и 62,49 с на одном (один Worker в этом проходе повторно не измерялся). Проверены Unicode-текст, CSV, автоматические и ручные повторы, дубликаты доставки, отключение/восстановление RabbitMQ и SIGKILL Worker с успешной второй попыткой. В браузере проверены все страницы на desktop и mobile, регистрация, реальный вход администратора, формы, фильтры, пагинация, прогресс, ошибки и пустые состояния. Скриншоты находятся локально в artifacts/redesign/ (исключены из Git). Ошибок JavaScript приложения не обнаружено.
