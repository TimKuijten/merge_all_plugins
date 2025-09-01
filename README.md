# Kovacic ATS Tracker

Interface de seguimiento de candidatos con estilo Salesforce.

## Endpoints AJAX
- `kvt_get_candidates` – lista candidatos (`search`, `client`, `process`, `stage`, `page`).
- `kvt_update_status` – actualiza etapa del candidato (`id`, `status`).
- `kvt_add_task` / `kvt_complete_task` / `kvt_delete_task` – gestiona actividad.

## Hooks
- `admin_post_kvt_export` – exportación CSV/XLS.
- `kvt_daily_followup` – cron diario para recordatorios.
