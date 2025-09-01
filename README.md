# Kovacic ATS Tracker

Interface de seguimiento de candidatos con estilo Salesforce.

Al activar el plugin se crea la página **Base** (`/base`) que incluye el shortcode `[kovacic_pipeline]` para mostrar el rastreador en el frontal del sitio.

## Endpoints AJAX
- `kvt_get_candidates` – lista candidatos (`search`, `client`, `process`, `stage`, `page`).
- `kvt_update_status` – actualiza etapa del candidato (`id`, `status`).
- `kvt_add_task` / `kvt_complete_task` / `kvt_delete_task` – gestiona actividad.

## Hooks
- `admin_post_kvt_export` – exportación CSV/XLS.
- `kvt_daily_followup` – cron diario para recordatorios.
