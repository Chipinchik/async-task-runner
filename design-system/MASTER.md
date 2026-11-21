# TaskFlow · Premium Light Workspace

Единая дизайн-система Blade / Tailwind CSS 4 / Alpine.js. Светлая тема — единственная и основная. Реализация токенов и компонентов: `resources/css/app.css`, `resources/views/components/`.

## Источник и применение UI UX Pro Max

Официальный проект: https://github.com/nextlevelbuilder/ui-ux-pro-max-skill. Установлен `ui-ux-pro-max-cli@2.15.0` для Codex в `.agents/skills/`; старый `uipro-cli` не использовался. Перед запуском проверены официальный README, package.json, init/template source, npm scripts и архив файлов. Lifecycle-скрипты npm отключены. Пакет содержит CLI, CSV-базы знаний, Python-поиск и шаблоны навыков. `--dry-run` в опубликованной версии отсутствует; проверка выполнена распаковкой npm-архива. Навык и его данные прочитаны; поиск запускался в Python 3.13 контейнере без установки Python в Windows.

Воспроизводимые запросы (из корня):

```sh
python .agents/skills/ui-ux-pro-max/scripts/search.py "developer SaaS dashboard minimal light" --design-system -p TaskFlow -f markdown
python .agents/skills/ui-ux-pro-max/scripts/search.py "minimalism swiss style" --domain style -n 1
python .agents/skills/ui-ux-pro-max/scripts/search.py "Inter professional" --domain typography -n 1
python .agents/skills/ui-ux-pro-max/scripts/search.py "error summary validation" --domain ux -n 2
python .agents/skills/ui-ux-pro-max/scripts/search.py "forms validation blade" --stack laravel -n 2
python .agents/skills/ui-ux-pro-max/scripts/search.py "part to whole" --domain chart -n 1
```

Windows без Python: префикс `docker run --rm --mount type=bind,source=D:\TaskFlow,target=/app -w /app python:3.13-alpine` перед командой `python`.

Приняты: Minimalism & Swiss Style (сетка, иерархия, спокойные эффекты), Minimal Swiss / Inter (единая гарнитура), inline validation и фокусируемая сводка ошибок, native form controls, видимый focus, reduced motion, диаграмма долей с точными текстовыми значениями. Общий генератор предложил glassmorphism и landing-page composition — они отклонены как несоответствующие ТЗ. Запросы палитр `SaaS dashboard` и `SaaS productivity` вернули тёмную финансовую и бирюзовую палитры: они также не применены. Итоговая индиго-палитра и умеренные скругления — осознанная адаптация к пользовательскому брифу, а не неизменённый машинный вывод.

## Токены

| Роль | Значение |
| --- | --- |
| Фон | `#F7F8FC` |
| Поверхность | `#FFFFFF` |
| Основной текст | `#202637` |
| Вторичный текст | `#626B7E` |
| Primary / hover | `#4F46E5` / `#4338CA` |
| Primary soft | `#EEEDFF` |
| Разделители | `#E6E9F0` |
| Граница поля | `#A0A8B8` |
| Завершено | `#147D57` на `#EAF7F0` |
| В работе | `#4F46E5` на `#EEEDFF` |
| В очереди | `#986009` на `#FFF6E2` |
| Ошибка | `#BD344B` на `#FFF0F2` |
| Информационный | `#2563EB` на `#EFF5FF` |

Состояния всегда содержат текст: цвет и точка не являются единственным обозначением. Акцент используется для основного действия, текущей навигации и подтверждённого прогресса.

## Типографика, пространство, поверхности

- Inter Variable, локальные WOFF2 через `@fontsource-variable/inter` (OFL). Кириллица и латиница. Fallback Segoe UI / sans-serif. `font-display: swap`.
- Базовый размер 16px; заголовок страницы 30px / 26px mobile, секций 17px, основной UI 13–14px, вторичная метаинформация 11–12px. Поля mobile 16px. Небольшие системные подписи не несут единственный источник значимой информации.
- Размеры задаются в rem, масштабирование браузера не блокируется. Моноширинные UUID и Worker ID переносятся, числовые показатели tabular.
- Ритм 4 / 8 / 12 / 16 / 20 / 24 / 32 / 40 / 48px. Desktop sidebar 240px, контент 36–44px, mobile 20px.
- Карточка: белый фон, border 1px, radius 12px, padding 20–24px. Мягкая тень только для отделения поверхности. Вложенные блоки radius 8px.
- Основная кнопка: индиго, белая надпись, radius 7px, высота 42–44px. Вторичная: белая с границей. Hover меняет цвет; геометрия не скачет.
- Единые SVG иконки: viewBox 24, stroke 1.65, round caps; декоративные скрыты от screen reader. Никаких emoji.

## Компоненты и поведение

- Общие Blade-компоненты: layout, icon, page-heading, field, errors, submit, status, task-table, live-events.
- Auth использует общий layout с отдельной двухколоночной композицией, на телефоне остаётся форма и бренд.
- Формы имеют видимые labels, autocomplete, подсказки, inline errors, aria-invalid, связанный error summary с фокусом. Пароль показывается явной кнопкой; вставка и менеджеры паролей разрешены. После валидного submit кнопка блокируется и сообщает ожидание; back/forward восстанавливает состояние.
- Таблицы: спокойная шапка, тонкие разделители, статус-компакт, прогресс и читаемые идентификаторы. Mobile задачи становятся строками-карточками. CSV сохраняет семантическую таблицу с локальной прокруткой и keyboard focus.
- Пустые состояния объясняют следующий шаг. Фильтры сохраняются в URL и пагинации. Поиск не показан: backend его не поддерживает.
- Диаграмма долей: 4 статуса, точные значения и проценты рядом, ссылки на фильтры. Нулевой набор показывает «нет задач», без выдуманных процентов.
- Доступность: skip link, логичные headings, focus-visible, контраст текста 4.5:1, статусы с текстом, живые уведомления только в нужных местах.

## Мониторинг и достоверность

- Четыре этапа: Laravel / RabbitMQ / отдельный узел каждого online Worker / PostgreSQL. Реальные broker ready, unacked и consumers отдельно от PostgreSQL counts.
- Worker определяется только по backend worker_id; текущая задача — только status=processing с тем же worker_id. Никакого предварительного назначения.
- Первая загрузка показывает историческое состояние без анимации. Следующие новые task_queued / task_started / task_completed запускают соответствующий переход. Повтор события не проигрывается заново. task_progress подсвечивает только подтверждённый Worker.
- Последнее получение снимка и server_time используются для локального истечения heartbeat TTL. При ошибке polling или старом снимке нет «живых» метрик. Анимацию можно отключить. prefers-reduced-motion отключает движение.
- Прогресс только из backend. Текст и CSV без измеримого процента показывают этап выполнения. Результат появляется только после сохранения.
- Журнал рядом со схемой: фильтры ключевых событий / всех / ошибок, ссылки на задачи. Исторические Workers доступны через disclosure.

## Адаптивность и движение

- Breakpoints 1440 / 1200 / 900 / 768px. На телефоне явное раскрываемое меню и доступный logout/profile. Карточки статистики по две, контент в одну колонку.
- Схема на desktop идёт слева направо, на узких экранах перестраивается сверху вниз. Все реальные Workers остаются видны. UUID переносятся.
- Hover 180ms, прогресс transform 400ms, подтверждённый переход ~1.2s. Никаких бесконечных вымышленных пакетов, тяжёлого blur, больших градиентов или декоративных графиков.
- Проверка: desktop / mobile, переполнение страницы, клавиатура, валидация, текущие состояния, пустые и ошибочные ответы, console errors, реальные задачи во время мониторинга.
