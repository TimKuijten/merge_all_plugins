<?php
/*
Plugin Name: Kovacic Pipeline Visualizer
Description: Kanban de procesos con relación Cliente→Proceso y candidatos vinculados. Subida de CV (admin y UI), edición en tarjeta, notas, exportación CSV/XLS en orden fijo, y estados/columnas configurables.
Version: 1.7.2
Author: Tim Kuijten - Kovacic Executive Talent Research
*/

if (!defined('ABSPATH')) exit;

class Kovacic_Pipeline_Visualizer {
    const CPT           = 'kvt_candidate';
    const TAX_CLIENT    = 'kvt_client';
    const TAX_PROCESS   = 'kvt_process';
    const OPT_GROUP     = 'kvt_options';
    const OPT_STATUSES  = 'kvt_statuses';
    const OPT_COLUMNS   = 'kvt_columns';
    const OPT_OPENAI_KEY= 'kvt_openai_key';
    const OPT_OPENAI_MODEL = 'kvt_openai_model';
    const OPT_NEWS_KEY  = 'kvt_newsapi_key';
    const OPT_SMTP_HOST = 'kvt_smtp_host';
    const OPT_SMTP_PORT = 'kvt_smtp_port';
    const OPT_SMTP_USER = 'kvt_smtp_user';
    const OPT_SMTP_PASS = 'kvt_smtp_pass';
    const OPT_SMTP_SECURE = 'kvt_smtp_secure';
    const OPT_SMTP_SIGNATURE = 'kvt_smtp_signature';
    const OPT_FROM_NAME = 'kvt_from_name';
    const OPT_FROM_EMAIL = 'kvt_from_email';
    const OPT_EMAIL_LOG = 'kvt_email_log';
    const OPT_REFRESH_QUEUE = 'kvt_refresh_queue';
    const OPT_MIT_TIME = 'kvt_mit_time';
    const OPT_MIT_RECIPIENTS = 'kvt_mit_recipients';
    const OPT_MIT_FREQUENCY = 'kvt_mit_frequency';
    const OPT_MIT_DAY = 'kvt_mit_day';
    const OPT_O365_TENANT = 'kvt_o365_tenant';
    const OPT_O365_CLIENT = 'kvt_o365_client';
    const CPT_EMAIL_TEMPLATE = 'kvt_email_tpl';
    const MIT_HISTORY_LIMIT = 20;
    const MIT_TIMEOUT      = 60;

    public function __construct() {
        add_action('init',                       [$this, 'register_types']);
        add_action('admin_init',                 [$this, 'register_settings']);
        add_action('admin_menu',                 [$this, 'admin_menu']);
        add_action('admin_enqueue_scripts',      [$this, 'admin_assets']);
        add_action('phpmailer_init',             [$this, 'apply_smtp_settings']);

        // Term meta: Proceso -> Cliente
        add_action(self::TAX_PROCESS . '_add_form_fields',  [$this, 'process_add_fields']);
        add_action(self::TAX_PROCESS . '_edit_form_fields', [$this, 'process_edit_fields']);
        add_action('created_' . self::TAX_PROCESS,          [$this, 'save_process_term'], 10, 2);
        add_action('edited_'  . self::TAX_PROCESS,          [$this, 'save_process_term'], 10, 2);

        // Term meta: Cliente (contacto)
        add_action(self::TAX_CLIENT . '_add_form_fields',  [$this, 'client_add_fields']);
        add_action(self::TAX_CLIENT . '_edit_form_fields', [$this, 'client_edit_fields']);
        add_action('created_' . self::TAX_CLIENT,          [$this, 'save_client_term'], 10, 2);
        add_action('edited_'  . self::TAX_CLIENT,          [$this, 'save_client_term'], 10, 2);

        // Candidate admin UI
        add_action('add_meta_boxes',             [$this, 'add_meta_boxes']);
        add_action('save_post_' . self::CPT,     [$this, 'save_candidate_meta']);
        add_action('post_edit_form_tag',         [$this, 'form_enctype']);

        // Replace default taxonomy boxes
        add_action('admin_menu',                 [$this, 'replace_tax_metaboxes']);

        // Frontend UI
        add_shortcode('kovacic_pipeline',        [$this, 'shortcode']);
        add_shortcode('kvt_pipeline',            [$this, 'shortcode']);
        add_action('wp_enqueue_scripts',         [$this, 'enqueue_assets']);

        // AJAX
        add_action('wp_ajax_kvt_get_candidates',       [$this, 'ajax_get_candidates']);
        add_action('wp_ajax_nopriv_kvt_get_candidates',[$this, 'ajax_get_candidates']);
        add_action('wp_ajax_kvt_update_status',        [$this, 'ajax_update_status']);
        add_action('wp_ajax_nopriv_kvt_update_status', [$this, 'ajax_update_status']);
        add_action('wp_ajax_kvt_add_task',             [$this, 'ajax_add_task']);
        add_action('wp_ajax_nopriv_kvt_add_task',      [$this, 'ajax_add_task']);
        add_action('wp_ajax_kvt_complete_task',        [$this, 'ajax_complete_task']);
        add_action('wp_ajax_nopriv_kvt_complete_task', [$this, 'ajax_complete_task']);
        add_action('wp_ajax_kvt_delete_task',          [$this, 'ajax_delete_task']);
        add_action('wp_ajax_nopriv_kvt_delete_task',   [$this, 'ajax_delete_task']);
        add_action('wp_ajax_kvt_update_notes',         [$this, 'ajax_update_notes']);
        add_action('wp_ajax_nopriv_kvt_update_notes',  [$this, 'ajax_update_notes']);
        add_action('wp_ajax_kvt_delete_notes',         [$this, 'ajax_delete_notes']);
        add_action('wp_ajax_nopriv_kvt_delete_notes',  [$this, 'ajax_delete_notes']);
        add_action('wp_ajax_kvt_update_public_notes',  [$this, 'ajax_update_public_notes']);
        add_action('wp_ajax_nopriv_kvt_update_public_notes',  [$this, 'ajax_update_public_notes']);
        add_action('wp_ajax_kvt_delete_public_notes',  [$this, 'ajax_delete_public_notes']);
        add_action('wp_ajax_nopriv_kvt_delete_public_notes',  [$this, 'ajax_delete_public_notes']);
        add_action('wp_ajax_kvt_delete_candidate',     [$this, 'ajax_delete_candidate']);
        add_action('wp_ajax_nopriv_kvt_delete_candidate',[$this, 'ajax_delete_candidate']);
        add_action('wp_ajax_kvt_update_profile',       [$this, 'ajax_update_profile']);
        add_action('wp_ajax_nopriv_kvt_update_profile',[$this, 'ajax_update_profile']);
        add_action('wp_ajax_kvt_list_profiles',        [$this, 'ajax_list_profiles']);
        add_action('wp_ajax_nopriv_kvt_list_profiles', [$this, 'ajax_list_profiles']);
        add_action('wp_ajax_kvt_list_clients',         [$this, 'ajax_list_clients']);
        add_action('wp_ajax_nopriv_kvt_list_clients',  [$this, 'ajax_list_clients']);
        add_action('wp_ajax_kvt_list_processes',       [$this, 'ajax_list_processes']);
        add_action('wp_ajax_nopriv_kvt_list_processes',[$this, 'ajax_list_processes']);
        add_action('wp_ajax_kvt_clone_profile',        [$this, 'ajax_clone_profile']);
        add_action('wp_ajax_nopriv_kvt_clone_profile', [$this, 'ajax_clone_profile']);
        add_action('wp_ajax_kvt_upload_cv',            [$this, 'ajax_upload_cv']); // subir CV desde UI
        add_action('wp_ajax_nopriv_kvt_upload_cv',     [$this, 'ajax_upload_cv']);
        add_action('wp_ajax_kvt_parse_cv',             [$this, 'ajax_parse_cv']);
        add_action('wp_ajax_nopriv_kvt_parse_cv',      [$this, 'ajax_parse_cv']);
        add_action('wp_ajax_kvt_create_candidate',     [$this, 'ajax_create_candidate']);
        add_action('wp_ajax_nopriv_kvt_create_candidate',[$this, 'ajax_create_candidate']);
        add_action('wp_ajax_kvt_bulk_upload_cvs',       [$this, 'ajax_bulk_upload_cvs']);
        add_action('wp_ajax_nopriv_kvt_bulk_upload_cvs',[$this, 'ajax_bulk_upload_cvs']);
        add_action('wp_ajax_kvt_create_client',        [$this, 'ajax_create_client']);
        add_action('wp_ajax_nopriv_kvt_create_client', [$this, 'ajax_create_client']);
        add_action('wp_ajax_kvt_parse_signature',       [$this, 'ajax_parse_signature']);
        add_action('wp_ajax_nopriv_kvt_parse_signature',[$this, 'ajax_parse_signature']);
        add_action('wp_ajax_kvt_create_process',       [$this, 'ajax_create_process']);
        add_action('wp_ajax_nopriv_kvt_create_process',[$this, 'ajax_create_process']);
        add_action('wp_ajax_kvt_update_client',        [$this, 'ajax_update_client']);
        add_action('wp_ajax_nopriv_kvt_update_client', [$this, 'ajax_update_client']);
        add_action('wp_ajax_kvt_update_process',       [$this, 'ajax_update_process']);
        add_action('wp_ajax_nopriv_kvt_update_process',[$this, 'ajax_update_process']);
        add_action('wp_ajax_kvt_update_process_status',       [$this, 'ajax_update_process_status']);
        add_action('wp_ajax_nopriv_kvt_update_process_status',[$this, 'ajax_update_process_status']);
        add_action('wp_ajax_kvt_assign_candidate',     [$this, 'ajax_assign_candidate']);
        add_action('wp_ajax_nopriv_kvt_assign_candidate',[$this, 'ajax_assign_candidate']);
        add_action('wp_ajax_kvt_unassign_candidate',   [$this, 'ajax_unassign_candidate']);
        add_action('wp_ajax_nopriv_kvt_unassign_candidate',[$this, 'ajax_unassign_candidate']);
        add_action('wp_ajax_kvt_ai_search',            [$this, 'ajax_ai_search']);
        add_action('wp_ajax_nopriv_kvt_ai_search',     [$this, 'ajax_ai_search']);
        add_action('wp_ajax_kvt_keyword_search',       [$this, 'ajax_keyword_search']);
        add_action('wp_ajax_nopriv_kvt_keyword_search',[$this, 'ajax_keyword_search']);
        add_action('wp_ajax_kvt_generate_share_link',  [$this, 'ajax_generate_share_link']);
        add_action('wp_ajax_nopriv_kvt_generate_share_link',[$this,'ajax_generate_share_link']);
        add_action('wp_ajax_kvt_client_comment',       [$this, 'ajax_client_comment']);
        add_action('wp_ajax_nopriv_kvt_client_comment',[$this, 'ajax_client_comment']);
        add_action('wp_ajax_kvt_get_dashboard',        [$this, 'ajax_get_dashboard']);
        add_action('wp_ajax_nopriv_kvt_get_dashboard', [$this, 'ajax_get_dashboard']);
        add_action('wp_ajax_kvt_dismiss_comment',      [$this, 'ajax_dismiss_comment']);
        add_action('wp_ajax_nopriv_kvt_dismiss_comment',[$this, 'ajax_dismiss_comment']);
        add_action('wp_ajax_kvt_generate_roles',       [$this, 'ajax_generate_roles']);
        add_action('wp_ajax_nopriv_kvt_generate_roles',[$this, 'ajax_generate_roles']);
        add_action('wp_ajax_kvt_mit_suggestions',      [$this, 'ajax_mit_suggestions']);
        add_action('wp_ajax_kvt_mit_chat',             [$this, 'ajax_mit_chat']);
        add_action('wp_ajax_kvt_send_email',           [$this, 'ajax_send_email']);
        add_action('wp_ajax_nopriv_kvt_send_email',    [$this, 'ajax_send_email']);
        add_action('wp_ajax_kvt_get_email_log',        [$this, 'ajax_get_email_log']);
        add_action('wp_ajax_nopriv_kvt_get_email_log', [$this, 'ajax_get_email_log']);
        add_action('kvt_send_email_batch',             [$this, 'cron_send_email_batch'], 10, 1);
        add_action('wp_ajax_kvt_generate_email',       [$this, 'ajax_generate_email']);
        add_action('wp_ajax_kvt_save_template',        [$this, 'ajax_save_template']);
        add_action('wp_ajax_kvt_delete_template',      [$this, 'ajax_delete_template']);
        add_action('wp_ajax_kvt_delete_board',        [$this, 'ajax_delete_board']);
        add_action('wp_ajax_kvt_refresh_all',          [$this, 'ajax_refresh_all']);
        add_action('wp_ajax_kvt_get_outlook_events',   [$this, 'ajax_get_outlook_events']);
        add_action('wp_ajax_nopriv_kvt_get_outlook_events', [$this, 'ajax_get_outlook_events']);

        add_action('kvt_refresh_worker',               [$this, 'cron_refresh_worker']);

        // Export
        add_action('admin_post_kvt_export',          [$this, 'handle_export']);
        add_action('admin_post_kvt_delete_board',   [$this, 'handle_delete_board']);

        // Follow-up reminders
        add_action('init',                          [$this, 'schedule_followup_cron']);
        add_action('kvt_daily_followup',            [$this, 'cron_check_followups']);
        add_action('init',                          [$this, 'schedule_mit_report']);
        add_action('kvt_mit_report',                [$this, 'cron_mit_report']);
        add_action('admin_notices',                 [$this, 'followup_admin_notice']);
        add_action('template_redirect',             [$this, 'maybe_redirect_share_link']);

        add_action('plugins_loaded',                 [$this, 'ensure_defaults']);
    }

    public function ensure_defaults() {
        if (get_option(self::OPT_STATUSES) === false) {
            update_option(self::OPT_STATUSES, "Identified\nContacted\nInterviewed\nOffer\nDeclined");
        }
        if (get_option(self::OPT_COLUMNS) === false) {
            update_option(self::OPT_COLUMNS,
"first_name|Nombre
last_name|Apellidos
email|Email
phone|Teléfono
country|País
city|Ciudad
current_role|Puesto actual
cv_url|CV (URL)
cv_uploaded|Fecha de subida");
        }
    }

    public static function activate() {
        if (!get_page_by_path('base')) {
            wp_insert_post([
                'post_title'   => 'Base',
                'post_name'    => 'base',
                'post_content' => '[kvt_pipeline]',
                'post_status'  => 'publish',
                'post_type'    => 'page',
            ]);
        }
        if (!wp_next_scheduled('kvt_mit_report')) {
            $tz   = new DateTimeZone('Europe/Madrid');
            $time = get_option(self::OPT_MIT_TIME, '07:45');
            if (!preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', $time)) {
                $time = '07:45';
            }
            list($hour, $min) = array_map('intval', explode(':', $time));
            $next = new DateTime('now', $tz);
            $next->setTime($hour, $min);
            if ($next->getTimestamp() <= time()) {
                $next->modify('+1 day');
            }
            wp_schedule_event($next->getTimestamp(), 'daily', 'kvt_mit_report');
        }
    }

    /* Types & Taxonomies */
    public function register_types() {
        register_post_type(self::CPT, [
            'labels' => [
                'name' => 'Candidatos',
                'singular_name' => 'Candidato',
                'add_new_item' => 'Añadir candidato',
                'edit_item' => 'Editar candidato',
                'view_item' => 'Ver candidato',
                'search_items' => 'Buscar candidatos',
            ],
            'public' => false,
            'show_ui' => true,
            'supports' => ['title'],
            'menu_icon' => 'dashicons-groups',
        ]);

        register_post_type(self::CPT_EMAIL_TEMPLATE, [
            'public'      => false,
            'show_ui'     => false,
            'supports'    => ['title'],
            'capability_type' => 'post',
        ]);

        register_taxonomy(self::TAX_CLIENT, [self::CPT], [
            'labels' => ['name' => 'Clientes','singular_name' => 'Cliente'],
            'public' => false,'show_ui' => true,'hierarchical' => false,
            'meta_box_cb' => null,
        ]);

        register_taxonomy(self::TAX_PROCESS, [self::CPT], [
            'labels' => ['name' => 'Procesos','singular_name' => 'Proceso'],
            'public' => false,'show_ui' => true,'hierarchical' => false,
            'meta_box_cb' => null,
        ]);
    }

    /* Settings page */
    public function register_settings() {
        register_setting(self::OPT_GROUP, self::OPT_STATUSES);
        register_setting(self::OPT_GROUP, self::OPT_COLUMNS);
        register_setting(self::OPT_GROUP, self::OPT_OPENAI_KEY);
        register_setting(self::OPT_GROUP, self::OPT_OPENAI_MODEL);
        register_setting(self::OPT_GROUP, self::OPT_NEWS_KEY);
        register_setting(self::OPT_GROUP, self::OPT_SMTP_HOST);
        register_setting(self::OPT_GROUP, self::OPT_SMTP_PORT);
        register_setting(self::OPT_GROUP, self::OPT_SMTP_USER);
        register_setting(self::OPT_GROUP, self::OPT_SMTP_PASS);
        register_setting(self::OPT_GROUP, self::OPT_SMTP_SECURE);
        register_setting(self::OPT_GROUP, self::OPT_SMTP_SIGNATURE);
        register_setting(self::OPT_GROUP, self::OPT_FROM_NAME);
        register_setting(self::OPT_GROUP, self::OPT_FROM_EMAIL);
        register_setting(self::OPT_GROUP, self::OPT_O365_TENANT);
        register_setting(self::OPT_GROUP, self::OPT_O365_CLIENT);
        register_setting(self::OPT_GROUP, self::OPT_EMAIL_LOG, [
            'type'    => 'array',
            'default' => [],
        ]);
        register_setting(self::OPT_GROUP, self::OPT_MIT_TIME);
        register_setting(self::OPT_GROUP, self::OPT_MIT_RECIPIENTS);
        register_setting(self::OPT_GROUP, self::OPT_MIT_FREQUENCY);
        register_setting(self::OPT_GROUP, self::OPT_MIT_DAY);
    }
    public function admin_menu() {
        global $admin_page_hooks;
        if (!isset($admin_page_hooks['kovacic'])) {
            add_menu_page('Kovacic', 'Kovacic', 'manage_options', 'kovacic', '__return_null', 'dashicons-businessman', 3);
        }
        add_submenu_page('kovacic', __('ATS', 'kovacic'), __('ATS', 'kovacic'), 'manage_options', 'kvt-tracker', [$this, 'tracker_page']);
        add_submenu_page('kovacic', __('Ajustes', 'kovacic'), __('Ajustes', 'kovacic'), 'manage_options', 'kvt-settings', [$this, 'settings_page']);
        add_submenu_page('kovacic', __('Tableros de candidatos/clientes', 'kovacic'), __('Tableros de candidatos/clientes', 'kovacic'), 'manage_options', 'kvt-boards', [$this, 'boards_page']);
        add_submenu_page('kovacic', __('Actualizar perfiles', 'kovacic'), __('Actualizar perfiles', 'kovacic'), 'manage_options', 'kvt-load-cv', [$this, 'load_cv_page']);
    }

    public function tracker_page() {
        $edit_slug = isset($_GET['edit_board']) ? sanitize_text_field($_GET['edit_board']) : '';
        if ($edit_slug) {
            $links = [
                'client'    => get_option('kvt_client_links', []),
                'candidate' => get_option('kvt_candidate_links', []),
            ];
            wp_add_inline_script('kvt-tracker', 'const KVT_BOARD_LINKS='.wp_json_encode($links).';const KVT_EDIT_BOARD="'.esc_js($edit_slug).'";', 'before');
        }
        ?>
        <div class="wrap kcvf">
          <header class="k-header">
            <div>
              <h1 class="k-title"><?php esc_html_e('Seguimiento de Candidatos', 'kovacic'); ?></h1>
              <span class="k-pill k-pill--blue"><?php esc_html_e('Etapa actual', 'kovacic'); ?></span>
            </div>
            <div class="k-badges">
              <span class="k-pill"><?php esc_html_e('Lista larga', 'kovacic'); ?>: <span id="stat-long">0</span></span>
              <span class="k-pill k-pill--green"><?php esc_html_e('Entrevistas', 'kovacic'); ?>: <span id="stat-interviews">0</span></span>
              <span class="k-pill k-pill--blue"><?php esc_html_e('Ofertas', 'kovacic'); ?>: <span id="stat-offers">0</span></span>
            </div>
          </header>
          <nav class="k-tabs" role="tablist">
            <div class="k-tab" aria-selected="false"><?php esc_html_e('Detalles', 'kovacic'); ?></div>
            <div class="k-tab" aria-selected="true"><?php esc_html_e('ATS', 'kovacic'); ?></div>
            <div class="k-tab" aria-selected="false"><?php esc_html_e('Agenda de entrevistas', 'kovacic'); ?></div>
            <div class="k-tab" aria-selected="false"><?php esc_html_e('Contrataciones', 'kovacic'); ?></div>
            <div class="k-tab" aria-selected="false"><?php esc_html_e('Notas', 'kovacic'); ?></div>
            <div class="k-tab" aria-selected="false"><?php esc_html_e('Candidaturas', 'kovacic'); ?></div>
          </nav>
          <section class="k-tabpanel">
            <div class="k-filters">
              <input type="search" id="k-search" class="k-input" placeholder="<?php esc_attr_e('Buscar', 'kovacic'); ?>">
              <select id="k-filter-client" class="k-select"><option value=""><?php esc_html_e('Cliente', 'kovacic'); ?></option></select>
              <select id="k-filter-process" class="k-select"><option value=""><?php esc_html_e('Proceso', 'kovacic'); ?></option></select>
              <select id="k-filter-stage" class="k-select"><option value=""><?php esc_html_e('Etapa', 'kovacic'); ?></option></select>
              <button class="btn k-activity-toggle" id="k-toggle-activity"><?php esc_html_e('Actividad', 'kovacic'); ?></button>
            </div>
            <div class="k-bulkbar" id="k-bulkbar" hidden>
              <div class="k-bulkactions">
                <button class="btn"><?php esc_html_e('Mover etapa', 'kovacic'); ?></button>
                <button class="btn"><?php esc_html_e('Enviar correo', 'kovacic'); ?></button>
                <button class="btn"><?php esc_html_e('Exportar CSV', 'kovacic'); ?></button>
                <button class="btn"><?php esc_html_e('Eliminar', 'kovacic'); ?></button>
              </div>
            </div>
            <div class="k-layout">
              <div id="k-client-process" class="k-client-process"></div>
              <button class="btn" id="k-add-candidate" style="display:none;"><?php esc_html_e('Añadir candidato', 'kovacic'); ?></button>
              <div class="k-tablewrap">
                  <table class="k-table" aria-describedby="k-page">
                    <thead>
                      <tr>
                        <th class="checkbox"><input type="checkbox" id="k-select-all" aria-label="<?php esc_attr_e('Seleccionar todos', 'kovacic'); ?>"></th>
                        <th class="sortable" data-sort="candidate" aria-sort="none"><?php esc_html_e('Candidato', 'kovacic'); ?></th>
                        <th class="sortable" data-sort="int_no" aria-sort="none"><?php esc_html_e('No. Ent.', 'kovacic'); ?></th>
                        <th><?php esc_html_e('Etapa actual', 'kovacic'); ?></th>
                        <th><?php esc_html_e('Acciones', 'kovacic'); ?></th>
                      </tr>
                    </thead>
                    <tbody id="k-rows"></tbody>
                  </table>
                </div>
              <div class="k-pager">
                <button class="btn" id="k-prev"><?php esc_html_e('Anterior', 'kovacic'); ?></button>
                <span id="k-page"></span>
                <button class="btn" id="k-next"><?php esc_html_e('Siguiente', 'kovacic'); ?></button>
              </div>
              <div class="k-sidebar" id="k-sidebar">
                <div class="k-sidehead"><?php esc_html_e('Actividad', 'kovacic'); ?></div>
                <div class="k-activity" id="k-activity-feed"></div>
                <div class="k-sideactions">
                  <button class="btn btn--primary" id="k-log-call"><?php esc_html_e('Registrar llamada', 'kovacic'); ?></button>
                  <button class="btn" id="k-new-event"><?php esc_html_e('Nuevo evento', 'kovacic'); ?></button>
                  <button class="btn" id="k-new-task"><?php esc_html_e('Nueva tarea', 'kovacic'); ?></button>
                </div>
              </div>
            </div>
          </section>
        </div>
        <?php
    }

    public function admin_assets($hook) {
        if (strpos($hook, 'kvt-tracker') === false) return;

        $css = <<<'CSS'
/* Kovacic ATS UI – namespaced to avoid bleed */
:root{
  --brand:#0A212E;           /* ink/brand */
  --blue:#0176D3;            /* primary action */
  --green:#2E844A;           /* success */
  --amber:#F5821F;           /* warning */
  --red:#BA0517;             /* danger */
  --bg:#F3F6F9;              /* app background */
  --surface:#FFFFFF;         /* cards/tables */
  --ink:#1F2937;             /* body text */
  --ink-muted:#6B7280;       /* secondary text */
  --divider:#E5E7EB;         /* borders */
  --shadow:0 2px 6px rgba(0,0,0,.08);
  --radius:8px;
  --gap:8px;                 /* 8px spacing grid */
}

.kcvf *{box-sizing:border-box}
.kcvf{
  font-family: "Salesforce Sans", -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;
  color:var(--ink);
  background:var(--bg);
  line-height:1.5;
  font-size:14px;
}

/* Page shell */
.kcvf .wrap{
  max-width:1200px;
  margin:0 auto;
  padding:calc(var(--gap)*3);
}

/* Top header */
.kcvf .k-header{
  background:var(--surface);
  border:1px solid var(--divider);
  border-radius:var(--radius);
  box-shadow:var(--shadow);
  padding:calc(var(--gap)*2);
  margin-bottom:calc(var(--gap)*2);
  display:grid;
  grid-template-columns: 1fr auto;
  gap:calc(var(--gap)*2);
}
.kcvf .k-title{font-size:18px;font-weight:600}
.kcvf .k-badges{display:flex;flex-wrap:wrap;gap:var(--gap)}
.kcvf .k-pill{
  display:inline-flex;align-items:center;gap:6px;
  padding:4px 10px;border-radius:999px;border:1px solid var(--divider);
  background:#fafafa;font-weight:500
}
.kcvf .k-pill--blue{background:rgba(1,118,211,.08);border-color:rgba(1,118,211,.25);color:#045FA3}
.kcvf .k-pill--green{background:rgba(46,132,74,.10);border-color:rgba(46,132,74,.25);color:#1E5C38}

/* Tabs */
.kcvf .k-tabs{display:flex;gap:2px;margin-bottom:calc(var(--gap)*2)}
.kcvf .k-tab{
  background:var(--surface);padding:10px 14px;border:1px solid var(--divider);
  border-bottom:none;border-top-left-radius:var(--radius);border-top-right-radius:var(--radius);
  color:var(--ink-muted);cursor:pointer
}
.kcvf .k-tab[aria-selected="true"]{color:var(--brand);font-weight:600;box-shadow:inset 0 -3px 0 var(--blue)}
.kcvf .k-tabpanel{
  background:var(--surface);border:1px solid var(--divider);border-radius:0 0 var(--radius) var(--radius);
  box-shadow:var(--shadow);padding:calc(var(--gap)*2)
}

/* Filters */
.kcvf .k-filters{
  display:flex;flex-wrap:wrap;gap:var(--gap);margin-bottom:calc(var(--gap)*2)
}
.kcvf .k-input,.kcvf .k-select{
  height:36px;padding:0 10px;border:1px solid var(--divider);border-radius:6px;background:#fff
}

/* Bulk bar */
.kcvf .k-bulkbar{
  display:flex;align-items:center;gap:var(--gap);justify-content:space-between;
  padding:8px 12px;background:#fff;border:1px solid var(--divider);
  border-radius:6px;margin-bottom:calc(var(--gap)*1.5)
}

/* Table */
.kcvf .k-tablewrap{position:relative;overflow:auto;border:1px solid var(--divider);border-radius:var(--radius);box-shadow:var(--shadow)}
.kcvf .k-client-process{font-weight:600;margin-bottom:8px}
.kcvf .k-cv-icon{margin-left:4px;text-decoration:none}
.kcvf table{width:100%;border-collapse:separate;border-spacing:0;background:var(--surface)}
.kcvf thead th{
  position:sticky;top:0;background:#f9fbfd;border-bottom:1px solid var(--divider);
  text-align:left;font-weight:600;padding:12px
}
.kcvf tbody td{border-top:1px solid var(--divider);padding:12px;vertical-align:middle}
.kcvf .sortable{cursor:pointer}
.kcvf .checkbox{width:36px;text-align:center}

/* Progress pill (3 steps) */
.kcvf .k-progress{display:inline-flex;gap:4px;align-items:center}
.kcvf .k-step{
  width:18px;height:8px;border-radius:999px;background:#E5E7EB
}
.kcvf .k-step.is-done{background:var(--green)}
.kcvf .k-step.is-current{background:var(--blue)}

/* Buttons */
.kcvf .btn{
  display:inline-flex;align-items:center;gap:8px;height:34px;padding:0 12px;border-radius:8px;border:1px solid var(--divider);
  background:#fff;cursor:pointer;font-weight:600
}
.kcvf .btn--primary{background:var(--blue);border-color:transparent;color:#fff}
.kcvf .btn--ghost{background:#fff;color:var(--brand)}
.kcvf .btn:focus{outline:2px solid rgba(1,118,211,.35);outline-offset:2px}

/* Sidebar */
.kcvf .k-layout{display:grid;grid-template-columns:1fr;gap:calc(var(--gap)*2)}
.kcvf .k-sidebar{
  background:var(--surface);border:1px solid var(--divider);border-radius:var(--radius);box-shadow:var(--shadow);
  padding:calc(var(--gap)*2)
}
.kcvf .k-sidehead{font-weight:700;margin-bottom:var(--gap)}
.kcvf .k-activity{display:grid;gap:10px;max-height:520px;overflow:auto}
.kcvf .k-activity-toggle{display:none}

/* Pagination */
.kcvf .k-pager{display:flex;justify-content:space-between;align-items:center;margin-top:calc(var(--gap)*2)}

/* Chips (statuses) */
.kcvf .chip{display:inline-flex;align-items:center;padding:4px 10px;border-radius:999px;border:1px solid var(--divider);gap:6px}
.kcvf .chip--rejected{background:#F3F4F6;color:#374151}

/* Modal (quick view) */
.kcvf .k-modal{position:fixed;inset:0;display:none;align-items:center;justify-content:center;background:rgba(0,0,0,.35);z-index:1000}
.kcvf .k-modal.is-open{display:flex}
.kcvf .k-dialog{width:min(720px,92vw);background:#fff;border-radius:12px;box-shadow:0 12px 32px rgba(0,0,0,.25);padding:20px}

/* Responsive */
@media (max-width: 960px){
  .kcvf .k-layout{grid-template-columns:1fr}
  .kcvf .k-activity-toggle{display:none}
  .kcvf .k-sidebar{order:0}
  .kcvf thead{display:none}
  .kcvf table,.kcvf tbody,.kcvf tr,.kcvf td{display:block;width:100%}
  .kcvf tbody tr{border-top:1px solid var(--divider);padding:8px 12px}
  .kcvf tbody td{border:none;padding:6px 0}
  .kcvf .checkbox{position:absolute;right:12px;top:12px}
}
CSS;
        wp_register_style('kvt-tracker', false);
        wp_enqueue_style('kvt-tracker');
        wp_add_inline_style('kvt-tracker', $css);

        $js = <<<'JS'
(function(){
  const state = {page:1, search:'', client:'', process:'', stage:''};
  const tbody = document.getElementById('k-rows');
  const pager = document.getElementById('k-page');

  function statusStep(status){
    if(!status) return 1;
    status = status.toLowerCase();
    if(status.includes('reject')) return 'rejected';
    const step1 = ['identificado','contactado','lista larga','long list'];
    const step2 = ['entrevista','shortlist'];
    const step3 = ['oferta','placement','colocado'];
    if(step3.some(s=>status.includes(s))) return 3;
    if(step2.some(s=>status.includes(s))) return 2;
    return 1;
  }

  function renderRows(items){
    tbody.innerHTML='';
    const cpEl = document.getElementById('k-client-process');
    if(CLIENT_VIEW && !CANDIDATE_VIEW && cpEl){
      if(items.length){
        cpEl.textContent = 'Cliente: '+(items[0].meta.client||'')+' — Proceso: '+(items[0].meta.process||'');
        cpEl.style.display='block';
      }else{
        cpEl.style.display='none';
      }
    } else if (cpEl){
      cpEl.style.display='none';
    }
    items.forEach(item=>{
      const tr=document.createElement('tr');
      const cb=document.createElement('td');
      cb.className='checkbox';
      cb.innerHTML='<input type="checkbox" class="k-rowcheck" value="'+item.id+'">';
      const name=document.createElement('td');
      const cv = item.meta.cv_url?'<a href="'+item.meta.cv_url+'" target="_blank" class="k-cv-icon" title="CV">\u{1F4C4}</a>':'';
      name.innerHTML='<a href="#" class="k-candidate" data-id="'+item.id+'">'+item.meta.first_name+' '+item.meta.last_name+'</a>'+cv;
      const intNo=document.createElement('td');
      intNo.textContent=item.meta.int_no||'';
      const stage=document.createElement('td');
      const step=statusStep(item.status);
      if(step==='rejected'){
        stage.innerHTML='<span class="chip chip--rejected">Rechazado</span>';
      }else{
        stage.innerHTML='<span class="k-progress">'
          +'<span class="k-step'+(step>=1?' is-done':'')+'"></span>'
          +'<span class="k-step'+(step>=2?' is-done':'')+(step===2?' is-current':'')+'"></span>'
          +'<span class="k-step'+(step>=3?' is-done':'')+(step===3?' is-current':'')+'"></span>'
          +'</span>';
      }
      const actions=document.createElement('td');
      actions.innerHTML='<button class="btn btn--ghost">Ver</button>';
      tr.append(cb,name,intNo,stage,actions);
      tbody.appendChild(tr);
    });
  }

  function fetchData(){
    const params=new URLSearchParams({
      action:'kvt_get_candidates',
      _ajax_nonce:KVT.nonce,
      search:state.search,
      client:state.client,
      process:state.process,
      stage:state.stage,
      page:state.page
    });
    fetch(KVT.ajaxurl,{method:'POST',body:params}).then(r=>r.json()).then(res=>{
      if(res.success){
        renderRows(res.data.items);
        pager.textContent=state.page+' / '+res.data.pages;
      }
    });
  }

  let to;
  const searchInput=document.getElementById('k-search');
  if(searchInput){
    searchInput.addEventListener('input',e=>{
      state.search=e.target.value;
      clearTimeout(to);
      to=setTimeout(()=>{state.page=1;fetchData();},300);
    });
  }

  document.getElementById('k-prev').addEventListener('click',()=>{
    if(state.page>1){state.page--;fetchData();}
  });
  document.getElementById('k-next').addEventListener('click',()=>{
    state.page++;fetchData();
  });

  ['k-filter-client','k-filter-process','k-filter-stage'].forEach(id=>{
    const el=document.getElementById(id);
    if(el){
      el.addEventListener('change',e=>{
        state[id.replace('k-filter-','')] = e.target.value;
        state.page=1;fetchData();
      });
    }
  });

  const toggle=document.getElementById('k-toggle-activity');
  if(toggle){
    toggle.addEventListener('click',()=>{
      document.getElementById('k-sidebar').classList.toggle('is-open');
    });
  }

  fetchData();
})();
JS;
        wp_register_script('kvt-tracker', '', [], false, true);
        wp_enqueue_script('kvt-tracker');
        wp_localize_script('kvt-tracker', 'KVT', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('kvt_nonce'),
        ]);
        wp_add_inline_script('kvt-tracker', $js);
    }
    public function settings_page() {
        if (isset($_POST['kvt_mit_send_now']) && check_admin_referer('kvt_mit_send_now')) {
            $this->cron_mit_report();
            echo '<div class="notice notice-success"><p>Informe MIT enviado.</p></div>';
        }
        if (isset($_POST['kvt_run_composer']) && check_admin_referer('kvt_run_composer')) {
            if (function_exists('shell_exec')) {
                $cmd = 'cd ' . escapeshellarg(__DIR__) . ' && composer install 2>&1';
                $output = shell_exec($cmd);
                if ($output !== null) {
                    echo '<div class="notice notice-success"><pre>' . esc_html($output) . '</pre></div>';
                } else {
                    echo '<div class="notice notice-error"><p>No se pudo ejecutar composer.</p></div>';
                }
            } else {
                echo '<div class="notice notice-error"><p>shell_exec deshabilitado; no se puede ejecutar composer.</p></div>';
            }
        }
        $statuses = get_option(self::OPT_STATUSES, "");
        $columns  = get_option(self::OPT_COLUMNS, "");
        $openai   = get_option(self::OPT_OPENAI_KEY, "");
        $openai_model = get_option(self::OPT_OPENAI_MODEL, 'gpt-5');
        $newskey  = get_option(self::OPT_NEWS_KEY, "");
        $smtp_host = get_option(self::OPT_SMTP_HOST, "");
        $smtp_port = get_option(self::OPT_SMTP_PORT, "");
        $smtp_user = get_option(self::OPT_SMTP_USER, "");
        $smtp_pass = get_option(self::OPT_SMTP_PASS, "");
        $smtp_secure = get_option(self::OPT_SMTP_SECURE, "");
        $smtp_sig  = get_option(self::OPT_SMTP_SIGNATURE, "");
        $o365_tenant = get_option(self::OPT_O365_TENANT, "");
        $o365_client = get_option(self::OPT_O365_CLIENT, "");
        $from_name_def = get_option(self::OPT_FROM_NAME, "");
        $from_email_def = get_option(self::OPT_FROM_EMAIL, "");
        $mit_time = get_option(self::OPT_MIT_TIME, '09:00');
        $mit_recipients = get_option(self::OPT_MIT_RECIPIENTS, get_option('admin_email'));
        $mit_freq = get_option(self::OPT_MIT_FREQUENCY, 'weekly');
        $mit_day = get_option(self::OPT_MIT_DAY, 'monday');
        ?>
        <div class="wrap">
            <h1>Kovacic Pipeline — Ajustes</h1>
            <form method="post" action="options.php">
                <?php settings_fields(self::OPT_GROUP); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="<?php echo self::OPT_STATUSES; ?>">Estados del pipeline</label></th>
                        <td>
                            <textarea name="<?php echo self::OPT_STATUSES; ?>" id="<?php echo self::OPT_STATUSES; ?>" rows="8" class="large-text" placeholder="Un estado por línea, de izquierda a derecha en el tablero"><?php echo esc_textarea($statuses); ?></textarea>
                            <p class="description">Ejemplo (uno por línea): Identified, Contacted, Interviewed, Offer, Declined</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="<?php echo self::OPT_OPENAI_KEY; ?>">OpenAI API Key</label></th>
                        <td>
                            <input type="text" name="<?php echo self::OPT_OPENAI_KEY; ?>" id="<?php echo self::OPT_OPENAI_KEY; ?>" class="regular-text" value="<?php echo esc_attr($openai); ?>">
                            <p class="description">Clave utilizada para las búsquedas avanzadas.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="<?php echo self::OPT_OPENAI_MODEL; ?>">Modelo OpenAI</label></th>
                        <td>
                            <select name="<?php echo self::OPT_OPENAI_MODEL; ?>" id="<?php echo self::OPT_OPENAI_MODEL; ?>">
                                <?php foreach (['gpt-5', 'gpt-4.1-mini'] as $m): ?>
                                    <option value="<?php echo esc_attr($m); ?>" <?php selected($openai_model, $m); ?>><?php echo esc_html($m); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">Modelo utilizado por MIT. Por defecto gpt-5.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="<?php echo self::OPT_NEWS_KEY; ?>">News API Key</label></th>
                        <td>
                            <input type="text" name="<?php echo self::OPT_NEWS_KEY; ?>" id="<?php echo self::OPT_NEWS_KEY; ?>" class="regular-text" value="<?php echo esc_attr($newskey); ?>">
                            <p class="description">Clave para obtener noticias del sector de energía renovable.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="<?php echo self::OPT_COLUMNS; ?>">Columnas de datos (tabla/exportación)</label></th>
                        <td>
                            <textarea name="<?php echo self::OPT_COLUMNS; ?>" id="<?php echo self::OPT_COLUMNS; ?>" rows="10" class="large-text" placeholder="meta_key|Etiqueta visible"><?php echo esc_textarea($columns); ?></textarea>
                            <p class="description">
                                Formato: <code>meta_key|Etiqueta</code> (una por línea). Por defecto: <code>first_name, last_name, email, phone, country, city, cv_url, cv_uploaded</code>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="<?php echo self::OPT_SMTP_HOST; ?>">SMTP Host</label></th>
                        <td><input type="text" name="<?php echo self::OPT_SMTP_HOST; ?>" id="<?php echo self::OPT_SMTP_HOST; ?>" class="regular-text" value="<?php echo esc_attr($smtp_host); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="<?php echo self::OPT_SMTP_PORT; ?>">SMTP Port</label></th>
                        <td><input type="number" name="<?php echo self::OPT_SMTP_PORT; ?>" id="<?php echo self::OPT_SMTP_PORT; ?>" class="small-text" value="<?php echo esc_attr($smtp_port); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="<?php echo self::OPT_SMTP_USER; ?>">SMTP User</label></th>
                        <td><input type="text" name="<?php echo self::OPT_SMTP_USER; ?>" id="<?php echo self::OPT_SMTP_USER; ?>" class="regular-text" value="<?php echo esc_attr($smtp_user); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="<?php echo self::OPT_SMTP_PASS; ?>">SMTP Password</label></th>
                        <td><input type="password" name="<?php echo self::OPT_SMTP_PASS; ?>" id="<?php echo self::OPT_SMTP_PASS; ?>" class="regular-text" value="<?php echo esc_attr($smtp_pass); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="<?php echo self::OPT_SMTP_SECURE; ?>">SMTP Security</label></th>
                        <td>
                            <select name="<?php echo self::OPT_SMTP_SECURE; ?>" id="<?php echo self::OPT_SMTP_SECURE; ?>">
                                <option value="" <?php selected($smtp_secure, ''); ?>><?php esc_html_e('None'); ?></option>
                                <option value="ssl" <?php selected($smtp_secure, 'ssl'); ?>>SSL</option>
                                <option value="tls" <?php selected($smtp_secure, 'tls'); ?>>TLS</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="<?php echo self::OPT_SMTP_SIGNATURE; ?>">Firma de correo</label></th>
                        <td>
                            <textarea name="<?php echo self::OPT_SMTP_SIGNATURE; ?>" id="<?php echo self::OPT_SMTP_SIGNATURE; ?>" rows="4" class="large-text"><?php echo esc_textarea($smtp_sig); ?></textarea>
                            <p class="description">Se añadirá al final de cada e-mail.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="<?php echo self::OPT_O365_TENANT; ?>">Outlook Tenant ID</label></th>
                        <td><input type="text" name="<?php echo self::OPT_O365_TENANT; ?>" id="<?php echo self::OPT_O365_TENANT; ?>" class="regular-text" value="<?php echo esc_attr($o365_tenant); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="<?php echo self::OPT_O365_CLIENT; ?>">Outlook Client ID</label></th>
                        <td><input type="text" name="<?php echo self::OPT_O365_CLIENT; ?>" id="<?php echo self::OPT_O365_CLIENT; ?>" class="regular-text" value="<?php echo esc_attr($o365_client); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="<?php echo self::OPT_FROM_NAME; ?>">Nombre remitente por defecto</label></th>
                        <td><input type="text" name="<?php echo self::OPT_FROM_NAME; ?>" id="<?php echo self::OPT_FROM_NAME; ?>" class="regular-text" value="<?php echo esc_attr($from_name_def); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="<?php echo self::OPT_FROM_EMAIL; ?>">Email remitente por defecto</label></th>
                        <td><input type="email" name="<?php echo self::OPT_FROM_EMAIL; ?>" id="<?php echo self::OPT_FROM_EMAIL; ?>" class="regular-text" value="<?php echo esc_attr($from_email_def); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="<?php echo self::OPT_MIT_FREQUENCY; ?>">Frecuencia informe MIT</label></th>
                        <td>
                            <select name="<?php echo self::OPT_MIT_FREQUENCY; ?>" id="<?php echo self::OPT_MIT_FREQUENCY; ?>">
                                <option value="daily" <?php selected($mit_freq, 'daily'); ?>>Diaria</option>
                                <option value="weekly" <?php selected($mit_freq, 'weekly'); ?>>Semanal</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="<?php echo self::OPT_MIT_DAY; ?>">Día informe MIT</label></th>
                        <td>
                            <select name="<?php echo self::OPT_MIT_DAY; ?>" id="<?php echo self::OPT_MIT_DAY; ?>">
                                <?php foreach ([
                                    'monday' => 'Lunes',
                                    'tuesday' => 'Martes',
                                    'wednesday' => 'Miércoles',
                                    'thursday' => 'Jueves',
                                    'friday' => 'Viernes',
                                    'saturday' => 'Sábado',
                                    'sunday' => 'Domingo',
                                ] as $k => $v): ?>
                                    <option value="<?php echo esc_attr($k); ?>" <?php selected($mit_day, $k); ?>><?php echo esc_html($v); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">Día de envío para informes semanales.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="<?php echo self::OPT_MIT_TIME; ?>">Hora informe MIT</label></th>
                        <td>
                            <input type="time" name="<?php echo self::OPT_MIT_TIME; ?>" id="<?php echo self::OPT_MIT_TIME; ?>" value="<?php echo esc_attr($mit_time); ?>">
                            <p class="description">Hora de envío (zona Madrid).</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="<?php echo self::OPT_MIT_RECIPIENTS; ?>">Emails informe MIT</label></th>
                        <td>
                            <input type="text" name="<?php echo self::OPT_MIT_RECIPIENTS; ?>" id="<?php echo self::OPT_MIT_RECIPIENTS; ?>" class="regular-text" value="<?php echo esc_attr($mit_recipients); ?>">
                            <p class="description">Direcciones separadas por comas.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Guardar ajustes'); ?>
            </form>
            <form method="post">
                <?php wp_nonce_field('kvt_mit_send_now'); ?>
                <?php submit_button('Enviar informe ahora', 'secondary', 'kvt_mit_send_now'); ?>
            </form>
            <form method="post">
                <?php wp_nonce_field('kvt_run_composer'); ?>
                <?php submit_button('Run Composer', 'secondary', 'kvt_run_composer'); ?>
            </form>
        </div>
        <?php
    }

    public function boards_page() {
        $client_links    = get_option('kvt_client_links', []);
        $candidate_links = get_option('kvt_candidate_links', []);
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Tableros de candidatos/clientes', 'kovacic'); ?></h1>
            <table class="widefat">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Type', 'kovacic'); ?></th>
                        <th><?php esc_html_e('Client', 'kovacic'); ?></th>
                        <th><?php esc_html_e('Process', 'kovacic'); ?></th>
                        <th><?php esc_html_e('Candidate', 'kovacic'); ?></th>
                        <th><?php esc_html_e('URL', 'kovacic'); ?></th>
                        <th><?php esc_html_e('Actions', 'kovacic'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php
                if (empty($client_links) && empty($candidate_links)) {
                    echo '<tr><td colspan="6">'.esc_html__('No boards found', 'kovacic').'</td></tr>';
                }
                foreach ($client_links as $slug => $cfg) {
                    $client  = get_term_field('name', $cfg['client'], self::TAX_CLIENT);
                    $process = get_term_field('name', $cfg['process'], self::TAX_PROCESS);
                    $url     = home_url('/view-board/'.$slug.'/');
                    $edit    = admin_url('admin.php?page=kvt-tracker&edit_board='.$slug);
                    $del     = wp_nonce_url(admin_url('admin-post.php?action=kvt_delete_board&type=client&slug='.$slug), 'kvt_delete_board');
                    echo '<tr><td>Client</td><td>'.esc_html($client).'</td><td>'.esc_html($process).'</td><td>—</td><td><a href="'.esc_url($url).'" target="_blank">'.esc_html($slug).'</a></td><td><a href="'.esc_url($edit).'">Edit</a> | <a href="'.esc_url($del).'">Delete</a></td></tr>';
                }
                foreach ($candidate_links as $slug => $cfg) {
                    $client  = get_term_field('name', $cfg['client'], self::TAX_CLIENT);
                    $process = get_term_field('name', $cfg['process'], self::TAX_PROCESS);
                    $cand    = get_the_title($cfg['candidate']);
                    $url     = home_url('/view-board/'.$slug.'/');
                    $edit    = admin_url('admin.php?page=kvt-tracker&edit_board='.$slug);
                    $del     = wp_nonce_url(admin_url('admin-post.php?action=kvt_delete_board&type=candidate&slug='.$slug), 'kvt_delete_board');
                    echo '<tr><td>Candidate</td><td>'.esc_html($client).'</td><td>'.esc_html($process).'</td><td>'.esc_html($cand).'</td><td><a href="'.esc_url($url).'" target="_blank">'.esc_html($slug).'</a></td><td><a href="'.esc_url($edit).'">Edit</a> | <a href="'.esc_url($del).'">Delete</a></td></tr>';
                }
                ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function load_cv_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Actualizar perfiles desde CVs', 'kovacic'); ?></h1>
            <p><?php esc_html_e('Lee todos los CVs en texto y completa los campos vacíos del perfil.', 'kovacic'); ?></p>
            <button id="kvt_load_cv_btn" class="button button-primary"><?php esc_html_e('Cargar CVs', 'kovacic'); ?></button>
        </div>
        <script>
        document.addEventListener('DOMContentLoaded',function(){
            var btn=document.getElementById('kvt_load_cv_btn');
            if(!btn) return;
            btn.addEventListener('click',function(){
                btn.disabled=true;
                fetch(ajaxurl,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({action:'kvt_generate_roles',_ajax_nonce:'<?php echo wp_create_nonce('kvt_nonce'); ?>'}).toString()})
                    .then(r=>r.json())
                    .then(function(res){
                        btn.disabled=false;
                        if(res && res.success){
                            alert('<?php echo esc_js(__('Perfiles actualizados.', 'kovacic')); ?>');
                        } else {
                            var msg=res && res.data && res.data.msg ? res.data.msg : '<?php echo esc_js(__('No se pudieron actualizar los perfiles.', 'kovacic')); ?>';
                            alert(msg);
                        }
                    })
                    .catch(function(){
                        btn.disabled=false;
                        alert('<?php echo esc_js(__('Error de red al procesar los CVs.', 'kovacic')); ?>');
                    });
            });
        });
        </script>
        <?php
    }

    public function handle_delete_board() {
        if (!current_user_can('manage_options')) wp_die('Unauthorized');
        check_admin_referer('kvt_delete_board');
        $slug = isset($_GET['slug']) ? sanitize_text_field(wp_unslash($_GET['slug'])) : '';
        $type = isset($_GET['type']) ? sanitize_text_field(wp_unslash($_GET['type'])) : 'client';
        $option = $type === 'candidate' ? 'kvt_candidate_links' : 'kvt_client_links';
        $links  = get_option($option, []);
        if (isset($links[$slug])) {
            unset($links[$slug]);
            update_option($option, $links, false);
        }
        wp_redirect(admin_url('admin.php?page=kvt-boards'));
        exit;
    }

    /* Proceso -> Cliente term meta */
    public function process_add_fields($taxonomy) {
        $clients = get_terms(['taxonomy'=>self::TAX_CLIENT,'hide_empty'=>false]); ?>
        <div class="form-field">
            <label for="kvt_process_client">Cliente asociado</label>
            <select name="kvt_process_client" id="kvt_process_client">
                <option value="">— Ninguno —</option>
                <?php foreach ($clients as $c): ?>
                    <option value="<?php echo esc_attr($c->term_id); ?>"><?php echo esc_html($c->name); ?></option>
                <?php endforeach; ?>
            </select>
            <p class="description">(Opcional) Vincula este proceso a un cliente.</p>
        </div>
        <div class="form-field">
            <label for="kvt_process_meetings">Reuniones</label>
            <textarea name="kvt_process_meetings" id="kvt_process_meetings" rows="5"></textarea>
            <button type="button" class="button" id="kvt_add_process_meeting">Añadir reunión</button>
        </div>
        <script>
        document.addEventListener('DOMContentLoaded',function(){
            const btn=document.getElementById('kvt_add_process_meeting');
            if(btn){
                btn.addEventListener('click',function(){
                    const ta=document.getElementById('kvt_process_meetings');
                    const info=prompt('Detalles de la reunión:');
                    if(info){
                        const date=new Date().toISOString().slice(0,10);
                        ta.value += (ta.value?"\n":"") + date + ' - ' + info;
                    }
                });
            }
        });
        </script>
        <?php
    }
    public function process_edit_fields($term) {
        $clients = get_terms(['taxonomy'=>self::TAX_CLIENT,'hide_empty'=>false]);
        $current = get_term_meta($term->term_id, 'kvt_process_client', true);
        $meetings = get_term_meta($term->term_id, 'kvt_process_meetings', true);
        ?>
        <tr class="form-field">
            <th scope="row"><label for="kvt_process_client">Cliente asociado</label></th>
            <td>
                <select name="kvt_process_client" id="kvt_process_client">
                    <option value="">— Ninguno —</option>
                    <?php foreach ($clients as $c): ?>
                        <option value="<?php echo esc_attr($c->term_id); ?>" <?php selected($current, $c->term_id); ?>>
                            <?php echo esc_html($c->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        <tr class="form-field">
            <th scope="row"><label for="kvt_process_meetings">Reuniones</label></th>
            <td>
                <textarea name="kvt_process_meetings" id="kvt_process_meetings" rows="5"><?php echo esc_textarea($meetings); ?></textarea><br>
                <button type="button" class="button" id="kvt_add_process_meeting">Añadir reunión</button>
            </td>
        </tr>
        <script>
        document.addEventListener('DOMContentLoaded',function(){
            const btn=document.getElementById('kvt_add_process_meeting');
            if(btn){
                btn.addEventListener('click',function(){
                    const ta=document.getElementById('kvt_process_meetings');
                    const info=prompt('Detalles de la reunión:');
                    if(info){
                        const date=new Date().toISOString().slice(0,10);
                        ta.value += (ta.value?"\n":"") + date + ' - ' + info;
                    }
                });
            }
        });
        </script>
        <?php
    }
    public function save_process_term($term_id, $tt_id) {
        if (isset($_POST['kvt_process_client'])) {
            $client_id = intval($_POST['kvt_process_client']);
            if ($client_id > 0) {
                update_term_meta($term_id, 'kvt_process_client', $client_id);
            } else {
                delete_term_meta($term_id, 'kvt_process_client');
            }
        }
        if (!get_term_meta($term_id, 'kvt_process_creator', true)) {
            update_term_meta($term_id, 'kvt_process_creator', get_current_user_id());
        }
        if (!get_term_meta($term_id, 'kvt_process_created', true)) {
            update_term_meta($term_id, 'kvt_process_created', current_time('Y-m-d'));
        }
        if (isset($_POST['kvt_process_meetings'])) {
            update_term_meta($term_id, 'kvt_process_meetings', sanitize_textarea_field($_POST['kvt_process_meetings']));
        }
    }

    /* Cliente contact meta */
    public function client_add_fields($taxonomy) { ?>
        <div class="form-field">
            <label for="kvt_client_contact_name">Persona de contacto</label>
            <input type="text" name="kvt_client_contact_name" id="kvt_client_contact_name">
        </div>
        <div class="form-field">
            <label for="kvt_client_contact_email">Email de contacto</label>
            <input type="email" name="kvt_client_contact_email" id="kvt_client_contact_email">
        </div>
        <div class="form-field">
            <label for="kvt_client_sector">Sector</label>
            <input type="text" name="kvt_client_sector" id="kvt_client_sector">
        </div>
        <div class="form-field">
            <label for="kvt_client_meetings">Reuniones</label>
            <textarea name="kvt_client_meetings" id="kvt_client_meetings" rows="5"></textarea>
            <button type="button" class="button" id="kvt_add_client_meeting">Añadir reunión</button>
        </div>
        <script>
        document.addEventListener('DOMContentLoaded',function(){
            const btn=document.getElementById('kvt_add_client_meeting');
            if(btn){
                btn.addEventListener('click',function(){
                    const ta=document.getElementById('kvt_client_meetings');
                    const person=prompt('Persona de la reunión:');
                    if(!person) return;
                    const date=prompt('Fecha (YYYY-MM-DD):', new Date().toISOString().slice(0,10)) || new Date().toISOString().slice(0,10);
                    const info=prompt('Detalles de la reunión:');
                    if(info){
                        ta.value += (ta.value?"\n":"") + date + ' | ' + person + ' | ' + info;
                    }
                });
            }
        });
        </script>
        <?php
    }
    public function client_edit_fields($term) {
        $cname  = get_term_meta($term->term_id, 'contact_name', true);
        $cemail = get_term_meta($term->term_id, 'contact_email', true);
        $sector = get_term_meta($term->term_id, 'kvt_client_sector', true);
        $meetings = get_term_meta($term->term_id, 'kvt_client_meetings', true);
        ?>
        <tr class="form-field">
            <th scope="row"><label for="kvt_client_contact_name">Persona de contacto</label></th>
            <td><input type="text" name="kvt_client_contact_name" id="kvt_client_contact_name" value="<?php echo esc_attr($cname); ?>"></td>
        </tr>
        <tr class="form-field">
            <th scope="row"><label for="kvt_client_contact_email">Email de contacto</label></th>
            <td><input type="email" name="kvt_client_contact_email" id="kvt_client_contact_email" value="<?php echo esc_attr($cemail); ?>"></td>
        </tr>
        <tr class="form-field">
            <th scope="row"><label for="kvt_client_sector">Sector</label></th>
            <td><input type="text" name="kvt_client_sector" id="kvt_client_sector" value="<?php echo esc_attr($sector); ?>"></td>
        </tr>
        <tr class="form-field">
            <th scope="row"><label for="kvt_client_meetings">Reuniones</label></th>
            <td>
                <textarea name="kvt_client_meetings" id="kvt_client_meetings" rows="5"><?php echo esc_textarea($meetings); ?></textarea><br>
                <button type="button" class="button" id="kvt_add_client_meeting">Añadir reunión</button>
            </td>
        </tr>
        <script>
        document.addEventListener('DOMContentLoaded',function(){
            const btn=document.getElementById('kvt_add_client_meeting');
            if(btn){
                btn.addEventListener('click',function(){
                    const ta=document.getElementById('kvt_client_meetings');
                    const person=prompt('Persona de la reunión:');
                    if(!person) return;
                    const date=prompt('Fecha (YYYY-MM-DD):', new Date().toISOString().slice(0,10)) || new Date().toISOString().slice(0,10);
                    const info=prompt('Detalles de la reunión:');
                    if(info){
                        ta.value += (ta.value?"\n":"") + date + ' | ' + person + ' | ' + info;
                    }
                });
            }
        });
        </script>
        <?php
    }
    public function save_client_term($term_id, $tt_id) {
        if (isset($_POST['kvt_client_contact_name'])) {
            update_term_meta($term_id, 'contact_name', sanitize_text_field($_POST['kvt_client_contact_name']));
        }
        if (isset($_POST['kvt_client_contact_email'])) {
            update_term_meta($term_id, 'contact_email', sanitize_email($_POST['kvt_client_contact_email']));
        }
        if (isset($_POST['kvt_client_sector'])) {
            update_term_meta($term_id, 'kvt_client_sector', sanitize_text_field($_POST['kvt_client_sector']));
        }
        if (isset($_POST['kvt_client_meetings'])) {
            update_term_meta($term_id, 'kvt_client_meetings', sanitize_textarea_field($_POST['kvt_client_meetings']));
        }
    }

    /* Candidate admin */
    public function form_enctype() {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ($screen && $screen->post_type === self::CPT) {
            echo ' enctype="multipart/form-data"';
        }
    }
    public function replace_tax_metaboxes() {
        remove_meta_box(self::TAX_CLIENT.'div', self::CPT, 'side');
        remove_meta_box(self::TAX_PROCESS.'div', self::CPT, 'side');
        add_meta_box('kvt_client_box',  'Cliente',  [$this,'render_client_dropdown'],  self::CPT, 'side', 'default');
        add_meta_box('kvt_process_box', 'Proceso',  [$this,'render_process_dropdown'], self::CPT, 'side', 'default');
    }
    public function render_client_dropdown($post) {
        $terms = get_terms(['taxonomy'=>self::TAX_CLIENT,'hide_empty'=>false]);
        $assigned = wp_get_object_terms($post->ID, self::TAX_CLIENT, ['fields'=>'ids']);
        $current  = isset($assigned[0]) ? (int)$assigned[0] : 0;
        echo '<select name="kvt_client_term" id="kvt_client_term" class="widefat">';
        echo '<option value="">— Ninguno —</option>';
        foreach ($terms as $t) {
            echo '<option value="'.esc_attr($t->term_id).'" '.selected($current,$t->term_id,false).'>'.esc_html($t->name).'</option>';
        }
        echo '</select>';
    }
    public function render_process_dropdown($post) {
        $terms = get_terms(['taxonomy'=>self::TAX_PROCESS,'hide_empty'=>false]);
        $assigned = wp_get_object_terms($post->ID, self::TAX_PROCESS, ['fields'=>'ids']);
        $current  = isset($assigned[0]) ? (int)$assigned[0] : 0;
        echo '<select name="kvt_process_term" id="kvt_process_term" class="widefat">';
        echo '<option value="">— Ninguno —</option>';
        foreach ($terms as $t) {
            echo '<option value="'.esc_attr($t->term_id).'" '.selected($current,$t->term_id,false).'>'.esc_html($t->name).'</option>';
        }
        echo '</select><p class="description">Si eliges Proceso, se asignará automáticamente su Cliente.</p>';
    }

    private function normalize_name($name) {
        $name = trim((string)$name);
        if ($name === '') return '';
        return mb_convert_case($name, MB_CASE_TITLE, 'UTF-8');
    }
    private function meta_get_compat($post_id, $key, $fallbacks = []) {
        $v = get_post_meta($post_id, $key, true);
        if ($v === '' || $v === null) {
            foreach ($fallbacks as $fb) {
                $vv = get_post_meta($post_id, $fb, true);
                if ($vv !== '' && $vv !== null) {
                    $v = $vv;
                    update_post_meta($post_id, $key, $vv);
                    break;
                }
            }
        }
        if (in_array($key, ['kvt_first_name','first_name','kvt_last_name','last_name'], true)) {
            $norm = $this->normalize_name($v);
            if ($norm !== $v) {
                $alt = strpos($key, 'kvt_') === 0 ? substr($key,4) : 'kvt_'.$key;
                update_post_meta($post_id, $key, $norm);
                update_post_meta($post_id, $alt, $norm);
                $v = $norm;
            }
        }
        return ($v !== '' && $v !== null) ? $v : '';
    }
    private function fmt_date_ddmmyyyy($val){
        $val = trim((string)$val);
        if ($val === '') return '';
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $val)) {
            $ts = strtotime(substr($val,0,10));
            return $ts ? date('d/m/Y',$ts) : $val;
        }
        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $val)) return $val;
        $ts = strtotime(str_replace('/', '-', $val));
        return $ts ? date('d/m/Y',$ts) : $val;
    }

    public function add_meta_boxes() {
        add_meta_box('kvt_candidate_details', 'Datos del candidato', [$this, 'metabox_candidate'], self::CPT, 'normal', 'high');
        add_meta_box('kvt_candidate_status',  'Estado del pipeline',  [$this, 'metabox_status'],    self::CPT, 'side',   'high');
    }
    public function metabox_candidate($post) {
        wp_nonce_field('kvt_save_candidate', 'kvt_nonce');
        wp_nonce_field('kvt_nonce', 'kvt_nonce_ajax');
        $first   = $this->meta_get_compat($post->ID, 'kvt_first_name',  ['first_name']);
        $last    = $this->meta_get_compat($post->ID, 'kvt_last_name',   ['last_name']);
        $email   = $this->meta_get_compat($post->ID, 'kvt_email',       ['email']);
        $phone   = $this->meta_get_compat($post->ID, 'kvt_phone',       ['phone']);
        $country = $this->meta_get_compat($post->ID, 'kvt_country',     ['country']);
        $city    = $this->meta_get_compat($post->ID, 'kvt_city',        ['city']);
        $current_role = $this->meta_get_compat($post->ID, 'kvt_current_role', ['current_role']);
        $cv_url  = $this->meta_get_compat($post->ID, 'kvt_cv_url',      ['cv_url']);
        $cv_date_raw = $this->meta_get_compat($post->ID, 'kvt_cv_uploaded', ['cv_uploaded']);
        $cv_date = $this->fmt_date_ddmmyyyy($cv_date_raw);
        $cv_att  = $this->meta_get_compat($post->ID, 'kvt_cv_attachment_id', ['cv_attachment_id']);
        $cv_txt  = get_post_meta($post->ID, 'kvt_cv_text_url', true);
        $next_raw = $this->meta_get_compat($post->ID, 'kvt_next_action', ['next_action']);
        $next_action = $this->fmt_date_ddmmyyyy($next_raw);
        $next_note = $this->meta_get_compat($post->ID, 'kvt_next_action_note', ['next_action_note']);
        $notes   = $this->meta_get_compat($post->ID, 'kvt_notes',       ['notes']);
        ?>
        <table class="form-table">
            <tr><th><label>Nombre</label></th><td><input type="text" name="kvt_first_name" value="<?php echo esc_attr($first); ?>" class="regular-text"></td></tr>
            <tr><th><label>Apellidos</label></th><td><input type="text" name="kvt_last_name" value="<?php echo esc_attr($last); ?>" class="regular-text"></td></tr>
            <tr><th><label>Email</label></th><td><input type="email" name="kvt_email" value="<?php echo esc_attr($email); ?>" class="regular-text"></td></tr>
            <tr><th><label>Teléfono</label></th><td><input type="text" name="kvt_phone" value="<?php echo esc_attr($phone); ?>" class="regular-text"></td></tr>
            <tr><th><label>País</label></th><td><input type="text" name="kvt_country" value="<?php echo esc_attr($country); ?>" class="regular-text"></td></tr>
            <tr><th><label>Ciudad</label></th><td><input type="text" name="kvt_city" value="<?php echo esc_attr($city); ?>" class="regular-text"></td></tr>
            <tr><th><label>Puesto actual</label></th><td><input type="text" name="kvt_current_role" value="<?php echo esc_attr($current_role); ?>" class="regular-text"></td></tr>

            <tr><th><label>CV (URL)</label></th>
                <td>
                    <input type="url" name="kvt_cv_url" value="<?php echo esc_attr($cv_url); ?>" class="regular-text" placeholder="https://...">
                    <?php if ($cv_url): ?><p style="margin:.4em 0 0;"><a href="<?php echo esc_url($cv_url); ?>" target="_blank" rel="noopener">Abrir CV actual</a></p><?php endif; ?>
                    <?php if ($cv_att): ?><p style="margin:.2em 0 0;color:#555;">Adjunto (ID: <?php echo intval($cv_att); ?>)</p><?php endif; ?>
                </td>
            </tr>
            <tr><th><label>Subir CV (PDF/DOC/DOCX)</label></th>
                <td>
                    <input type="file" id="kvt_cv_file" name="kvt_cv_file" style="display:none" accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document">
                    <button type="button" class="button" id="kvt_cv_file_btn">Seleccionar archivo</button>
                    <button type="button" class="button" id="kvt_cv_upload_btn" disabled>Subir</button>
                    <span id="kvt_cv_file_label" style="margin-left:.5em;">
                        <?php echo $cv_att ? esc_html(basename(get_attached_file($cv_att))) : 'Ningún archivo seleccionado'; ?>
                    </span>
                    <?php if ($cv_url || $cv_att): ?>
                        <p style="margin:.4em 0 0;"><label><input type="checkbox" name="kvt_cv_remove" value="1"> Eliminar CV actual</label></p>
                    <?php endif; ?>
                    <p class="description">Al subir un CV, guardamos el enlace en “CV (URL)” y la fecha (DD/MM/YYYY) si está vacía.</p>
                </td>
            </tr>

            <tr><th><label>CV leído IA</label></th>
                <td>
                    <?php if ($cv_txt): ?>
                        <a href="<?php echo esc_url($cv_txt); ?>" target="_blank" rel="noopener" class="kvt-cv-link">Ver texto</a>
                    <?php else: ?>
                        <em>No disponible</em>
                    <?php endif; ?>
                </td>
            </tr>

            <tr><th><label>Fecha de subida</label></th><td><input type="text" name="kvt_cv_uploaded" value="<?php echo esc_attr($cv_date); ?>" class="regular-text kvt-date" placeholder="DD/MM/YYYY"></td></tr>
            <tr><th><label>Próxima acción</label></th><td><input type="text" name="kvt_next_action" value="<?php echo esc_attr($next_action); ?>" class="regular-text kvt-date" placeholder="DD/MM/YYYY"></td></tr>
            <tr><th><label>Comentario próxima acción</label></th><td><input type="text" name="kvt_next_action_note" value="<?php echo esc_attr($next_note); ?>" class="regular-text"></td></tr>

            <tr><th><label>Notas</label></th>
                <td><textarea name="kvt_notes" rows="6" class="large-text" placeholder="Notas internas"><?php echo esc_textarea($notes); ?></textarea></td>
            </tr>
        </table>
        <script>
        document.addEventListener('DOMContentLoaded', function(){
            const btn    = document.getElementById('kvt_cv_file_btn');
            const input  = document.getElementById('kvt_cv_file');
            const label  = document.getElementById('kvt_cv_file_label');
            const upload = document.getElementById('kvt_cv_upload_btn');
            const urlFld = document.querySelector('input[name="kvt_cv_url"]');
            const dateFld= document.querySelector('input[name="kvt_cv_uploaded"]');
            const nonce  = document.getElementById('kvt_nonce_ajax');
            const pid    = <?php echo $post->ID; ?>;
            const maskDate = e => {
                let v = e.target.value.replace(/[^0-9]/g,'').slice(0,8);
                if (v.length > 4) v = v.replace(/(\d{2})(\d{2})(\d+)/,'$1-$2-$3');
                else if (v.length > 2) v = v.replace(/(\d{2})(\d+)/,'$1-$2');
                e.target.value = v;
            };
            document.querySelectorAll('input.kvt-date').forEach(inp=>{
                inp.addEventListener('input', maskDate);
            });
            if(btn && input){
                btn.addEventListener('click', function(){ input.click(); });
                input.addEventListener('change', function(){
                    label.textContent = input.files[0] ? input.files[0].name : 'Ningún archivo seleccionado';
                    if(upload) upload.disabled = !input.files.length;
                });
            }
            if(upload && input){
                upload.addEventListener('click', function(){
                    if(!input.files.length) return;
                    upload.disabled = true;
                    const fd = new FormData();
                    fd.append('action','kvt_upload_cv');
                    if(nonce) fd.append('_ajax_nonce', nonce.value);
                    fd.append('id', pid);
                    fd.append('file', input.files[0]);
                    fetch(window.ajaxurl || '', {method:'POST', body: fd})
                        .then(r=>r.json())
                        .then(j=>{
                            upload.disabled = false;
                            if(!j.success){ alert(j.data && j.data.msg ? j.data.msg : 'Error al subir CV'); return; }
                            if(urlFld) urlFld.value = j.data.url || '';
                            if(dateFld && !dateFld.value) dateFld.value = j.data.date || '';
                            if(j.data.fields){
                                const f = j.data.fields;
                                const map = {
                                    first_name: document.querySelector('input[name="kvt_first_name"]'),
                                    last_name: document.querySelector('input[name="kvt_last_name"]'),
                                    email: document.querySelector('input[name="kvt_email"]'),
                                    phone: document.querySelector('input[name="kvt_phone"]'),
                                    country: document.querySelector('input[name="kvt_country"]'),
                                    city: document.querySelector('input[name="kvt_city"]'),
                                    current_role: document.querySelector('input[name="kvt_current_role"]'),
                                };
                                Object.keys(map).forEach(k=>{ if(map[k] && f[k]) map[k].value = f[k]; });
                            }
                            label.textContent = 'Ningún archivo seleccionado';
                            input.value = '';
                            alert('CV subido y guardado.');
                            })
                            .catch(()=>{ upload.disabled = false; alert('Error de red.'); });
                });
            }
        });
        </script>
        <p class="description">Asigna un <strong>Proceso</strong> en la caja lateral. Se autovinculará el <strong>Cliente</strong> relacionado.</p>
        <?php
    }
    public function metabox_status($post) {
        $status = get_post_meta($post->ID, 'kvt_status', true);
        $statuses = $this->get_statuses(); ?>
        <p><label for="kvt_status">Estado actual</label></p>
        <select name="kvt_status" id="kvt_status" class="widefat">
            <?php foreach ($statuses as $st): ?>
                <option value="<?php echo esc_attr($st); ?>" <?php selected($status, $st); ?>><?php echo esc_html($st); ?></option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    public function save_candidate_meta($post_id) {
        if (!isset($_POST['kvt_nonce']) || !wp_verify_nonce($_POST['kvt_nonce'], 'kvt_save_candidate')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        $uploaded_url = '';
        $uploaded_dt  = '';

        // Remove CV
        if (!empty($_POST['kvt_cv_remove'])) {
            $att_id = (int) get_post_meta($post_id, 'kvt_cv_attachment_id', true);
            if ($att_id) wp_delete_attachment($att_id, true);
            delete_post_meta($post_id, 'kvt_cv_attachment_id');
            delete_post_meta($post_id, 'kvt_cv_url');
            delete_post_meta($post_id, 'cv_url');
            // Remove cached text files
            $txt_url = get_post_meta($post_id, 'kvt_cv_text_url', true);
            if ($txt_url) {
                $path = wp_parse_url($txt_url, PHP_URL_PATH);
                if ($path) @unlink(ABSPATH . ltrim($path, '/'));
            }
            delete_post_meta($post_id, 'kvt_cv_text');
            delete_post_meta($post_id, 'kvt_cv_text_url');
        }

        // Upload new CV
        if (!empty($_FILES['kvt_cv_file']['name'])) {
            // Remove previous cached text if exists
            $old_txt = get_post_meta($post_id, 'kvt_cv_text_url', true);
            if ($old_txt) {
                $path = wp_parse_url($old_txt, PHP_URL_PATH);
                if ($path) @unlink(ABSPATH . ltrim($path, '/'));
            }
            delete_post_meta($post_id, 'kvt_cv_text');
            delete_post_meta($post_id, 'kvt_cv_text_url');

            if (!function_exists('media_handle_upload')) {
                require_once ABSPATH . 'wp-admin/includes/image.php';
                require_once ABSPATH . 'wp-admin/includes/file.php';
                require_once ABSPATH . 'wp-admin/includes/media.php';
            }
            add_filter('upload_mimes', function($mimes){
                $mimes['pdf']  = 'application/pdf';
                $mimes['doc']  = 'application/msword';
                $mimes['docx'] = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
                return $mimes;
            });
            $attach_id = media_handle_upload('kvt_cv_file', $post_id);
            if (!is_wp_error($attach_id)) {
                $uploaded_url = wp_get_attachment_url($attach_id);
                update_post_meta($post_id, 'kvt_cv_attachment_id', $attach_id);
                if ($uploaded_url) {
                    update_post_meta($post_id, 'kvt_cv_url', esc_url_raw($uploaded_url));
                    update_post_meta($post_id, 'cv_url', esc_url_raw($uploaded_url)); // legacy
                }
                // Store plain-text version of the CV for later AI processing
                $client_text = isset($_POST['cv_text']) ? sanitize_textarea_field(wp_unslash($_POST['cv_text'])) : '';
                if ($client_text !== '') {
                    update_post_meta($post_id, 'kvt_cv_text', $client_text);
                } else {
                    $this->save_cv_text_attachment($post_id, $attach_id);
                }
                $today = date_i18n('d-m-Y');
                update_post_meta($post_id, 'kvt_cv_uploaded', $today);
                update_post_meta($post_id, 'cv_uploaded', $today);
                $uploaded_dt = $today;
                // Extract current role from CV using AI
                $this->update_current_role_from_cv($post_id);
            } else {
                error_log('[KVT] Error subiendo CV: ' . $attach_id->get_error_message());
            }
        }

        // Save fields
        $fields = [
            'kvt_first_name' => ['first_name'],
            'kvt_last_name'  => ['last_name'],
            'kvt_email'      => ['email'],
            'kvt_phone'      => ['phone'],
            'kvt_country'    => ['country'],
            'kvt_city'       => ['city'],
            'kvt_current_role'=> ['current_role'],
            'kvt_cv_url'     => ['cv_url'],
            'kvt_cv_uploaded'=> ['cv_uploaded'],
            'kvt_next_action'=> ['next_action'],
            'kvt_next_action_note'=> ['next_action_note'],
            'kvt_status'     => [],
            'kvt_notes'      => ['notes'],
        ];
        foreach ($fields as $k => $fallbacks) {
            if ($k === 'kvt_cv_url' && $uploaded_url) continue;
            if ($k === 'kvt_cv_uploaded' && $uploaded_dt) continue;
            if (isset($_POST[$k])) {
                $val = ($k==='kvt_notes') ? wp_kses_post($_POST[$k])
                      : (($k==='kvt_email') ? sanitize_email($_POST[$k]) : sanitize_text_field($_POST[$k]));
                if ($k==='kvt_first_name' || $k==='kvt_last_name') $val = $this->normalize_name($val);
                if ($k === 'kvt_cv_uploaded' || $k === 'kvt_next_action') $val = $this->fmt_date_ddmmyyyy($val);
                update_post_meta($post_id, $k, $val);
                foreach ($fallbacks as $fb) update_post_meta($post_id, $fb, $val);
            }
        }

        // Terms
        $client_term  = isset($_POST['kvt_client_term'])  ? intval($_POST['kvt_client_term'])  : 0;
        $process_term = isset($_POST['kvt_process_term']) ? intval($_POST['kvt_process_term']) : 0;

        if ($process_term > 0) {
            wp_set_object_terms($post_id, [$process_term], self::TAX_PROCESS, false);
            $linked_client = (int) get_term_meta($process_term, 'kvt_process_client', true);
            if ($linked_client > 0) {
                wp_set_object_terms($post_id, [$linked_client], self::TAX_CLIENT, false);
            } elseif ($client_term > 0) {
                wp_set_object_terms($post_id, [$client_term], self::TAX_CLIENT, false);
            }
        } else {
            if ($client_term > 0) {
                wp_set_object_terms($post_id, [$client_term], self::TAX_CLIENT, false);
            } else {
                wp_set_object_terms($post_id, [], self::TAX_CLIENT, false);
                wp_set_object_terms($post_id, [], self::TAX_PROCESS, false);
            }
        }

        // Title fallback
        $title = get_the_title($post_id);
        if (!$title) {
            $fn = get_post_meta($post_id, 'kvt_first_name', true);
            $ln = get_post_meta($post_id, 'kvt_last_name', true);
            $new = trim($fn . ' ' . $ln);
            if ($new) wp_update_post(['ID'=>$post_id,'post_title'=>$new]);
        }
    }

    /* Helpers */
    private function get_statuses() {
        $raw = get_option(self::OPT_STATUSES, "");
        $lines = preg_split('/\r\n|\r|\n/', (string)$raw);
        $out = [];
        foreach ($lines as $l) { $l = trim($l); if ($l !== '') $out[] = $l; }
        if (empty($out)) $out = ['Identified','Contacted','Interviewed','Offer','Declined'];
        return $out;
    }
    private function get_columns() {
        $raw = get_option(self::OPT_COLUMNS, "");
        $lines = preg_split('/\r\n|\r|\n/', (string)$raw);
        $cols = [];
        foreach ($lines as $l) {
            $l = trim($l); if ($l === '') continue;
            $parts = explode('|', $l, 2);
            $key = trim($parts[0]);
            $label = isset($parts[1]) ? trim($parts[1]) : $key;
            $cols[] = ['key'=>$key,'label'=>$label];
        }
        if (empty($cols)) {
            $cols = [
                ['key'=>'candidate','label'=>'Candidato'],
                ['key'=>'status','label'=>'Estado'],
                ['key'=>'client','label'=>'Cliente'],
                ['key'=>'process','label'=>'Proceso'],
                ['key'=>'email','label'=>'Email'],
                ['key'=>'phone','label'=>'Teléfono'],
                ['key'=>'country','label'=>'País'],
                ['key'=>'city','label'=>'Ciudad'],
                ['key'=>'current_role','label'=>'Puesto actual'],
                ['key'=>'cv_url','label'=>'CV (URL)'],
                ['key'=>'cv_uploaded','label'=>'Fecha de subida'],
            ];
        }
        $keys = wp_list_pluck($cols, 'key');
        if (!in_array('next_action', $keys, true)) {
            $cols[] = ['key'=>'next_action','label'=>'Próxima acción'];
        }
        if (!in_array('next_action_note', $keys, true)) {
            $cols[] = ['key'=>'next_action_note','label'=>'Comentario próxima acción'];
        }
        return $cols;
    }
    private function get_process_map() {
        $terms = get_terms(['taxonomy'=>self::TAX_PROCESS,'hide_empty'=>false]);
        $out = [];
        $statuses = array_values(array_filter(array_map('trim', explode("\n", get_option(self::OPT_STATUSES, '')))));
        foreach ($terms as $t) {
            $cid = (int) get_term_meta($t->term_id, 'kvt_process_client', true);
            // Determine furthest active job stage
            $job_stage = '';
            $posts = get_posts([
                'post_type'   => self::CPT,
                'numberposts' => -1,
                'fields'      => 'ids',
                'tax_query'   => [[
                    'taxonomy' => self::TAX_PROCESS,
                    'terms'    => $t->term_id,
                ]],
            ]);
            $max = -1;
            foreach ($posts as $pid) {
                $st = get_post_meta($pid,'kvt_status',true);
                $idx = array_search($st, $statuses, true);
                if ($idx !== false && strtolower($st) !== 'declined' && $idx > $max) {
                    $max = $idx;
                }
            }
            $candidate_count = count($posts);
            if ($max >= 0 && isset($statuses[$max])) $job_stage = $statuses[$max];

            $out[] = [
                'id'          => $t->term_id,
                'name'        => $t->name,
                'client_id'   => $cid ?: 0,
                'client'      => $cid ? get_term($cid)->name : '',
                'description' => wp_strip_all_tags($t->description),
                'contact_name'  => get_term_meta($t->term_id, 'contact_name', true),
                'contact_email' => get_term_meta($t->term_id, 'contact_email', true),
                'meetings'      => get_term_meta($t->term_id, 'kvt_process_meetings', true),
                'creator'       => get_the_author_meta('display_name', (int) get_term_meta($t->term_id, 'kvt_process_creator', true)),
                'created'       => get_term_meta($t->term_id, 'kvt_process_created', true),
                'candidates'    => $candidate_count,
                'job_stage'     => $job_stage,
            ];
        }
        return $out;
    }

    private function get_candidate_countries() {
        global $wpdb;
        $sql = $wpdb->prepare(
            "SELECT DISTINCT pm.meta_value FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID WHERE pm.meta_key IN ('kvt_country','country') AND p.post_type = %s AND p.post_status <> 'trash' AND pm.meta_value <> ''",
            self::CPT
        );
        $raw = $wpdb->get_col($sql);
        $countries = [];
        foreach ($raw as $c) {
            $c = trim($c);
            $norm = strtolower(remove_accents($c));
            if (!isset($countries[$norm])) {
                $countries[$norm] = $c;
            }
        }
        ksort($countries);
        return array_values($countries);
    }
    private function get_term_name($post_id, $tax){
        $terms = wp_get_object_terms($post_id, $tax);
        if (is_wp_error($terms) || empty($terms)) return '';
        return $terms[0]->name;
    }
    private function count_notes($notes) {
        $notes = (string) $notes;
        if ($notes === '') return 0;
        $lines = preg_split('/\r\n|\r|\n/', $notes);
        $cnt = 0;
        foreach ($lines as $ln) if (trim($ln) !== '') $cnt++;
        return $cnt;
    }

    private function get_unique_meta_values($keys) {
        global $wpdb;
        $keys = array_map('esc_sql', (array) $keys);
        $in   = "'" . implode("','", $keys) . "'";
        $vals = $wpdb->get_col("SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key IN ($in) AND meta_value <> ''");
        $vals = array_filter(array_map('trim', (array) $vals));
        sort($vals);
        return $vals;
    }

    public function schedule_followup_cron() {
        if (!wp_next_scheduled('kvt_daily_followup')) {
            wp_schedule_event(time(), 'daily', 'kvt_daily_followup');
        }
    }
    public function cron_check_followups() {
        $posts = get_posts([
            'post_type'   => self::CPT,
            'post_status' => 'any',
            'numberposts' => -1,
            'meta_query'  => [
                ['key' => 'kvt_next_action', 'value' => '', 'compare' => '!='],
            ],
        ]);
        $due = [];
        $today = strtotime('today');
        foreach ($posts as $p) {
            $raw = get_post_meta($p->ID, 'kvt_next_action', true);
            $ts  = strtotime(str_replace('/', '-', $raw));
            if ($ts && $ts <= $today) {
                $due[] = get_the_title($p);
            }
        }
        update_option('kvt_followup_due', $due);
    }

    public function schedule_mit_report() {
        $tz   = new DateTimeZone('Europe/Madrid');
        $time = get_option(self::OPT_MIT_TIME, '09:00');
        if (!preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', $time)) {
            $time = '09:00';
        }
        list($hour, $min) = array_map('intval', explode(':', $time));
        $freq = get_option(self::OPT_MIT_FREQUENCY, 'weekly');
        $day  = strtolower(get_option(self::OPT_MIT_DAY, 'monday'));
        $now  = new DateTime('now', $tz);
        $next = clone $now;
        $next->setTime($hour, $min);
        $recurrence = 'weekly';
        if ($freq === 'daily') {
            if ($now->getTimestamp() >= $next->getTimestamp()) {
                $next->modify('+1 day');
            }
            $recurrence = 'daily';
        } else {
            $days = [
                'monday'    => 1,
                'tuesday'   => 2,
                'wednesday' => 3,
                'thursday'  => 4,
                'friday'    => 5,
                'saturday'  => 6,
                'sunday'    => 7,
            ];
            $target = $days[$day] ?? 1;
            $current = (int)$now->format('N');
            if ($current > $target || ($current === $target && $now->getTimestamp() >= $next->getTimestamp())) {
                $next = new DateTime('next ' . $day, $tz);
            } else {
                $next = new DateTime('this ' . $day, $tz);
            }
            $next->setTime($hour, $min);
            $recurrence = 'weekly';
        }
        $timestamp = $next->getTimestamp();
        $scheduled = wp_next_scheduled('kvt_mit_report');
        if (!$scheduled || abs($scheduled - $timestamp) > 60) {
            if ($scheduled) wp_unschedule_event($scheduled, 'kvt_mit_report');
            wp_schedule_event($timestamp, $recurrence, 'kvt_mit_report');
        }
    }

    public function cron_mit_report() {
        $key = get_option(self::OPT_OPENAI_KEY, '');
        if (!$key) return;
        $model = get_option(self::OPT_OPENAI_MODEL, 'gpt-5');
        $ctx = $this->mit_gather_context();
        $summary = $ctx['summary'];
        $prompt = "Eres MIT, un asistente de reclutamiento especializado en energía renovable. Nunca uses '—'. Inicio del mensaje: Siempre comienza con un tono juguetón y cercano, presentándote como MIT, su asistente de IA. Incluye un consejo positivo para iniciar el día, relacionado con la vida o con los retos de emprender una nueva empresa (varía entre ambos). Dirígete siempre a Alan (usa su nombre). No empieces hablando directamente de energía renovable; primero da el consejo positivo. Contenido principal: Ofrece recomendaciones sobre cómo progresar en los procesos, cómo dar seguimiento a candidatos y clientes, y cualquier otro consejo útil para que la empresa sea más exitosa. Recuerda que tienes acceso a todos los datos, clientes, candidatos y procesos, así que puedes usarlos libremente para dar recomendaciones concretas. No inventes datos nuevos; utiliza únicamente la información disponible y, si falta algún dato, indícalo claramente. Plantillas de correo: Cuando generes plantillas, no menciones a MIT. Deja el cierre del correo abierto para que lo firme el remitente. Usa las siguientes variables disponibles: {{first_name}} {{surname}} {{country}} {{city}} {{client}} {{role}} {{status}} {{board}} (enlace al tablero) {{sender}} (remitente) Importante: si recomiendas un nuevo rol para un candidato, no uses {{role}} en el texto, ya que este campo hace referencia al rol actual del candidato en su perfil. Formato de salida: Devuelve la respuesta siempre en HTML. Usa la etiqueta h3 para los títulos de sección, ul/li para listas, blockquote para plantillas de correo y strong para resaltar nombres o roles importantes. Separa las secciones con hr. Datos de entrad: Dispones del campo $summary, que resume información clave de procesos, clientes o candidatos. Con esos datos, genera un informe con recomendaciones y sugerencias accionables.";
        $resp = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $key,
                'Content-Type'  => 'application/json',
            ],
            'body' => json_encode([
                'model'   => $model,
                'messages'=> [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]),
            'timeout' => self::MIT_TIMEOUT,
        ]);
        if (is_wp_error($resp)) return;
        $data = json_decode(wp_remote_retrieve_body($resp), true);
        $text = $this->mit_strip_fences(trim($data['choices'][0]['message']['content'] ?? ''));
        if (!$text) return;

        $raw = get_option(self::OPT_MIT_RECIPIENTS, get_option('admin_email'));
        $list = array_filter(array_map('trim', explode(',', (string) $raw)));
        $emails = [];
        foreach ($list as $email) {
            $email = sanitize_email($email);
            if ($email) $emails[] = $email;
        }
        if (empty($emails)) return;

        $from_email = get_option(self::OPT_FROM_EMAIL, '');
        if (!$from_email) $from_email = get_option('admin_email');
        $from_name  = get_option(self::OPT_FROM_NAME, '');
        if (!$from_name) $from_name = get_bloginfo('name');
        $from_cb = null;
        $from_name_cb = null;
        if ($from_email) {
            $from_cb = function() use ($from_email){ return $from_email; };
            add_filter('wp_mail_from', $from_cb, 99);
        }
        if ($from_name) {
            $from_name_cb = function() use ($from_name){ return $from_name; };
            add_filter('wp_mail_from_name', $from_name_cb, 99);
        }
        wp_mail($emails, 'Informe MIT', wp_kses_post($text), ['Content-Type: text/html; charset=UTF-8']);
        if ($from_cb) remove_filter('wp_mail_from', $from_cb, 99);
        if ($from_name_cb) remove_filter('wp_mail_from_name', $from_name_cb, 99);
    }

    public function followup_admin_notice() {
        if (!current_user_can('edit_posts')) return;
        $due = get_option('kvt_followup_due', []);
        if (!empty($due)) {
            $list = esc_html(implode(', ', $due));
            echo '<div class="notice notice-warning"><p>Seguimientos pendientes: ' . $list . '</p></div>';
        }
    }

    /* Shortcode */
    public function shortcode($atts = []) {
        $slug   = isset($_GET['kvt_board']) ? sanitize_text_field($_GET['kvt_board']) : '';
        $client_links    = get_option('kvt_client_links', []);
        $candidate_links = get_option('kvt_candidate_links', []);
        $is_client_board    = $slug && isset($client_links[$slug]);
        $is_candidate_board = $slug && isset($candidate_links[$slug]);
        if (!$is_client_board && !$is_candidate_board && (!is_user_logged_in() || !current_user_can('edit_posts'))) {
            return '<div class="kvt-wrapper"><p>Debes iniciar sesión para ver el pipeline.</p></div>';
        }
        $clients   = get_terms(['taxonomy'=>self::TAX_CLIENT, 'hide_empty'=>false]);
        $processes = get_terms(['taxonomy'=>self::TAX_PROCESS,'hide_empty'=>false]);
        $statuses  = $this->get_statuses();
        $countries = $this->get_candidate_countries();
        $cities    = $this->get_unique_meta_values(['kvt_city','city']);
        $proc_map  = $this->get_process_map();
        $client_map = array_map(function($t){
            return [
                'id'            => $t->term_id,
                'name'          => $t->name,
                'contact_name'  => get_term_meta($t->term_id, 'contact_name', true),
                'contact_email' => get_term_meta($t->term_id, 'contact_email', true),
                'contact_phone' => get_term_meta($t->term_id, 'contact_phone', true),
                'description'   => wp_strip_all_tags($t->description),
                'meetings'      => get_term_meta($t->term_id, 'kvt_client_meetings', true),
            ];
        }, $clients);
        $from_name_def  = get_option(self::OPT_FROM_NAME, '');
        $from_email_def = get_option(self::OPT_FROM_EMAIL, '');
        $templates      = $this->get_email_templates();
        $sent_emails    = get_option(self::OPT_EMAIL_LOG, []);
        $count_obj = wp_count_posts(self::CPT, 'readable');
        $total_candidates = array_sum((array) $count_obj);
        $recent_q = new WP_Query([
            'post_type'      => self::CPT,
            'post_status'    => 'any',
            'date_query'     => [ [ 'after' => date('Y-m-d', strtotime('-7 days')) ] ],
            'fields'         => 'ids',
            'posts_per_page' => -1,
        ]);
        $recent_candidates = $recent_q->found_posts;

        ob_start(); ?>
        <div class="kvt-wrapper">
            <nav class="kvt-nav" aria-label="Navegación principal">
                <a href="#" class="active" data-view="detalles"><span class="dashicons dashicons-dashboard"></span> Panel</a>
                <a href="#" data-view="calendario"><span class="dashicons dashicons-calendar"></span> Calendario</a>
                <a href="#" data-view="base"><span class="dashicons dashicons-admin-users"></span> Candidatos</a>
                <a href="#" data-view="base" id="kvt_open_clients"><span class="dashicons dashicons-businessman"></span> Clientes</a>
                <a href="#" data-view="base" id="kvt_open_processes"><span class="dashicons dashicons-networking"></span> Procesos</a>
                <a href="#" data-view="email" id="kvt_nav_email"><span class="dashicons dashicons-email"></span> Correo</a>
                <a href="#" data-view="keyword"><span class="dashicons dashicons-search"></span> <?php esc_html_e('Búsqueda de palabras', 'kovacic'); ?></a>
                <a href="#" data-view="ai"><span class="dashicons dashicons-search"></span> Buscador IA</a>
                <a href="#" data-view="boards" id="kvt_nav_boards"><span class="dashicons dashicons-admin-generic"></span> Tableros</a>
                <a href="#" data-view="chat"><span class="dashicons dashicons-format-chat"></span> Chat con MIT</a>
            </nav>
            <div class="kvt-content">
            <?php if ($is_client_board || $is_candidate_board): ?>
            <img src="https://kovacictalent.com/wp-content/uploads/2025/08/Logo_Kovacic.png" alt="Kovacic Talent" class="kvt-logo">
            <?php endif; ?>
            <div class="kvt-header"></div>
            <div id="kvt_filters_bar" class="kvt-filters" style="display:none;">
                <div class="kvt-filter-field">
                    <label for="kvt_client">Cliente</label>
                    <select id="kvt_client">
                        <option value="">— Todos —</option>
                        <?php foreach ($clients as $c): ?>
                          <option value="<?php echo esc_attr($c->term_id); ?>"><?php echo esc_html($c->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
              <div class="kvt-filter-field">
                  <label for="kvt_process">Proceso</label>
                  <select id="kvt_process">
                      <option value="">— Todos —</option>
                      <?php foreach ($processes as $t): ?>
                        <option value="<?php echo esc_attr($t->term_id); ?>"><?php echo esc_html($t->name); ?></option>
                      <?php endforeach; ?>
                  </select>
              </div>
              <div class="kvt-filter-field">
                  <label for="kvt_stage">Etapa</label>
                  <select id="kvt_stage"><option value=""><?php esc_html_e('Etapa', 'kovacic'); ?></option></select>
              </div>
              <button class="btn k-activity-toggle" id="k-toggle-activity"><?php esc_html_e('Actividad', 'kovacic'); ?></button>
          </div>

          <div id="kvt_selected_info" class="kvt-selected-info" style="display:none;"></div>

          <div class="kvt-main">
                <div id="kvt_table_wrap" class="kvt-table-wrap" style="display:none;">
                    <div id="kvt_stage_overview" class="kvt-stage-overview"></div>
                    <div id="kvt_ats_bar" class="kvt-ats-bar">
                        <label for="kvt_search">Buscar</label>
                        <input type="text" id="kvt_search" placeholder="Buscar candidato, empresa, ciudad...">
                        <label for="kvt_stage_filter">Etapa</label>
                        <select id="kvt_stage_filter"><option value="">Todas las etapas</option></select>
                        <form id="kvt_export_form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" target="_blank" style="display:inline;">
                            <input type="hidden" name="action" value="kvt_export">
                            <input type="hidden" name="kvt_export_nonce" value="<?php echo esc_attr(wp_create_nonce('kvt_export')); ?>">
                            <input type="hidden" name="filter_client"  id="kvt_export_client"  value="">
                            <input type="hidden" name="filter_process" id="kvt_export_process" value="">
                            <input type="hidden" name="format"         id="kvt_export_format"   value="xls">
                            <button class="kvt-btn" type="button" id="kvt_export_xls">Exportar Excel</button>
                        </form>
                        <button type="button" class="kvt-btn" id="kvt_add_candidate_table_btn">Añadir candidato</button>
                    </div>
                    <div id="kvt_board_base" class="kvt-base" style="display:none;">
                      <div class="kvt-tabs" id="kvt_board_tabs">
                        <button type="button" class="kvt-tab active" data-target="candidates">Candidatos</button>
                        <button type="button" class="kvt-tab" data-target="clients">Clientes</button>
                        <button type="button" class="kvt-tab" data-target="processes">Procesos</button>
                      </div>
                      <div id="kvt_board_tab_candidates" class="kvt-tab-panel active">
                        <div class="kvt-head">
                          <h3 class="kvt-title">Base de candidatos</h3>
                          <div class="kvt-stats"><span>Total: <?php echo intval($total_candidates); ?></span><span>Últimos 7 días: <?php echo intval($recent_candidates); ?></span></div>
                          <div class="kvt-toolbar">
                            <label>Nombre
                              <input type="text" id="kvt_board_name" placeholder="Nombre">
                            </label>
                            <label>Puesto/Empresa actual
                              <input type="text" id="kvt_board_role" placeholder="Puesto/Empresa actual (ej: Gerente, Analista)">
                              <small class="kvt-hint">Puedes buscar varios separados por coma</small>
                            </label>
                            <label>Ubicación
                              <input type="text" id="kvt_board_location" placeholder="País o ciudad (ej: Países Bajos, Chile)">
                              <small class="kvt-hint">Puedes buscar varios separados por coma</small>
                            </label>
                            <button type="button" class="kvt-btn" id="kvt_board_assign">Asignar a proceso</button>
                            <form id="kvt_board_export_all_form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" target="_blank">
                              <input type="hidden" name="action" value="kvt_export">
                              <input type="hidden" name="kvt_export_nonce" value="<?php echo esc_attr(wp_create_nonce('kvt_export')); ?>">
                              <input type="hidden" name="filter_client" value="">
                              <input type="hidden" name="filter_process" value="">
                            <input type="hidden" name="format" id="kvt_board_export_all_format" value="xls">
                              <button type="button" class="kvt-btn" id="kvt_board_export_all_xls">Exportar Excel</button>
                            </form>
                            <button type="button" class="kvt-btn kvt-add-candidate" id="kvt_add_candidate_btn">Nuevo</button>
                          </div>
                        </div>
                        <div id="kvt_board_list" class="kvt-list"></div>
                        <div class="kvt-modal-pager">
                          <button type="button" class="kvt-btn kvt-secondary" id="kvt_board_prev">Anterior</button>
                          <span id="kvt_board_pageinfo"></span>
                          <button type="button" class="kvt-btn kvt-secondary" id="kvt_board_next">Siguiente</button>
                        </div>
                      </div>
                      <div id="kvt_board_tab_clients" class="kvt-tab-panel">
                        <div class="kvt-head">
                          <button type="button" class="kvt-btn" id="kvt_add_client_btn">Nuevo</button>
                        </div>
                        <div id="kvt_board_clients_list" class="kvt-list"></div>
                      </div>
                      <div id="kvt_board_tab_processes" class="kvt-tab-panel">
                        <div class="kvt-head">
                          <div id="kvt_board_proc_info" class="kvt-selected-info" style="display:none;"></div>
                          <button type="button" class="kvt-btn" id="kvt_add_process_btn">Nuevo</button>
                        </div>
                        <div id="kvt_board_processes_list" class="kvt-list"></div>
                      </div>
                    </div>
                    <table id="kvt_table">
                        <thead><tr id="kvt_table_head"></tr></thead>
                        <tbody id="kvt_table_body"></tbody>
                    </table>
                    <div id="kvt_table_pager" class="kvt-table-pager" style="display:none;">
                        <button type="button" class="kvt-btn kvt-secondary" id="kvt_table_prev">Anterior</button>
                        <span id="kvt_table_pageinfo"></span>
                        <button type="button" class="kvt-btn kvt-secondary" id="kvt_table_next">Siguiente</button>
                    </div>
                </div>
                <div id="kvt_keyword_view" style="display:none;">
                  <div class="kvt-modal-controls">
                    <div class="kvt-hint">
                      <p><?php esc_html_e('Cómo usar las palabras clave:', 'kovacic'); ?></p>
                      <ul>
                        <li><?php esc_html_e('Usa', 'kovacic'); ?> <strong>Y</strong> <?php esc_html_e('cuando quieras que todas las palabras aparezcan en el resultado.', 'kovacic'); ?></li>
                        <li><?php esc_html_e('Usa', 'kovacic'); ?> <strong>O</strong> <?php esc_html_e('cuando baste con que aparezca una de ellas.', 'kovacic'); ?></li>
                      </ul>
                      <p><?php esc_html_e('Ejemplos:', 'kovacic'); ?></p>
                      <ul>
                        <li><strong><?php esc_html_e('PPA Y minería Y Chile O Holanda', 'kovacic'); ?></strong> <?php esc_html_e('→ incluye PPA y minería, y además Chile o Holanda.', 'kovacic'); ?></li>
                        <li><strong><?php esc_html_e('energía solar Y fotovoltaica', 'kovacic'); ?></strong> <?php esc_html_e('→ incluye ambas palabras a la vez.', 'kovacic'); ?></li>
                        <li><strong><?php esc_html_e('litio O cobre O níquel', 'kovacic'); ?></strong> <?php esc_html_e('→ basta con que aparezca una de esas palabras.', 'kovacic'); ?></li>
                      </ul>
                    </div>
                    <input type="text" id="kvt_keyword_board_input" placeholder="<?php esc_attr_e('Introduce palabras clave (usa Y/O)', 'kovacic'); ?>">
                    <select id="kvt_keyword_board_country"><option value=""><?php esc_html_e('Todos los países', 'kovacic'); ?></option></select>
                    <button type="button" class="kvt-btn" id="kvt_keyword_board_search"><?php esc_html_e('Buscar', 'kovacic'); ?></button>
                  </div>
                  <div id="kvt_keyword_board_results" class="kvt-modal-list"></div>
                </div>
                <div id="kvt_ai_view" style="display:none;">
                  <div class="kvt-modal-controls">
                    <textarea id="kvt_ai_board_input" rows="6" style="width:100%;" placeholder="Describe el perfil o pega la descripción del trabajo para que la IA sugiera candidatos"></textarea>
                    <select id="kvt_ai_board_country"><option value=""><?php esc_html_e('Todos los países', 'kovacic'); ?></option></select>
                    <button type="button" class="kvt-btn" id="kvt_ai_board_search">Buscar</button>
                  </div>
                  <div id="kvt_ai_board_results" class="kvt-modal-list"></div>
                </div>
                <div id="kvt_boards_view" style="display:none;">
                  <h3><?php esc_html_e('Tableros de candidatos/clientes', 'kovacic'); ?></h3>
                  <table class="kvt-table">
                    <thead><tr><th><?php esc_html_e('Tipo', 'kovacic'); ?></th><th><?php esc_html_e('Cliente', 'kovacic'); ?></th><th><?php esc_html_e('Proceso', 'kovacic'); ?></th><th><?php esc_html_e('Candidato', 'kovacic'); ?></th><th>URL</th><th><?php esc_html_e('Acciones', 'kovacic'); ?></th></tr></thead>
                    <tbody>
                    <?php if (empty($client_links) && empty($candidate_links)) : ?>
                      <tr><td colspan="6"><?php esc_html_e('No se encontraron tableros', 'kovacic'); ?></td></tr>
                    <?php else : ?>
                      <?php foreach ($client_links as $slug => $cfg) :
                        $client  = get_term_field('name', $cfg['client'], self::TAX_CLIENT);
                        $process = get_term_field('name', $cfg['process'], self::TAX_PROCESS);
                        $url     = home_url('/view-board/' . $slug . '/');
                        $fields  = esc_attr(wp_json_encode($cfg['fields'] ?? []));
                        $steps   = esc_attr(wp_json_encode($cfg['steps'] ?? []));
                        $comments= !empty($cfg['comments']) ? '1' : '0'; ?>
                        <tr>
                          <td><?php esc_html_e('Cliente', 'kovacic'); ?></td>
                          <td><?php echo esc_html($client); ?></td>
                          <td><?php echo esc_html($process); ?></td>
                          <td>&mdash;</td>
                          <td><a href="<?php echo esc_url($url); ?>" target="_blank"><?php echo esc_html($slug); ?></a></td>
                          <td><a href="#" class="kvt-config-board" data-type="client" data-slug="<?php echo esc_attr($slug); ?>" data-client="<?php echo esc_attr($cfg['client']); ?>" data-process="<?php echo esc_attr($cfg['process']); ?>" data-fields="<?php echo $fields; ?>" data-steps="<?php echo $steps; ?>" data-comments="<?php echo $comments; ?>"><?php esc_html_e('Configurar', 'kovacic'); ?></a> | <a href="#" class="kvt-delete-board" data-type="client" data-slug="<?php echo esc_attr($slug); ?>"><?php esc_html_e('Eliminar', 'kovacic'); ?></a></td>
                        </tr>
                      <?php endforeach; ?>
                      <?php foreach ($candidate_links as $slug => $cfg) :
                        $client  = get_term_field('name', $cfg['client'], self::TAX_CLIENT);
                        $process = get_term_field('name', $cfg['process'], self::TAX_PROCESS);
                        $cand    = get_the_title($cfg['candidate']);
                        $url     = home_url('/view-board/' . $slug . '/');
                        $fields  = esc_attr(wp_json_encode($cfg['fields'] ?? []));
                        $steps   = esc_attr(wp_json_encode($cfg['steps'] ?? []));
                        $comments= !empty($cfg['comments']) ? '1' : '0'; ?>
                        <tr>
                          <td><?php esc_html_e('Candidato', 'kovacic'); ?></td>
                          <td><?php echo esc_html($client); ?></td>
                          <td><?php echo esc_html($process); ?></td>
                          <td><?php echo esc_html($cand); ?></td>
                          <td><a href="<?php echo esc_url($url); ?>" target="_blank"><?php echo esc_html($slug); ?></a></td>
                          <td><a href="#" class="kvt-config-board" data-type="candidate" data-slug="<?php echo esc_attr($slug); ?>" data-client="<?php echo esc_attr($cfg['client']); ?>" data-process="<?php echo esc_attr($cfg['process']); ?>" data-candidate="<?php echo esc_attr($cfg['candidate']); ?>" data-fields="<?php echo $fields; ?>" data-steps="<?php echo $steps; ?>" data-comments="<?php echo $comments; ?>"><?php esc_html_e('Configurar', 'kovacic'); ?></a> | <a href="#" class="kvt-delete-board" data-type="candidate" data-slug="<?php echo esc_attr($slug); ?>"><?php esc_html_e('Eliminar', 'kovacic'); ?></a></td>
                        </tr>
                      <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                  </table>
                </div>
                <div id="kvt_email_view" style="display:none;">
                  <div class="kvt-tabs" id="kvt_email_tabs">
                    <button type="button" class="kvt-tab active" data-target="compose">Enviar</button>
                    <button type="button" class="kvt-tab" data-target="sent">Enviados</button>
                    <button type="button" class="kvt-tab" data-target="templates">Plantillas</button>
                  </div>
                  <div id="kvt_email_tab_compose" class="kvt-tab-panel active">
                    <div id="kvt_email_filters" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px;">
                      <div class="kvt-filter-field">
                        <label for="kvt_email_client">Cliente</label>
                        <select id="kvt_email_client" multiple size="4">
                          <?php foreach ($clients as $c): ?>
                            <option value="<?php echo esc_attr($c->term_id); ?>"><?php echo esc_html($c->name); ?></option>
                          <?php endforeach; ?>
                        </select>
                      </div>
                      <div class="kvt-filter-field">
                        <label for="kvt_email_process">Proceso</label>
                        <select id="kvt_email_process" multiple size="4">
                          <?php foreach ($processes as $t): ?>
                            <option value="<?php echo esc_attr($t->term_id); ?>"><?php echo esc_html($t->name); ?></option>
                          <?php endforeach; ?>
                        </select>
                      </div>
                      <div class="kvt-filter-field">
                        <label for="kvt_email_status">Estado</label>
                        <select id="kvt_email_status" multiple size="4">
                          <?php foreach ($statuses as $st): ?>
                            <option value="<?php echo esc_attr($st); ?>"><?php echo esc_html($st); ?></option>
                          <?php endforeach; ?>
                        </select>
                      </div>
                      <div class="kvt-filter-field">
                        <label for="kvt_email_country">País</label>
                        <select id="kvt_email_country" multiple size="4">
                          <?php foreach ($countries as $c): ?>
                            <option value="<?php echo esc_attr($c); ?>"><?php echo esc_html($c); ?></option>
                          <?php endforeach; ?>
                        </select>
                      </div>
                      <div class="kvt-filter-field">
                        <label for="kvt_email_city">Ciudad</label>
                        <select id="kvt_email_city" multiple size="4">
                          <?php foreach ($cities as $c): ?>
                            <option value="<?php echo esc_attr($c); ?>"><?php echo esc_html($c); ?></option>
                          <?php endforeach; ?>
                        </select>
                      </div>
                      <div class="kvt-filter-field">
                        <label for="kvt_email_search">Buscar</label>
                        <input type="text" id="kvt_email_search" placeholder="Buscar...">
                      </div>
                    </div>
                    <div class="kvt-row" style="margin-bottom:10px;">
                      <button class="kvt-btn" id="kvt_email_select_all">Seleccionar todo</button>
                      <button class="kvt-btn" id="kvt_email_clear" style="margin-left:8px">Limpiar</button>
                      <span class="kvt-muted" id="kvt_email_selected" style="margin-left:8px">0 seleccionados</span>
                    </div>
                    <div class="kvt-table-wrap">
                      <table class="kvt-table">
                        <thead>
                          <tr>
                            <th></th><th>Nombre</th><th>Apellido</th><th>Email</th><th>País</th><th>Ciudad</th><th>Cliente</th><th>Proceso</th><th>Estado</th>
                          </tr>
                        </thead>
                        <tbody id="kvt_email_tbody"></tbody>
                      </table>
                    </div>
                    <div id="kvt_email_pager" class="kvt-table-pager" style="display:none;">
                      <button type="button" class="kvt-btn" id="kvt_email_prev">Anterior</button>
                      <span id="kvt_email_pageinfo"></span>
                      <button type="button" class="kvt-btn" id="kvt_email_next">Siguiente</button>
                    </div>
                    <div style="height:20px;"></div>
                    <label for="kvt_email_prompt">Describe el correo para la IA</label>
                    <textarea id="kvt_email_prompt" rows="3" class="kvt-textarea" placeholder="Ej: Invita a {{first_name}} a una entrevista para el rol {{role}} en {{client}} ubicado en {{city}}, {{country}}. Usa tono profesional."></textarea>
                    <p class="kvt-hint">Ejemplo: "Invita a {{first_name}} a una entrevista para el rol {{role}} en {{client}}". Puedes usar variables para personalizar.</p>
                    <p class="kvt-hint">Variables disponibles: {{first_name}}, {{surname}}, {{country}}, {{city}}, {{client}}, {{role}}, {{status}}, {{board}} (enlace al tablero), {{sender}} (remitente)</p>
                    <button type="button" class="kvt-btn" id="kvt_email_generate">Generar con IA</button>
                    <select id="kvt_email_template" class="kvt-input"><option value="">— Plantillas —</option></select>
                    <input type="text" id="kvt_email_subject" class="kvt-input" placeholder="Asunto">
                    <textarea id="kvt_email_body" class="kvt-textarea" rows="8" placeholder="Mensaje con {{placeholders}}"></textarea>
                    <div class="kvt-filter-field">
                      <input type="text" id="kvt_email_from_name" class="kvt-input" placeholder="Nombre remitente" value="<?php echo esc_attr($from_name_def ? $from_name_def : get_bloginfo('name')); ?>">
                      <input type="email" id="kvt_email_from_email" class="kvt-input" placeholder="Email remitente" value="<?php echo esc_attr($from_email_def ? $from_email_def : get_option('admin_email')); ?>">
                      <label for="kvt_email_use_signature" style="display:flex;align-items:center;font-weight:400;gap:4px;">
                        <input type="checkbox" id="kvt_email_use_signature" checked>
                        Incluir firma
                      </label>
                    </div>
                    <div class="kvt-row" style="margin-top:8px;">
                      <button type="button" class="kvt-btn" id="kvt_email_preview">Vista previa</button>
                      <button type="button" class="kvt-btn" id="kvt_email_send">Enviar</button>
                      <button type="button" class="kvt-btn" id="kvt_email_save_tpl">Guardar como plantilla</button>
                    </div>
                    <div id="kvt_email_status_msg"></div>
                  </div>
                  <div id="kvt_email_tab_sent" class="kvt-tab-panel">
                    <table class="kvt-table">
                      <thead><tr><th>Fecha</th><th>Asunto</th><th>Destinatarios</th></tr></thead>
                      <tbody id="kvt_email_sent_tbody"></tbody>
                    </table>
                  </div>
                  <div id="kvt_email_tab_templates" class="kvt-tab-panel">
                    <div class="kvt-row" style="margin-bottom:10px;flex-wrap:wrap;gap:8px;">
                      <input type="text" id="kvt_tpl_title" class="kvt-input" placeholder="Título">
                      <input type="text" id="kvt_tpl_subject" class="kvt-input" placeholder="Asunto">
                      <textarea id="kvt_tpl_body" class="kvt-textarea" rows="4" placeholder="Cuerpo"></textarea>
                      <button type="button" class="kvt-btn" id="kvt_tpl_save">Guardar plantilla</button>
                    </div>
                    <ul id="kvt_tpl_list"></ul>
                  </div>
                </div>
                <div id="kvt_calendar" class="kvt-calendar" style="display:none;"></div>
                <div id="kvt_mit_view" class="kvt-mit" style="display:none;">
                    <h4>Asistente MIT</h4>
                    <p id="kvt_mit_content"></p>
                    <ul id="kvt_mit_news"></ul>
                </div>
                <div id="kvt_mit_chat_view" class="kvt-mit" style="display:none;">
                    <h4>Chat con MIT</h4>
                    <div id="kvt_mit_chat_log" style="max-height:300px;overflow:auto;"></div>
                    <div class="kvt-row" style="margin-top:10px;gap:8px;">
                        <input type="text" id="kvt_mit_chat_input" class="kvt-input" placeholder="Escribe un mensaje">
                        <button type="button" class="kvt-btn" id="kvt_mit_chat_send">Enviar</button>
                    </div>
                </div>
                <div class="kvt-widgets">
                <div id="kvt_activity" class="kvt-activity">
                    <h4 class="kvt-widget-title">Actividad</h4>
                    <div class="kvt-activity-tabs">
                        <button type="button" class="kvt-activity-tab active" data-target="tasks">Tareas</button>
                        <button type="button" class="kvt-activity-tab" data-target="log">Registro</button>
                    </div>
                    <div id="kvt_activity_tasks" class="kvt-activity-content">
                        <div class="kvt-activity-columns">
                            <div class="kvt-activity-col">
                                <h4>Próximos eventos</h4>
                                <ul id="kvt_tasks_due" class="kvt-activity-list"></ul>
                                <ul id="kvt_tasks_upcoming" class="kvt-activity-list"></ul>
                                <button type="button" class="kvt-btn" id="kvt_task_open">Añadir tarea</button>
                            </div>
                            <div class="kvt-activity-col">
                                <h4>Notificaciones</h4>
                                <ul id="kvt_notifications" class="kvt-activity-list"></ul>
                            </div>
                        </div>
                    </div>
                    <div id="kvt_activity_log" class="kvt-activity-content" style="display:none;">
                        <ul id="kvt_activity_log_list" class="kvt-activity-list"></ul>
                    </div>
                </div>
                <div id="kvt_calendar_wrap" class="kvt-activity">
                    <h4 class="kvt-widget-title">Calendario</h4>
                    <div id="kvt_dashboard_calendar" class="kvt-calendar-small"></div>
                </div>
                </div>
            </div>
            <button type="button" class="kvt-btn" id="kvt_toggle_kanban" style="display:none;margin-top:20px;">Mostrar Kanban</button>
            <div id="kvt_board_wrap" class="kvt-board-wrap" style="display:none;">
                <div id="kvt_board" class="kvt-board" aria-live="polite" style="margin-top:12px;"></div>
            </div>
            </div><!-- .kvt-content -->
        </div>
        <!-- Info Modal -->
        <div class="kvt-modal" id="kvt_info_modal" style="display:none;">
          <div class="kvt-modal-content">
            <div class="kvt-modal-header">
              <h3>Información</h3>
              <button type="button" class="kvt-modal-close" id="kvt_info_close" aria-label="Cerrar"><span class="dashicons dashicons-no-alt"></span></button>
            </div>
            <div class="kvt-modal-body" id="kvt_info_body"></div>
          </div>
        </div>

        <!-- Email Preview Modal -->
        <div class="kvt-modal" id="kvt_email_preview_modal" style="display:none;">
          <div class="kvt-modal-content">
            <div class="kvt-modal-header">
              <h3>Vista previa</h3>
              <button type="button" class="kvt-modal-close" id="kvt_email_preview_close" aria-label="Cerrar"><span class="dashicons dashicons-no-alt"></span></button>
            </div>
            <div class="kvt-modal-body">
              <p><strong>Asunto:</strong> <span id="kvt_email_preview_subject"></span></p>
              <div id="kvt_email_preview_body"></div>
            </div>
          </div>
        </div>

        <!-- Modal de comentarios -->
        <div class="kvt-modal" id="kvt_feedback_modal" style="display:none;">
          <div class="kvt-modal-content">
            <div class="kvt-modal-header">
              <h3>Comentarios</h3>
              <button type="button" class="kvt-modal-close" id="kvt_feedback_close" aria-label="Cerrar"><span class="dashicons dashicons-no-alt"></span></button>
            </div>
            <div class="kvt-modal-body">
              <input type="text" class="kvt-input" id="kvt_fb_name" placeholder="Tu nombre" style="margin-bottom:8px;">
              <textarea class="kvt-input" id="kvt_fb_text" placeholder="Tu feedback"></textarea>
              <div class="row" style="margin-top:12px;"><button type="button" id="kvt_fb_save">Guardar</button></div>
            </div>
          </div>
        </div>

        <!-- Help Modal -->
 <div class="kvt-modal" id="kvt_help_modal" style="display:none;">
  <div class="kvt-modal-content">
    <div class="kvt-modal-header">
      <h3>Cómo funciona</h3>
      <button type="button" class="kvt-modal-close" id="kvt_help_close" aria-label="Cerrar">
        <span class="dashicons dashicons-no-alt"></span>
      </button>
    </div>
    <div class="kvt-modal-body">
      <p><strong>Guía de uso – Vista del Cliente</strong></p>

      <p>Siga en tiempo real el proceso de selección. Columnas:</p>
      <ul>
        <li><strong>Long list</strong>: candidatos identificados inicialmente.</li>
        <li><strong>Short list</strong>: preseleccionados tras un primer filtro.</li>
        <li><strong>Contactados</strong>: profesionales ya contactados.</li>
        <li><strong>Entrevistados</strong>: candidatos que han pasado entrevista.</li>
        <li><strong>En oferta</strong>: finalistas en fase de oferta.</li>
      </ul>

      <p>Acciones por candidato:</p>
      <ul>
        <li><strong>Icono del documento</strong>: abre y visualiza el CV.</li>
        <li><strong>Ver perfil</strong>: muestra información detallada del candidato.</li>
        <li><strong>Comentar</strong>: deje su feedback; será visible únicamente para el equipo de Kovacic Executive Talent Research.</li>
      </ul>
    </div>
  </div>
</div>

        <!-- Share Board Modal -->
        <div class="kvt-modal" id="kvt_share_modal" style="display:none;">
          <div class="kvt-modal-content">
            <div class="kvt-modal-header">
              <h3>Configurar vista cliente</h3>
              <button type="button" class="kvt-modal-close" id="kvt_share_close" aria-label="Cerrar"><span class="dashicons dashicons-no-alt"></span></button>
            </div>
            <div class="kvt-modal-body">
              <div class="kvt-share-grid">
                <div>
                  <p class="kvt-share-title">¿Qué datos del candidato quieres que sean visibles para el cliente?</p>
                  <label><input type="checkbox" id="kvt_share_fields_all" checked> Todos los campos</label>
                  <div id="kvt_share_fields"></div>
                </div>
                <div>
                  <p class="kvt-share-title">¿Qué etapas del proceso quieres que sean visibles para el cliente?</p>
                  <label><input type="checkbox" id="kvt_share_steps_all" checked> Todos los estados</label>
                  <div id="kvt_share_steps"></div>
                </div>
              </div>
              <p class="kvt-share-title">Otros ajustes</p>
              <label style="display:block;margin-top:5px;"><input type="checkbox" id="kvt_share_comments"> Permitir comentarios del cliente</label>
              <button type="button" class="kvt-btn" id="kvt_share_generate" style="margin-top:15px;">Generar enlace</button>
            </div>
          </div>
        </div>

        <div class="kvt-modal" id="kvt_stage_modal" style="display:none;">
          <div class="kvt-modal-content">
            <div class="kvt-modal-header">
              <h3>Actualizar etapa</h3>
              <button type="button" class="kvt-modal-close" id="kvt_stage_close" aria-label="Cerrar"><span class="dashicons dashicons-no-alt"></span></button>
            </div>
            <div class="kvt-modal-body">
              <form id="kvt_stage_form">
                <textarea id="kvt_stage_comment" placeholder="Comentario (opcional)" style="width:100%;min-height:80px;"></textarea>
                <p><button type="submit" class="kvt-btn">Guardar</button></p>
              </form>
            </div>
          </div>
        </div>
        <div class="kvt-modal" id="kvt_task_modal" style="display:none;">
          <div class="kvt-modal-content">
            <div class="kvt-modal-header">
              <h3>Añadir tarea</h3>
              <button type="button" class="kvt-modal-close" id="kvt_task_close" aria-label="Cerrar"><span class="dashicons dashicons-no-alt"></span></button>
            </div>
            <div class="kvt-modal-body">
              <form id="kvt_task_form">
                <select id="kvt_task_process"></select>
                <select id="kvt_task_candidate"></select>
                <input type="date" id="kvt_task_date">
                <input type="time" id="kvt_task_time">
                <input type="text" id="kvt_task_note" placeholder="Nota">
                <p><button class="kvt-btn" type="submit">Guardar</button></p>
              </form>
            </div>
          </div>
        </div>
        <!-- Modal Base -->
        <div class="kvt-modal" id="kvt_modal" style="display:none;">
          <div class="kvt-modal-content" role="dialog" aria-modal="true" aria-labelledby="kvt_modal_title">
            <div class="kvt-modal-header">
              <h3 id="kvt_modal_title">Base</h3>
              <button type="button" class="kvt-modal-close dashicons dashicons-no-alt" title="Cerrar"></button>
            </div>
            <div class="kvt-modal-body">
              <div class="kvt-tabs">
                <button type="button" class="kvt-tab active" data-target="candidates">Candidatos</button>
                <button type="button" class="kvt-tab" data-target="clients">Clientes</button>
                <button type="button" class="kvt-tab" data-target="processes">Procesos</button>
                <button type="button" class="kvt-tab" data-target="ai">Buscador IA</button>
                <button type="button" class="kvt-tab" data-target="keyword"><?php esc_html_e('Búsqueda de palabras', 'kovacic'); ?></button>
              </div>
              <div id="kvt_tab_candidates" class="kvt-tab-panel active kvt-base">
                <div class="kvt-head">
                  <h3 class="kvt-title">Base de candidatos</h3>
                  <div class="kvt-stats"><span>Total: <?php echo intval($total_candidates); ?></span><span>Últimos 7 días: <?php echo intval($recent_candidates); ?></span></div>
                  <div class="kvt-toolbar">
                    <label>Nombre
                      <input type="text" id="kvt_modal_name" placeholder="Nombre">
                    </label>
                    <label>Puesto/Empresa actual
                      <input type="text" id="kvt_modal_role" placeholder="Puesto/Empresa actual (ej: Gerente, Analista)">
                      <small class="kvt-hint">Puedes buscar varios separados por coma</small>
                    </label>
                    <label>Ubicación
                      <input type="text" id="kvt_modal_location" placeholder="País o ciudad">
                    </label>
                    <button type="button" class="kvt-btn" id="kvt_modal_assign" style="display:none;">Asignar seleccionados</button>
                    <form id="kvt_export_all_form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" target="_blank">
                      <input type="hidden" name="action" value="kvt_export">
                      <input type="hidden" name="kvt_export_nonce" value="<?php echo esc_attr(wp_create_nonce('kvt_export')); ?>">
                      <input type="hidden" name="filter_client" value="">
                      <input type="hidden" name="filter_process" value="">
                      <input type="hidden" name="format" id="kvt_export_all_format" value="xls">
                      <button type="button" class="kvt-btn" id="kvt_export_all_xls">Exportar Excel</button>
                    </form>
                  </div>
                </div>
                <div id="kvt_modal_list" class="kvt-list"></div>
                <div class="kvt-modal-pager">
                  <button type="button" class="kvt-btn kvt-secondary" id="kvt_modal_prev">Anterior</button>
                  <span id="kvt_modal_pageinfo"></span>
                  <button type="button" class="kvt-btn kvt-secondary" id="kvt_modal_next">Siguiente</button>
                </div>
              </div>
              <div id="kvt_tab_clients" class="kvt-tab-panel kvt-base">
                <div id="kvt_clients_list" class="kvt-list"></div>
              </div>
              <div id="kvt_tab_processes" class="kvt-tab-panel kvt-base">
                <div class="kvt-head">
                  <div class="kvt-toolbar">
                    <label>Estado
                      <select id="kvt_proc_status">
                        <option value="">Todos</option>
                        <option value="active">Activo</option>
                        <option value="completed">Cerrado</option>
                        <option value="closed">Cancelado</option>
                      </select>
                    </label>
                    <label>Empresa
                      <select id="kvt_proc_client">
                        <option value="">Todas</option>
                        <?php foreach ($clients as $c): ?>
                          <option value="<?php echo esc_attr($c->term_id); ?>"><?php echo esc_html($c->name); ?></option>
                        <?php endforeach; ?>
                      </select>
                    </label>
                  </div>
                </div>
                <div id="kvt_processes_list" class="kvt-list"></div>
              </div>
              <div id="kvt_tab_ai" class="kvt-tab-panel">
                <div class="kvt-modal-controls">
                  <textarea id="kvt_ai_input" rows="6" style="width:100%;" placeholder="Describe el perfil o pega la descripción del trabajo para que la IA sugiera candidatos"></textarea>
                  <select id="kvt_ai_country"><option value=""><?php esc_html_e('Todos los países', 'kovacic'); ?></option></select>
                  <button type="button" class="kvt-btn" id="kvt_ai_search">Buscar</button>
                </div>
                <div id="kvt_ai_results" class="kvt-modal-list"></div>
              </div>
              <div id="kvt_tab_keyword" class="kvt-tab-panel">
                <div class="kvt-modal-controls">
                  <div class="kvt-hint">
                    <p><?php esc_html_e('Escribe las palabras clave separadas por Y si todas deben aparecer o por O si basta con alguna.', 'kovacic'); ?></p>
                    <p><?php esc_html_e('Ejemplos:', 'kovacic'); ?></p>
                    <ul>
                      <li><?php esc_html_e('PPA Y minería Y Chile O Holanda', 'kovacic'); ?></li>
                      <li><?php esc_html_e('energía solar Y fotovoltaica', 'kovacic'); ?></li>
                      <li><?php esc_html_e('litio O cobre O níquel', 'kovacic'); ?></li>
                    </ul>
                  </div>
                  <input type="text" id="kvt_keyword_input" placeholder="<?php esc_attr_e('Introduce palabras clave (usa Y/O)', 'kovacic'); ?>">
                  <select id="kvt_keyword_country"><option value=""><?php esc_html_e('Todos los países', 'kovacic'); ?></option></select>
                  <button type="button" class="kvt-btn" id="kvt_keyword_search"><?php esc_html_e('Buscar', 'kovacic'); ?></button>
                </div>
                <div id="kvt_keyword_results" class="kvt-modal-list"></div>
              </div>
            </div>
          </div>
        </div>

        <!-- Create Candidate Modal -->
        <div class="kvt-modal" id="kvt_create_modal" style="display:none">
          <div class="kvt-modal-content">
            <div class="kvt-modal-header">
              <h3>Crear candidato</h3>
              <button type="button" class="kvt-modal-close" id="kvt_create_close" aria-label="Cerrar"><span class="dashicons dashicons-no-alt"></span></button>
            </div>
            <div class="kvt-modal-body">
              <div class="kvt-modal-controls">
                <input type="text" id="kvt_new_first" placeholder="Nombre">
                <input type="text" id="kvt_new_last" placeholder="Apellidos">
                <input type="email" id="kvt_new_email" placeholder="Email">
                <input type="text" id="kvt_new_phone" placeholder="Teléfono">
                <input type="text" id="kvt_new_country" placeholder="País">
                <input type="text" id="kvt_new_city" placeholder="Ciudad">
                <input type="text" id="kvt_new_role" placeholder="Puesto actual">
                <input type="text" id="kvt_new_company" placeholder="Empresa actual">
                <input type="text" id="kvt_new_tags" placeholder="Etiquetas">
                <input type="url" id="kvt_new_cv_url" placeholder="CV (URL)">
                <input type="file" id="kvt_new_cv_file" accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document">
                <button type="button" class="kvt-btn" id="kvt_new_cv_upload">Subir y guardar</button>
                <select id="kvt_new_client">
                  <option value="">— Cliente —</option>
                  <?php foreach ($clients as $t): ?>
                    <option value="<?php echo esc_attr($t->term_id); ?>"><?php echo esc_html($t->name); ?></option>
                  <?php endforeach; ?>
                </select>
                <select id="kvt_new_process">
                  <option value="">— Proceso —</option>
                  <?php foreach ($processes as $t): ?>
                    <option value="<?php echo esc_attr($t->term_id); ?>"><?php echo esc_html($t->name); ?></option>
                  <?php endforeach; ?>
                </select>
                <button type="button" class="kvt-btn" id="kvt_new_submit">Crear</button>
              </div>
            </div>
          </div>
        </div>
        <!-- Create Client Modal -->
        <div class="kvt-modal" id="kvt_new_client_modal" style="display:none">
          <div class="kvt-modal-content">
            <div class="kvt-modal-header">
              <h3>Crear cliente</h3>
              <button type="button" class="kvt-modal-close" id="kvt_new_client_close" aria-label="Cerrar"><span class="dashicons dashicons-no-alt"></span></button>
            </div>
            <div class="kvt-modal-body">
              <div class="kvt-tabs">
                <button type="button" class="kvt-tab active" data-target="info">Info</button>
                <button type="button" class="kvt-tab" data-target="meetings">Reuniones</button>
              </div>
              <div id="kvt_client_tab_info" class="kvt-tab-panel active">
                <div class="kvt-modal-controls">
                  <input type="text" id="kvt_client_name" placeholder="Empresa">
                  <input type="text" id="kvt_client_contact" placeholder="Persona de contacto">
                  <input type="email" id="kvt_client_email" placeholder="Email">
                  <input type="text" id="kvt_client_phone" placeholder="Teléfono">
                  <input type="text" id="kvt_client_sector" placeholder="Sector">
                  <textarea id="kvt_client_desc" placeholder="Descripción"></textarea>
                  <textarea id="kvt_client_sig_text" placeholder="Email o firma (texto)"></textarea>
                  <input type="file" id="kvt_client_sig_file" accept="image/*">
                  <button type="button" class="kvt-btn" id="kvt_client_sig_parse">Extraer datos</button>
                  <button type="button" class="kvt-btn" id="kvt_client_submit">Crear</button>
                </div>
              </div>
              <div id="kvt_client_tab_meetings" class="kvt-tab-panel">
                <div class="kvt-modal-controls">
                  <ul id="kvt_client_meetings_list"></ul>
                  <textarea id="kvt_client_meetings_modal" style="display:none"></textarea>
                  <button type="button" class="kvt-btn" id="kvt_client_add_meeting">Añadir reunión</button>
                  <button type="button" class="kvt-btn" id="kvt_client_save_meetings">Guardar</button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Add Client Meeting Modal -->
        <div class="kvt-modal" id="kvt_client_meeting_modal" style="display:none">
          <div class="kvt-modal-content">
            <div class="kvt-modal-header">
              <h3>Añadir reunión</h3>
              <button type="button" class="kvt-modal-close" id="kvt_client_meeting_close" aria-label="Cerrar"><span class="dashicons dashicons-no-alt"></span></button>
            </div>
            <div class="kvt-modal-body">
              <div class="kvt-modal-controls">
                <input type="text" id="kvt_meeting_person" placeholder="Persona">
                <input type="date" id="kvt_meeting_date">
                <textarea id="kvt_meeting_details" placeholder="Detalles"></textarea>
                <button type="button" class="kvt-btn" id="kvt_meeting_save">Guardar</button>
              </div>
            </div>
          </div>
        </div>

        <!-- Add Process Meeting Modal -->
        <div class="kvt-modal" id="kvt_process_meeting_modal" style="display:none">
          <div class="kvt-modal-content">
            <div class="kvt-modal-header">
              <h3>Añadir reunión</h3>
              <button type="button" class="kvt-modal-close" id="kvt_process_meeting_close" aria-label="Cerrar"><span class="dashicons dashicons-no-alt"></span></button>
            </div>
            <div class="kvt-modal-body">
              <div class="kvt-modal-controls">
                <input type="text" id="kvt_proc_meeting_person" placeholder="Persona">
                <input type="date" id="kvt_proc_meeting_date">
                <textarea id="kvt_proc_meeting_details" placeholder="Detalles"></textarea>
                <button type="button" class="kvt-btn" id="kvt_proc_meeting_save">Guardar</button>
              </div>
            </div>
          </div>
        </div>

        <!-- Create Process Modal -->
        <div class="kvt-modal" id="kvt_new_process_modal" style="display:none">
          <div class="kvt-modal-content">
            <div class="kvt-modal-header">
              <h3>Crear proceso</h3>
              <button type="button" class="kvt-modal-close" id="kvt_new_process_close" aria-label="Cerrar"><span class="dashicons dashicons-no-alt"></span></button>
            </div>
            <div class="kvt-modal-body">
              <div class="kvt-tabs">
                <button type="button" class="kvt-tab active" data-target="info">Info</button>
                <button type="button" class="kvt-tab" data-target="meetings">Reuniones</button>
              </div>
              <div id="kvt_process_tab_info" class="kvt-tab-panel active">
                <div class="kvt-modal-controls">
                  <input type="text" id="kvt_process_name_new" placeholder="Nombre del proceso">
                  <select id="kvt_process_client_new">
                    <option value="">— Cliente —</option>
                    <?php foreach ($clients as $t): ?>
                      <option value="<?php echo esc_attr($t->term_id); ?>"><?php echo esc_html($t->name); ?></option>
                    <?php endforeach; ?>
                  </select>
                  <input type="text" id="kvt_process_contact_new" placeholder="Persona de contacto">
                  <input type="email" id="kvt_process_email_new" placeholder="Email">
                  <textarea id="kvt_process_desc_new" placeholder="Descripción"></textarea>
                  <button type="button" class="kvt-btn" id="kvt_process_submit">Crear</button>
                </div>
              </div>
              <div id="kvt_process_tab_meetings" class="kvt-tab-panel">
                <div class="kvt-modal-controls">
                  <ul id="kvt_process_meetings_list"></ul>
                  <textarea id="kvt_process_meetings_modal" style="display:none"></textarea>
                  <button type="button" class="kvt-btn" id="kvt_process_add_meeting">Añadir reunión</button>
                  <button type="button" class="kvt-btn" id="kvt_process_save_meetings">Guardar</button>
                </div>
              </div>
            </div>
          </div>
        </div>
</div>
        <?php
        // Make maps available to JS BEFORE app script executes
        wp_add_inline_script('kvt-app', 'window.KVT_CLIENT_MAP=' . wp_json_encode($client_map) . ';', 'before');
        wp_add_inline_script('kvt-app', 'window.KVT_PROCESS_MAP=' . wp_json_encode($proc_map) . ';', 'before');
        return ob_get_clean();
    }

    /* Assets */
    public function enqueue_assets() {
        // Styles
        wp_enqueue_style('dashicons');
        wp_enqueue_style('select2','https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css',[], '4.1.0');
        $css = "
        .kvt-wrapper{max-width:1200px;margin:0 auto;background:#fff;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.06);display:flex}
        .kvt-toolbar{display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:12px}
        .kvt-filters{display:flex;gap:12px;flex-wrap:wrap;margin:12px 0;align-items:center}
        .kvt-filter-field{display:flex;gap:6px;align-items:center;font-weight:600}
        .kvt-filters input,.kvt-filters select{padding:8px 10px;border:1px solid #e5e7eb;border-radius:8px}
        .kvt-selected-info{display:flex;flex-wrap:wrap;gap:8px;margin:8px 0;padding:8px;border:1px solid #e5e7eb;border-radius:8px;background:#f1f5f9;font-size:14px}
        .kvt-selected-info span{font-weight:600}
        .kvt-selected-info span+span:before{content:'|';margin:0 4px;color:#94a3b8;font-weight:400}
        .kvt-logo{display:block;margin:0 auto 12px;max-width:300px}
        .kvt-content{flex:1;padding:16px;position:relative}
        .kvt-help{position:absolute;top:16px;right:16px;font-size:24px;color:#0A212E;cursor:pointer}
        .kvt-header{display:flex;align-items:center;margin-bottom:16px;border-bottom:1px solid #e5e7eb;padding-bottom:8px}
        .kvt-board-title{font-size:20px;font-weight:700;margin:0}
        .kvt-nav{width:200px;background:#f8fafc;border-right:1px solid #e5e7eb;padding:20px 10px;display:flex;flex-direction:column;gap:8px}
        .kvt-nav a{display:flex;align-items:center;gap:8px;padding:10px;border-radius:8px;color:#6b7280;font-weight:600;text-decoration:none}
        .kvt-nav a.active{background:#0A212E;color:#fff}
        .kvt-nav a:hover{background:#e2e8f0;color:#0A212E}
        .kvt-nav a .dashicons{font-size:20px}
        .kvt-btn{background:#0A212E;color:#fff;border:none;border-radius:10px;padding:10px 14px;cursor:pointer;font-weight:600;text-decoration:none}
        .kvt-btn:hover{opacity:.95}
          .kvt-secondary{background:#475569}
          .kvt-board{display:flex;gap:12px;overflow-x:auto;padding-bottom:6px}
        .kvt-col{min-width:260px;background:#f8fafc;border:1px solid #e5e7eb;border-radius:12px;padding:10px;flex:0 0 260px}
        .kvt-col h3{margin:0 0 8px;font-size:16px;color:#0A212E}
        .kvt-col.dragover{outline:2px dashed #0A212E; outline-offset: -6px;}
        .kvt-dropzone{min-height:60px;display:flex;flex-direction:column;gap:8px}
        .kvt-card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:10px;box-shadow:0 3px 10px rgba(0,0,0,.04);cursor:grab;overflow-wrap:anywhere;word-break:break-word}
        .kvt-card.dragging{opacity:.6}
        .kvt-card.kvt-overdue{border-color:#dc2626}
        .kvt-card .kvt-followup{font-size:12px;color:#000;margin:0;display:flex;align-items:center}
        .kvt-card.kvt-overdue .kvt-followup{color:#dc2626}
        .kvt-card .kvt-followup .dashicons{margin-right:4px;line-height:1;font-size:16px}
        .kvt-card .kvt-title{font-weight:700;margin:0 0 4px}
        .kvt-card .kvt-sub{font-size:12px;color:#64748b;margin:0}
        .kvt-card .kvt-role{font-size:12px;color:#334155;margin:0}
        .kvt-card .kvt-tags, .kvt-card-mini .kvt-tags{margin:4px 0;display:flex;gap:4px;flex-wrap:wrap}
        .kvt-card .kvt-tag, .kvt-card-mini .kvt-tag{background:#eef2f7;color:#0A212E;border:1px solid #e5e7eb;border-radius:6px;padding:2px 6px;font-size:12px}
        .kvt-card .kvt-meta{display:none}
        .kvt-card .kvt-expand{margin-top:8px;display:flex;gap:8px;flex-wrap:wrap}
        .kvt-card .kvt-expand button{background:#eef2f7;color:#0A212E;border:1px solid #e5e7eb;border-radius:8px;padding:6px 10px;cursor:pointer;font-weight:600}
        .kvt-card .kvt-panel{display:none;margin-top:8px;border-top:1px dashed #e2e8f0;padding-top:8px;max-height:70vh;overflow:auto}
        .kvt-card .kvt-panel dl{display:grid;grid-template-columns:140px 1fr;gap:6px 10px;font-size:13px}
        .kvt-card .kvt-panel dt{font-weight:700;color:#0A212E}
          .kvt-card .kvt-panel dd{margin:0}
          .kvt-card-head{display:flex;justify-content:space-between;align-items:center}
          .kvt-cv-link{font-size:18px;color:#0A212E;text-decoration:none}
          .kvt-cv-link:hover{color:#334155}
          .kvt-input{width:100%;padding:8px;border:1px solid #e5e7eb;border-radius:8px}
        .kvt-card .kvt-notes{margin-top:8px}
        .kvt-card .kvt-notes textarea{width:100%;min-height:80px;padding:8px;border:1px solid #e5e7eb;border-radius:8px}
        .kvt-card .kvt-public-notes{margin-top:8px}
        .kvt-card .kvt-public-notes textarea{width:100%;min-height:80px;padding:8px;border:1px solid #e5e7eb;border-radius:8px}
        .kvt-card .kvt-save-profile{padding:8px 10px;border-radius:8px;border:1px solid #e5e7eb;background:#0A212E;color:#fff;cursor:pointer;margin-top:6px}
        .kvt-feedback-btn{margin-left:6px;padding:2px 6px;font-size:12px;border:1px solid #e5e7eb;border-radius:6px;background:#eef2f7;color:#0A212E;cursor:pointer}
        .kvt-feedback-btn:hover{background:#e2e8f0}
        .kvt-feedback-section{margin-top:16px}
        .kvt-feedback-list{list-style:none;margin:0;padding:0}
        .kvt-feedback-list li{margin-bottom:8px}
        .kvt-empty{padding:16px;color:#475569;font-style:italic}
        .kvt-delete{background:none !important;border:none !important;color:#b91c1c !important;font-size:18px;line-height:1;cursor:pointer;padding:0}
        .kvt-delete:hover{color:#7f1d1d !important}
        .kvt-delete.dashicons{vertical-align:middle}
        .kvt-edit{background:none !important;border:none !important;color:#0A212E;font-size:18px;line-height:1;cursor:pointer;padding:0}
        .kvt-edit:hover{color:#334155}
        .kvt-edit.dashicons{vertical-align:middle}
        .kvt-main{display:grid;grid-template-columns:1fr;gap:16px;align-items:start}
        .kvt-widgets{display:flex;flex-wrap:wrap;gap:16px;width:100%;align-items:flex-start;align-content:flex-start}
        .kvt-table-wrap{margin-top:16px;overflow:auto;border:1px solid #e5e7eb;border-radius:12px}
        #kvt_table_wrap{width:100%}
        .kvt-calendar{width:100%;border:1px solid #e5e7eb;border-radius:12px;padding:8px;margin-top:16px}
        .kvt-calendar-small{flex:0 0 100%;border:1px solid #e5e7eb;border-radius:12px;padding:8px;max-width:750px}
        .kvt-mit{width:100%;border:1px solid #e5e7eb;border-radius:12px;padding:16px;margin-top:16px}
        #kvt_mit_chat_log{display:flex;flex-direction:column;gap:4px}
        #kvt_mit_chat_log p{margin:0;padding:6px 10px;border-radius:8px;max-width:80%;white-space:pre-wrap;word-break:break-word}
        #kvt_mit_chat_log p.user{align-self:flex-end;background:#e0f2fe;text-align:right}
        #kvt_mit_chat_log p.assistant{align-self:flex-start;background:#f1f5f9}
        .kvt-cal-head{display:grid;grid-template-columns:repeat(7,1fr);text-align:center;font-weight:600}
        .kvt-cal-grid{display:grid;grid-template-columns:repeat(7,1fr);text-align:center}
        .kvt-cal-cell{min-height:80px;border:1px solid #e5e7eb;padding:4px;position:relative}
        .kvt-cal-day{font-size:12px;color:#6b7280;position:absolute;top:4px;right:4px}
        .kvt-cal-event{display:block;margin-top:16px;font-size:12px;text-align:left}
        .kvt-cal-event.manual{font-weight:700;color:#000}
        .kvt-cal-event.suggested{font-style:italic;color:#6b7280}
        .kvt-cal-cell.has-event{background:#f1f5f9}
        .kvt-cal-cell.today{border:2px solid #000;font-weight:700}
        .kvt-cal-cell.today:after{content:'\2192';position:absolute;top:2px;right:4px;color:#000;font-weight:700}
        .kvt-cal-controls{display:flex;align-items:center;justify-content:space-between;margin-bottom:8px}
        .kvt-cal-nav{display:flex;gap:4px}
        .kvt-cal-add{display:flex;gap:8px;margin-bottom:8px;flex-wrap:wrap}
        .kvt-cal-add label{display:flex;flex-direction:column;font-size:12px}
        .kvt-cal-event.done{text-decoration:line-through;color:#9ca3af}
        .kvt-cal-remove{background:none;border:0;color:#ef4444;margin-left:4px;cursor:pointer}
        .kvt-cal-accept,.kvt-cal-reject{background:none;border:0;margin-left:4px;cursor:pointer;font-size:12px}
        .kvt-cal-accept{color:#16a34a}
        .kvt-cal-reject{color:#dc2626}
        .kvt-mit-btn{background:#0A212E;color:#fff;border:1px solid #0A212E;border-radius:4px;padding:2px 8px;font-weight:600}
        .kvt-mit-btn.loading{opacity:.7}
        .kvt-mit-btn.loading:after{content:'';display:inline-block;width:12px;height:12px;border:2px solid currentColor;border-right-color:transparent;border-radius:50%;animation:kvt-spin 1s linear infinite;margin-left:6px;vertical-align:middle}
        #kvt_mit_detail{position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);display:none;align-items:center;justify-content:center;z-index:1000}
        #kvt_mit_detail_box{background:#fff;padding:20px;border-radius:8px;max-width:500px;width:90%;position:relative}
        #kvt_mit_detail_box h4{margin-top:0}
        #kvt_mit_detail_close{position:absolute;top:8px;right:8px;background:none;border:0;font-size:16px;cursor:pointer}
        #kvt_table{width:100%;border-collapse:separate;border-spacing:0;table-layout:fixed}
        #kvt_table thead th{position:sticky;top:0;background:#f8fafc;color:#0A212E;padding:10px;border-bottom:1px solid #e5e7eb;text-align:left;font-weight:600}
        #kvt_table td{padding:8px;border-bottom:1px solid #e5e7eb;overflow-wrap:anywhere;word-break:break-word}
        #kvt_table tbody tr:hover{background:#f1f5f9}
        .kvt-ats-bar{display:flex;gap:8px;align-items:center;padding:8px}
        .kvt-ats-bar label{font-weight:600}
        .kvt-activity{border:1px solid #e5e7eb;border-radius:12px;padding:8px;overflow:auto;flex:0 1 300px;align-self:flex-start}
        #kvt_activity{flex:0 1 800px}
        #kvt_calendar_wrap{flex:0 1 750px}
        .kvt-widget-title{margin:0 0 8px;font-size:15px;font-weight:600;border-bottom:1px solid #e5e7eb;padding-bottom:4px}
        #kvt_table tbody tr:nth-child(even){background:#f1f5f9}
        #kvt_table tbody tr:nth-child(odd){background:#fff}
        #kvt_email_tbody tr:nth-child(even){background:#f1f5f9}
        #kvt_email_tbody tr:nth-child(odd){background:#fff}
        #kvt_email_filters .kvt-filter-field{display:flex;flex-direction:column}
        #kvt_email_filters select,#kvt_email_filters input{min-width:500px;width:500px}
        .kvt-base .kvt-row:nth-child(even){background:#f1f5f9}
        .kvt-base .kvt-row:nth-child(odd){background:#fff}
        .kvt-active-days{font-size:14px;font-weight:600}
        .kvt-hint{display:block;font-size:12px;color:#6b7280;margin-top:4px}
        .kvt-activity-tabs{display:flex;gap:8px;margin-bottom:8px}
        .kvt-activity-tab{flex:1;padding:6px 8px;border:1px solid #e5e7eb;border-radius:8px;background:#f1f5f9;cursor:pointer}
        .kvt-activity-tab.active{background:#0A212E;color:#fff}
        .kvt-activity-columns{display:flex;gap:16px}
        .kvt-activity-col{flex:1}
        .kvt-activity-col h4{margin:8px 0;font-size:14px}
        .kvt-activity-list{list-style:none;margin:0;padding-left:16px;font-size:13px}
        .kvt-activity-list li{margin-bottom:4px}
        .kvt-ats-bar input,.kvt-ats-bar select{padding:8px;border:1px solid #e5e7eb;border-radius:8px}
        .kvt-stage-cell{display:flex;align-items:center;font-size:12px;flex-wrap:nowrap}
        .kvt-stage-step{display:inline-flex;align-items:center;justify-content:center;width:120px;flex:0 0 120px;padding:4px 12px;background:#e5e7eb;color:#6b7280;white-space:nowrap;box-sizing:border-box;border:none;cursor:pointer}
        .kvt-stage-step.done{background:#22c55e;color:#fff}
        .kvt-stage-step.current{background:#3b82f6;color:#fff}
        .kvt-stage-overview{margin:8px;padding:0 8px;font-size:14px}
        .kvt-name-icon{margin-left:4px;font-size:14px;vertical-align:middle;cursor:default}
        .kvt-name-icon.kvt-alert{color:#dc2626;font-weight:700;font-size:18px}
        .kvt-name-icon.dashicons-clock{color:#000}
        .kvt-name-icon.dashicons-clock.overdue{color:#dc2626}
        .kvt-task-done,.kvt-task-delete{margin-left:8px;cursor:pointer}
        .kvt-task-done{color:#16a34a}
        .kvt-task-delete{color:#dc2626}
        .kvt-row-remove{color:#000;background:none;border-radius:0;padding:0;margin-right:4px;font-size:14px;cursor:pointer;vertical-align:middle}
        .kvt-profile-activity{margin-bottom:16px;font-size:13px}
        .kvt-profile-activity ul{list-style:disc;margin-left:20px}
        .kvt-board-wrap{margin-top:40px}
        .kvt-modal{position:fixed;inset:0;background:rgba(2,6,23,.5);display:flex;align-items:center;justify-content:center;z-index:9999}
        .kvt-modal-content{background:#fff;max-width:980px;width:95%;border-radius:12px;box-shadow:0 15px 40px rgba(0,0,0,.2)}
        #kvt_modal .kvt-modal-content{width:80vw;height:80vh;max-width:80vw;max-height:80vh;resize:both;overflow:auto}
        #kvt_info_modal .kvt-modal-content{max-height:90vh;overflow:auto}
        .kvt-modal-header{display:flex;justify-content:space-between;align-items:center;padding:12px 16px;border-bottom:1px solid #e5e7eb}
        .kvt-modal-body{padding:12px 16px}
        .kvt-modal-close{background:none;border:none;cursor:pointer}
        .kvt-tabs{display:flex;gap:8px;margin-bottom:12px}
        .kvt-tabs button{background:#eef2f7;color:#0A212E;border:1px solid #e5e7eb;border-radius:8px;padding:8px 12px;cursor:pointer}
        .kvt-tabs button.active{background:#0A212E;color:#fff}
        .kvt-tab-panel{display:none}
        .kvt-tab-panel.active{display:block}
        #kvt_info_modal .kvt-profile-cols{display:flex;gap:20px;flex-wrap:wrap}
        #kvt_info_modal .kvt-profile-col{flex:1;min-width:220px}
        #kvt_info_modal .kvt-notes-list{list-style:disc;margin-left:20px;margin-bottom:10px}
        #kvt_info_modal .kvt-notes-list li{margin-bottom:4px}
        .kvt-modal-controls{display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:12px}
        .kvt-modal-controls select,.kvt-modal-controls input{padding:8px 10px;border:1px solid #e5e7eb;border-radius:8px}
        .kvt-modal-list{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:10px;max-height:420px;overflow:auto}
        #kvt_modal .kvt-modal-list{max-height:calc(80vh - 200px)}
        .kvt-card-mini{border:1px solid #e5e7eb;border-radius:10px;padding:10px}
          .kvt-card-mini h4{margin:0 0 6px}
          .kvt-mini-panel{display:none;margin-top:8px;border-top:1px dashed #e2e8f0;padding-top:8px}
          .kvt-mini-actions{display:flex;gap:8px;margin-top:8px}
        .kvt-ai-meta{margin:.2em 0;color:var(--muted);font-style:italic}
        .kvt-ai-summary{margin:.2em 0;color:#64748b}
        .kvt-loading{display:flex;align-items:center;gap:8px;color:#475569}
          .kvt-loading:before{content:'';width:16px;height:16px;border:2px solid #e5e7eb;border-top-color:#0A212E;border-radius:50%;animation:kvt-spin 1s linear infinite}
          @keyframes kvt-spin{to{transform:rotate(360deg)}}
          .kvt-modal-pager{display:flex;gap:10px;align-items:center;justify-content:flex-end;margin-top:10px}
          .kvt-table-pager{display:flex;gap:10px;align-items:center;justify-content:flex-end;padding:8px}
          .kvt-share-grid{display:flex;gap:20px}
            .kvt-share-grid>div{flex:1}
          .kvt-share-title{font-weight:600;margin-bottom:6px}
          .kvt-config-client{background:none;border:none;cursor:pointer;margin-left:8px}
          .kvt-config-client .dashicons{vertical-align:middle}
          :root{--ink:#0A212E;--muted:#6B7280;--line:#E5E7EB;--bg:#FFFFFF;--accent:#0A212E;--radius:8px;--shadow:0 6px 20px rgba(10,33,46,.06)}
          .kvt-base *{box-sizing:border-box}
          .kvt-base{font-family:ui-sans-serif,system-ui,-apple-system,'Segoe UI',Roboto,Helvetica,Arial;color:var(--ink);background:var(--bg)}
          .kvt-base a{color:var(--accent);text-decoration:none}
          .kvt-base a:hover{text-decoration:underline}
          .kvt-base .kvt-head{position:sticky;top:70px;background:#fff;z-index:10;padding:12px 0 16px;border-bottom:1px solid var(--line)}
          #kvt_modal .kvt-base .kvt-head{top:0}
          .kvt-base .kvt-title{font-weight:700;font-size:18px;margin:0 0 12px}
          .kvt-base .kvt-toolbar{display:flex;gap:8px;flex-wrap:wrap}
          .kvt-base .kvt-toolbar select,.kvt-base .kvt-toolbar input{padding:8px 10px;border:1px solid var(--line);border-radius:8px}
          .kvt-base .kvt-btn{display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border:1px solid var(--line);border-radius:8px;background:#fff;cursor:pointer;color:var(--ink)}
          .kvt-base .kvt-btn:hover{box-shadow:var(--shadow)}
          .kvt-base .kvt-list{margin-top:16px;border-top:1px solid var(--line);max-height:420px;overflow:auto}
          #kvt_modal .kvt-base .kvt-list{max-height:calc(90vh - 200px)}
          .kvt-base .kvt-card-mini{border:none;padding:0;border-radius:0}
          .kvt-base .kvt-row{display:grid;grid-template-columns:1fr auto;gap:12px;align-items:center;padding:12px 0;border-bottom:1px solid var(--line)}
          .kvt-base .kvt-row.with-check{grid-template-columns:28px 1fr auto}
          .kvt-base .kvt-check{display:flex;align-items:center;justify-content:center}
          .kvt-base .kvt-row:hover{background:rgba(10,33,46,.02)}
          .kvt-base .kvt-name{font-weight:600}
          .kvt-base .kvt-sub{color:var(--muted);font-size:14px;overflow:hidden;text-overflow:ellipsis}
          .kvt-base .kvt-meta{display:flex;align-items:center;gap:10px;white-space:nowrap}
          .kvt-base .kvt-mini-panel{margin:8px 0}
          .kvt-base .kvt-stats{font-size:14px;color:var(--muted);margin-bottom:8px;display:flex;gap:12px}
          @media(max-width:720px){.kvt-base .kvt-row{grid-template-columns:1fr}.kvt-base .kvt-meta{grid-column:1;justify-content:flex-start;margin-top:4px}.kvt-base .kvt-head{top:120px}#kvt_modal .kvt-base .kvt-head{top:0}}
        ";
        wp_register_style('kvt-style', false);
        wp_enqueue_style('kvt-style');
        wp_add_inline_style('kvt-style', $css);

        $slug = isset($_GET['kvt_board']) ? sanitize_text_field($_GET['kvt_board']) : '';
        $all_links = get_option('kvt_client_links', []);
        $candidate_links = get_option('kvt_candidate_links', []);
        $link_cfg = [];
        $is_client_board = false;
        $is_candidate_board = false;
        if ($slug && isset($all_links[$slug])) {
            $link_cfg = $all_links[$slug];
            $is_client_board = true;
        } elseif ($slug && isset($candidate_links[$slug])) {
            $link_cfg = $candidate_links[$slug];
            $is_candidate_board = true;
        }
        $has_share_link = $is_client_board || $is_candidate_board;
        if ((is_user_logged_in() && current_user_can('edit_posts')) || $has_share_link) {
            // PDF.js and Tesseract.js for client-side text extraction
            wp_enqueue_script(
                'select2',
                'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js',
                ['jquery'],
                '4.1.0',
                true
            );
            wp_enqueue_script(
                'pdfjs',
                'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js',
                [],
                '3.11.174',
                true
            );
            wp_add_inline_script('pdfjs', 'window["pdfjs-dist/build/pdf"] && (window.pdfjsLib = window["pdfjs-dist/build/pdf"]);', 'after');

            wp_enqueue_script(
                'tesseract',
                'https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js',
                [],
                '5.0.0',
                true
            );

            // Register a tiny empty script handle and attach our inlines to it, to avoid theme collisions
            wp_register_script('kvt-app', '', ['pdfjs','tesseract','jquery','select2'], null, true);
            wp_enqueue_script('kvt-app');

            // Inline constants BEFORE app
        $statuses = $this->get_statuses();
        $sel_steps = $has_share_link ? array_map('sanitize_text_field', (array) ($link_cfg['steps'] ?? [])) : [];
        if ($has_share_link && $sel_steps) {
            $statuses = array_values(array_intersect($statuses, $sel_steps));
        }
        $columns  = $this->get_columns();
        $fields   = $has_share_link ? array_map('sanitize_text_field', (array) ($link_cfg['fields'] ?? [])) : [];
        wp_add_inline_script('kvt-app', 'const KVT_STATUSES='.wp_json_encode($statuses).';', 'before');
        wp_add_inline_script('kvt-app', 'const KVT_COLUMNS='.wp_json_encode($columns).';',  'before');
        $countries = $this->get_candidate_countries();
        wp_add_inline_script('kvt-app', 'const KVT_COUNTRIES='.wp_json_encode($countries).';', 'before');
        wp_add_inline_script('kvt-app', 'const KVT_AJAX="'.esc_js(admin_url('admin-ajax.php')).'";', 'before');
        wp_add_inline_script('kvt-app', 'const KVT_HOME="'.esc_js(home_url('/view-board/')).'";', 'before');
        wp_add_inline_script('kvt-app', 'const KVT_NONCE="'.esc_js(wp_create_nonce('kvt_nonce')).'";', 'before');
        wp_add_inline_script('kvt-app', 'const KVT_MIT_NONCE="'.esc_js(wp_create_nonce('kvt_mit')).'";', 'before');
        $signature = (string) get_option(self::OPT_SMTP_SIGNATURE, '');
        $def_from_name = get_option(self::OPT_FROM_NAME, '');
        if (!$def_from_name) $def_from_name = get_bloginfo('name');
        $def_from_email = get_option(self::OPT_FROM_EMAIL, '');
        if (!$def_from_email) $def_from_email = get_option('admin_email');
        $templates = $this->get_email_templates();
        $sent_emails = get_option(self::OPT_EMAIL_LOG, []);
        wp_add_inline_script('kvt-app', 'const KVT_SIGNATURE='.wp_json_encode($signature).';const KVT_FROM_NAME='.wp_json_encode($def_from_name).';const KVT_FROM_EMAIL='.wp_json_encode($def_from_email).';let KVT_TEMPLATES='.wp_json_encode($templates).';let KVT_SENT_EMAILS='.wp_json_encode($sent_emails).';', 'before');
        wp_add_inline_script('kvt-app', 'const KVT_CLIENT_VIEW='.($has_share_link?'true':'false').';', 'before');
        wp_add_inline_script('kvt-app', 'const KVT_ALLOWED_FIELDS='.wp_json_encode($fields).';', 'before');
        wp_add_inline_script('kvt-app', 'const KVT_ALLOWED_STEPS='.wp_json_encode($sel_steps).';', 'before');
        wp_add_inline_script('kvt-app', 'const KVT_ALLOW_COMMENTS='.(!empty($link_cfg['comments'])?'true':'false').';', 'before');
        wp_add_inline_script('kvt-app', 'const KVT_CLIENT_SLUG="'.esc_js($slug).'";', 'before');
        wp_add_inline_script('kvt-app', 'const KVT_IS_ADMIN='.((is_user_logged_in() && current_user_can('edit_posts'))?'true':'false').';', 'before');
        wp_add_inline_script('kvt-app', 'const KVT_CANDIDATE_VIEW='.($is_candidate_board?'true':'false').';', 'before');
        $cand_id = $is_candidate_board ? (int)($link_cfg['candidate'] ?? 0) : 0;
        wp_add_inline_script('kvt-app', 'const KVT_CANDIDATE_ID='.$cand_id.';', 'before');
        $user = wp_get_current_user();
        $display = ($user && $user->exists()) ? $user->display_name : '';
        wp_add_inline_script('kvt-app', 'const KVT_CURRENT_USER="'.esc_js($display).'";', 'before');
        $link_map = [];
        foreach ($all_links as $s=>$cfg) {
            $c = isset($cfg['client']) ? (int)$cfg['client'] : 0;
            $p = isset($cfg['process']) ? (int)$cfg['process'] : 0;
            if ($c && $p) $link_map[$c.'|'.$p] = $s;
        }
        wp_add_inline_script('kvt-app', 'const KVT_CLIENT_LINKS='.wp_json_encode($link_map).';', 'before');
        if ($has_share_link) {
            $cid = isset($link_cfg['client']) ? (int) $link_cfg['client'] : 0;
            $pid = isset($link_cfg['process']) ? (int) $link_cfg['process'] : 0;
            wp_add_inline_script('kvt-app', 'const KVT_CLIENT_ID='.$cid.';', 'before');
            wp_add_inline_script('kvt-app', 'const KVT_PROCESS_ID='.$pid.';', 'before');
        }
        wp_add_inline_script('kvt-app', 'const KVT_BULKREADER_URL="'.esc_url(admin_url('admin.php?page=kt-abm')).'";', 'before');

            // App JS
            $js = <<<'JS'
function kvtInit(){
  const el = (sel, root=document)=>root.querySelector(sel);
  const els = (sel, root=document)=>Array.from(root.querySelectorAll(sel));
  const esc = (s)=>String(s||'').replace(/[&<>"']/g, m=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;' }[m]));
  const escAttr = esc;
  const fixUnicode = (s)=>{ try{ return decodeURIComponent(escape(String(s||''))); }catch(e){ return s; } };
  const urlParams = new URLSearchParams(location.search);
  const CLIENT_VIEW = typeof KVT_CLIENT_VIEW !== 'undefined' && KVT_CLIENT_VIEW;
  const CANDIDATE_VIEW = typeof KVT_CANDIDATE_VIEW !== 'undefined' && KVT_CANDIDATE_VIEW;
  const CANDIDATE_ID = typeof KVT_CANDIDATE_ID !== 'undefined' ? parseInt(KVT_CANDIDATE_ID,10) : 0;
  let ALLOWED_FIELDS = Array.isArray(KVT_ALLOWED_FIELDS) ? KVT_ALLOWED_FIELDS : [];
  const CLIENT_ID = typeof KVT_CLIENT_ID !== 'undefined' ? String(KVT_CLIENT_ID) : '';
  const PROCESS_ID = typeof KVT_PROCESS_ID !== 'undefined' ? String(KVT_PROCESS_ID) : '';
  let ALLOWED_STEPS = Array.isArray(KVT_ALLOWED_STEPS) ? KVT_ALLOWED_STEPS : [];
  let ALLOW_COMMENTS = typeof KVT_ALLOW_COMMENTS !== 'undefined' && KVT_ALLOW_COMMENTS;
  const CLIENT_SLUG = typeof KVT_CLIENT_SLUG !== 'undefined' ? KVT_CLIENT_SLUG : '';
  const IS_ADMIN = typeof KVT_IS_ADMIN !== 'undefined' && KVT_IS_ADMIN;
  const CLIENT_LINKS = (typeof KVT_CLIENT_LINKS === 'object' && KVT_CLIENT_LINKS) ? KVT_CLIENT_LINKS : {};
  const COUNTRY_OPTIONS = Array.isArray(KVT_COUNTRIES) ? KVT_COUNTRIES : [];
  let EDIT_SLUG = '';

  if (CLIENT_VIEW) {
    const actToggle = el('#k-toggle-activity');
    if (actToggle) actToggle.style.display = 'none';
    const sideHead = el('#k-sidebar .k-sidehead');
    if (sideHead) sideHead.textContent = 'Historial';
    const sideActions = el('#k-sidebar .k-sideactions');
    if (sideActions) sideActions.style.display = 'none';
    const sidebar = el('#k-sidebar');
    if (sidebar) sidebar.style.display = 'none';
  }

  const helpBtn = el('.kvt-help');
  const helpModal = el('#kvt_help_modal');
  const helpClose = el('#kvt_help_close');
  if (helpBtn && helpModal) {
    helpBtn.addEventListener('click', () => { helpModal.style.display = 'flex'; });
    if (helpClose) helpClose.addEventListener('click', () => { helpModal.style.display = 'none'; });
  }

  const viewLinks = els('.kvt-nav a[data-view]');
  if (viewLinks.length){
    viewLinks.forEach(link=>{
      link.addEventListener('click',e=>{
        e.preventDefault();
        viewLinks.forEach(n=>n.classList.remove('active'));
        link.classList.add('active');
        showView(link.dataset.view);
      });
    });
  }
  const openProcesses = el('#kvt_open_processes');
  openProcesses && openProcesses.addEventListener('click', ()=>{ switchBoardTab('processes'); });
  const openClients = el('#kvt_open_clients');
  openClients && openClients.addEventListener('click', ()=>{ switchBoardTab('clients'); });

  async function extractPdfWithPDFjs(file){
    if (!window.pdfjsLib) return '';
    try {
      const buf = await file.arrayBuffer();
      const pdf = await window.pdfjsLib.getDocument({ data: buf }).promise;
      let full = '';
      for (let p=1; p<=pdf.numPages; p++){
        const page = await pdf.getPage(p);
        const content = await page.getTextContent();
        const strings = content.items.map(it=>it.str);
        full += strings.join(' ') + '\n\n';
      }
      return full.trim();
    } catch(e){ return ''; }
  }

  async function ocrPdfWithTesseract(file){
    if (!window.Tesseract || !window.pdfjsLib) return '';
    const buf = await file.arrayBuffer();
    const pdf = await window.pdfjsLib.getDocument({ data: buf }).promise;
    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d');
    let ocrText = '';
    for (let p=1; p<=pdf.numPages; p++){
      const page = await pdf.getPage(p);
      const viewport = page.getViewport({ scale: 2.0 });
      canvas.width = viewport.width;
      canvas.height = viewport.height;
      await page.render({ canvasContext: ctx, viewport }).promise;
      const dataURL = canvas.toDataURL('image/png');
      try {
        const { data: { text } } = await Tesseract.recognize(dataURL, 'spa+eng', { logger: ()=>{} });
        if (text) ocrText += text + '\n\n';
      } catch(e){}
      ctx.clearRect(0,0,canvas.width,canvas.height);
    }
    return ocrText.trim();
  }

  const board = el('#kvt_board');
  if (!board) return;

  const tableWrap = el('#kvt_table_wrap');
  const tHead = el('#kvt_table_head');
  const tBody = el('#kvt_table_body');
  const searchInput = el('#kvt_search');
  const stageSelect = el('#kvt_stage_filter');
  const addCandidate = el('#k-add-candidate');
  const addCandidateTable = el('#kvt_add_candidate_table_btn');
  const boardBase   = el('#kvt_board_base');
  const boardList   = el('#kvt_board_list');
  const boardName   = el('#kvt_board_name');
  const boardRole   = el('#kvt_board_role');
  const boardLoc    = el('#kvt_board_location');
  const boardAssign = el('#kvt_board_assign');
  const boardPrev   = el('#kvt_board_prev');
  const boardNext   = el('#kvt_board_next');
  const boardPage   = el('#kvt_board_pageinfo');
  const boardExportXls = el('#kvt_board_export_all_xls');
  const boardExportFormat = el('#kvt_board_export_all_format');
  const boardExportAllForm = el('#kvt_board_export_all_form');
  const navLoadRoles = el('#kvt_nav_load_roles');
  const activityDue = el('#kvt_tasks_due');
  const activityUpcoming = el('#kvt_tasks_upcoming');
  const activityNotify = el('#kvt_notifications');
  const activityLog = el('#kvt_activity_log_list');
  const calendarSmall = el('#kvt_dashboard_calendar');
  const calendarMiniWrap = el('#kvt_calendar_wrap');
  const activityTabs = document.querySelectorAll('.kvt-activity-tab');
  const activityViews = document.querySelectorAll('.kvt-activity-content');
  const overview = el('#kvt_stage_overview');
  const atsBar   = el('#kvt_ats_bar');
  const btnTaskOpen = el('#kvt_task_open');
  const taskModalWrap = el('#kvt_task_modal');
  const taskClose = el('#kvt_task_close');
  const taskForm = el('#kvt_task_form');
  const taskProcess = el('#kvt_task_process');
  const taskCandidate = el('#kvt_task_candidate');
  const taskDate = el('#kvt_task_date');
  const taskTime = el('#kvt_task_time');
  const taskNote = el('#kvt_task_note');
  const stageModal = el('#kvt_stage_modal');
  const stageClose = el('#kvt_stage_close');
  const stageForm = el('#kvt_stage_form');
  const stageComment = el('#kvt_stage_comment');
  let stageId = '';
  let stageNext = '';

  const filtersBar = el('#kvt_filters_bar');
  const calendarWrap = el('#kvt_calendar');
  const mitWrap = el('#kvt_mit_view');
  const keywordBoard = el('#kvt_keyword_view');
  const aiBoard = el('#kvt_ai_view');
  const boardsView = el('#kvt_boards_view');
  const emailView = el('#kvt_email_view');
  const emailClient = el('#kvt_email_client');
  const emailProcess = el('#kvt_email_process');
  const emailStatusSel = el('#kvt_email_status');
  const emailCountry = el('#kvt_email_country');
  const emailCity = el('#kvt_email_city');
  const emailSearch = el('#kvt_email_search');
  const emailSelectAll = el('#kvt_email_select_all');
  const emailClear = el('#kvt_email_clear');
  const emailTbody = el('#kvt_email_tbody');
  const emailSelInfo = el('#kvt_email_selected');
  const emailPrompt = el('#kvt_email_prompt');
  const emailGenerate = el('#kvt_email_generate');
  const emailSubject = el('#kvt_email_subject');
  const emailBody = el('#kvt_email_body');
  const emailFromName = el('#kvt_email_from_name');
  const emailFromEmail = el('#kvt_email_from_email');
  const emailUseSig = el('#kvt_email_use_signature');
  const emailSend = el('#kvt_email_send');
  const emailSaveTplBtn = el('#kvt_email_save_tpl');
  const emailStatusMsg = el('#kvt_email_status_msg');
  const emailPager = el('#kvt_email_pager');
  const emailPrev = el('#kvt_email_prev');
  const emailNext = el('#kvt_email_next');
  const emailPageInfo = el('#kvt_email_pageinfo');
  const emailPreviewBtn = el('#kvt_email_preview');
  const emailPrevModal = el('#kvt_email_preview_modal');
  const emailPrevSubject = el('#kvt_email_preview_subject');
  const emailPrevBody = el('#kvt_email_preview_body');
  const emailPrevClose = el('#kvt_email_preview_close');
  const emailTabs = els('#kvt_email_tabs .kvt-tab');
  const emailTplSelect = el('#kvt_email_template');
  const tplTitle = el('#kvt_tpl_title');
  const tplSubject = el('#kvt_tpl_subject');
  const tplBody = el('#kvt_tpl_body');
  const tplSave = el('#kvt_tpl_save');
  const tplList = el('#kvt_tpl_list');
  const sentTbody = el('#kvt_email_sent_tbody');
  const mitContent = el('#kvt_mit_content');
  const mitNews = el('#kvt_mit_news');
  const mitChatWrap = el('#kvt_mit_chat_view');
  const mitChatLog = el('#kvt_mit_chat_log');
  const mitChatInput = el('#kvt_mit_chat_input');
  const mitChatSend = el('#kvt_mit_chat_send');
  const activityWrap = el('#kvt_activity');
  const boardWrap    = el('#kvt_board_wrap');
  const widgetsWrap  = el('.kvt-widgets');
  const toggleKanban = el('#kvt_toggle_kanban');

  const selClient  = el('#kvt_client');
  const selProcess = el('#kvt_process');
  const btnXLS     = el('#kvt_export_xls');
  const btnAllXLS  = el('#kvt_export_all_xls');
  const exportAllForm   = el('#kvt_export_all_form');
  const exportAllFormat = el('#kvt_export_all_format');
  const btnShare   = el('#kvt_share_board');
  const shareModal = el('#kvt_share_modal');
  const shareClose = el('#kvt_share_close');
  const shareFieldsWrap = el('#kvt_share_fields');
    const shareStepsWrap  = el('#kvt_share_steps');
    const shareFieldsAll  = el('#kvt_share_fields_all');
    const shareStepsAll   = el('#kvt_share_steps_all');
    const shareGenerate   = el('#kvt_share_generate');
    const shareComments   = el('#kvt_share_comments');
    const selInfo        = el('#kvt_selected_info');
    const boardProcInfo  = el('#kvt_board_proc_info');
  const tablePager = el('#kvt_table_pager');
  const tablePrev  = el('#kvt_table_prev');
  const tableNext  = el('#kvt_table_next');
  const tablePage  = el('#kvt_table_pageinfo');
  let currentPage = 1;
  let totalPages = 1;
  let allRows = [];
  let calendarEvents = [];
  let dragIdx = null;
  let calMonth = (new Date()).getMonth();
  let calYear  = (new Date()).getFullYear();
  let shareMode = 'client';
  let selectedCandidateId = CANDIDATE_VIEW ? CANDIDATE_ID : 0;
  let selectedCandidateIds = [];
  let forceSelect = false;

  let emailCandidates = [];
  let emailSelected = new Set();
  let emailPageNum = 1;
  let emailPageTotal = 1;
  if (typeof KVT_TEMPLATES === 'undefined' || !Array.isArray(KVT_TEMPLATES)) KVT_TEMPLATES = [];
  if (typeof KVT_SENT_EMAILS === 'undefined' || !Array.isArray(KVT_SENT_EMAILS)) KVT_SENT_EMAILS = [];

  function populateTemplateSelect(){
    if(!emailTplSelect) return;
    emailTplSelect.innerHTML='<option value="">— Plantillas —</option>';
    KVT_TEMPLATES.forEach(t=>{
      const o=document.createElement('option');
      o.value=t.id; o.textContent=t.title; emailTplSelect.appendChild(o);
    });
  }

  function renderTplList(){
    if(!tplList) return;
    tplList.innerHTML='';
    KVT_TEMPLATES.forEach(t=>{
      const li=document.createElement('li');
      li.textContent=t.title+' ';
      const edit=document.createElement('button');
      edit.textContent='Editar';
      edit.addEventListener('click',()=>editTemplate(t.id));
      li.appendChild(edit);
      const del=document.createElement('button');
      del.textContent='Eliminar';
      del.dataset.id=t.id;
      del.addEventListener('click',()=>deleteTemplate(t.id));
      li.appendChild(del);
      tplList.appendChild(li);
    });
  }

  async function deleteTemplate(id){
    try {
      const res=await fetch(KVT_AJAX,{
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        credentials:'same-origin',
        body:new URLSearchParams({action:'kvt_delete_template', _ajax_nonce:KVT_NONCE, id})
      });
      const j = await res.json();
      if(j.success){
        KVT_TEMPLATES=j.data.templates||[];
        populateTemplateSelect();
        renderTplList();
      } else {
        alert(j.data && j.data.msg ? j.data.msg : 'Error eliminando');
      }
    } catch(e){
      alert('Error eliminando');
    }
  }

  let tplEditId=null;
  function editTemplate(id){
    const t=KVT_TEMPLATES.find(x=>String(x.id)===String(id));
    if(!t) return;
    tplTitle.value=t.title||'';
    tplSubject.value=t.subject||'';
    tplBody.value=t.body||'';
    tplEditId=id;
    if(tplSave) tplSave.textContent='Actualizar';
  }

  tplSave && tplSave.addEventListener('click', async()=>{
    const title=(tplTitle.value||'').trim();
    const subject=(tplSubject.value||'').trim();
    const body=(tplBody.value||'').trim();
    if(!title) { alert('Título requerido'); return; }
    try {
      const params=new URLSearchParams({action:'kvt_save_template', _ajax_nonce:KVT_NONCE, title, subject, body});
      if(tplEditId) params.set('id', tplEditId);
      const res=await fetch(KVT_AJAX,{
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        credentials:'same-origin',
        body:params
      });
      const j = await res.json();
      if(j.success){
        KVT_TEMPLATES=j.data.templates||[];
        populateTemplateSelect();
        renderTplList();
        tplTitle.value=''; tplSubject.value=''; tplBody.value='';
        tplEditId=null;
        tplSave.textContent='Guardar';
        alert('Plantilla guardada');
      }else{
        alert(j.data && j.data.msg ? j.data.msg : 'Error guardando');
      }
    } catch(e){
      alert('Error guardando');
    }
  });

  emailTplSelect && emailTplSelect.addEventListener('change',()=>{
    const t=KVT_TEMPLATES.find(x=>String(x.id)===String(emailTplSelect.value));
    if(t){ emailSubject.value=t.subject||''; emailBody.value=t.body||''; }
  });

  emailSaveTplBtn && emailSaveTplBtn.addEventListener('click', async ()=>{
    const title = prompt('Título de la plantilla');
    if(!title) return;
    const subject=(emailSubject.value||'').trim();
    const body=(emailBody.value||'').trim();
    try {
      const res = await fetch(KVT_AJAX,{
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        credentials:'same-origin',
        body:new URLSearchParams({action:'kvt_save_template', _ajax_nonce:KVT_NONCE, title, subject, body})
      });
      const j = await res.json();
      if(j.success){
        KVT_TEMPLATES=j.data.templates||[];
        populateTemplateSelect();
        renderTplList();
        alert('Plantilla guardada');
      } else {
        alert(j.data && j.data.msg ? j.data.msg : 'Error guardando');
      }
    } catch(e){
      alert('Error guardando');
    }
  });

  function renderSentEmails(){
    if(!sentTbody) return;
    sentTbody.innerHTML='';
    KVT_SENT_EMAILS.forEach(l=>{
      const tr=document.createElement('tr');
      const count=l.recipients?l.recipients.length:0;
      tr.innerHTML=`<td>${l.time||''}</td><td>${l.subject||''}</td><td>${count}</td>`;
      sentTbody.appendChild(tr);
    });
  }

  populateTemplateSelect();
  renderTplList();
  renderSentEmails();

  emailTabs.forEach(btn=>{
    btn.addEventListener('click',()=>{
      const target=btn.dataset.target;
      emailTabs.forEach(b=>b.classList.toggle('active', b===btn));
      ['compose','sent','templates'].forEach(k=>{
        const pane=el('#kvt_email_tab_'+k);
        if(pane) pane.classList.toggle('active', k===target);
      });
    });
  });

  if(window.jQuery){
    [emailClient,emailProcess,emailStatusSel,emailCountry,emailCity].forEach(sel=>{
      if(sel) {
        jQuery(sel)
          .select2({width:'style', dropdownAutoWidth:true})
          .on('select2:select select2:unselect', () => loadEmailCandidates(1));
      }
    });
  } else {
    [emailClient,emailProcess,emailStatusSel,emailCountry,emailCity].forEach(sel=>{
      sel && sel.addEventListener('change', ()=>loadEmailCandidates(1));
    });
  }

  function updateEmailSel(){
    if(emailSelInfo) emailSelInfo.textContent = emailSelected.size + ' seleccionados';
  }

  function updateEmailPager(){
    if(!emailPager) return;
    emailPageInfo.textContent = emailPageNum + ' / ' + emailPageTotal;
    emailPrev.disabled = emailPageNum <= 1;
    emailNext.disabled = emailPageNum >= emailPageTotal;
    emailPager.style.display = emailPageTotal > 1 ? 'flex' : 'none';
  }

  function renderEmailTable(){
    if(!emailTbody) return;
    emailTbody.innerHTML = emailCandidates.map(c=>{
      const id=c.id;
      const m=c.meta||{};
      const chk=emailSelected.has(String(id))?'checked':'';
      return '<tr><td><input type="checkbox" data-id="'+escAttr(id)+'" '+chk+'></td>'+
        '<td>'+esc(m.first_name||'')+'</td>'+
        '<td>'+esc(m.last_name||'')+'</td>'+
        '<td>'+esc(m.email||'')+'</td>'+
        '<td>'+esc(m.country||'')+'</td>'+
        '<td>'+esc(m.city||'')+'</td>'+
        '<td>'+esc(m.client||'')+'</td>'+
        '<td>'+esc(m.process||'')+'</td>'+
        '<td>'+esc(m.status||'')+'</td></tr>';
    }).join('');
    updateEmailSel();
  }

  function loadEmailCandidates(pg=1){
    if(!emailTbody) return;
    const params=new URLSearchParams({action:'kvt_get_candidates', _ajax_nonce:KVT_NONCE});
    const getVals=sel=>sel?Array.from(sel.selectedOptions).map(o=>o.value).filter(v=>v):[];
    const c=getVals(emailClient); if(c.length) params.set('client', c.join(','));
    const p=getVals(emailProcess); if(p.length) params.set('process', p.join(','));
    const s=getVals(emailStatusSel); if(s.length) params.set('status', s.join(','));
    const co=getVals(emailCountry); if(co.length) params.set('country', co.join(','));
    const ci=getVals(emailCity); if(ci.length) params.set('city', ci.join(','));
    const q=emailSearch?emailSearch.value.trim():''; if(q) params.set('search', q);
    const hasFilter = c.length || p.length || s.length || co.length || ci.length || q.length;
    if(!hasFilter){ params.set('per_page',15); params.set('page',pg); }
    else { params.set('all','1'); }
    fetch(KVT_AJAX,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:params.toString()})
      .then(r=>r.json()).then(j=>{
        emailCandidates = (j.success && j.data.items) ? j.data.items : [];
        emailSelected = new Set();
        if(hasFilter){
          emailPageNum = 1;
          emailPageTotal = 1;
          if(emailPager) emailPager.style.display='none';
        }else{
          emailPageNum = pg;
          emailPageTotal = j.success && j.data.pages ? j.data.pages : 1;
          updateEmailPager();
        }
        renderEmailTable();
      });
  }

  emailTbody && emailTbody.addEventListener('change', e=>{
    const cb=e.target.closest('input[type="checkbox"]');
    if(!cb) return;
    const id=cb.dataset.id;
    if(cb.checked) emailSelected.add(id); else emailSelected.delete(id);
    updateEmailSel();
  });

  emailSelectAll && emailSelectAll.addEventListener('click', ()=>{
    const all = emailSelected.size === emailCandidates.length;
    emailSelected = new Set(all ? [] : emailCandidates.map(c=>String(c.id)));
    renderEmailTable();
  });

  emailClear && emailClear.addEventListener('click', ()=>{ emailSelected.clear(); renderEmailTable(); });

  emailSearch && emailSearch.addEventListener('input', ()=>loadEmailCandidates(1));

  emailPrev && emailPrev.addEventListener('click', ()=>{ if(emailPageNum>1) loadEmailCandidates(emailPageNum-1); });
  emailNext && emailNext.addEventListener('click', ()=>{ if(emailPageNum<emailPageTotal) loadEmailCandidates(emailPageNum+1); });

  function formatInputDate(v){
    if(/^\d{4}-\d{2}-\d{2}$/.test(v)){
      const p=v.split('-');
      return p[2]+'/'+p[1]+'/'+p[0];
    }
    return v;
  }

  async function loadMit(){
    if(!mitContent) return;
    mitContent.textContent = 'Obteniendo sugerencias...';
    try {
      const resp = await fetch(KVT_AJAX, {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        credentials:'same-origin',
        body:new URLSearchParams({action:'kvt_mit_suggestions', nonce:KVT_MIT_NONCE})
      });
      const json = await resp.json();
      if(json && json.success && json.data){
        if(json.data.suggestions_html){
          mitContent.innerHTML = json.data.suggestions_html;
        } else if(json.data.suggestions){
          mitContent.textContent = json.data.suggestions;
        } else {
          mitContent.textContent = 'No hay sugerencias disponibles.';
        }
        if(mitNews){
          mitNews.innerHTML = '';
          (json.data.news || []).forEach(n=>{
            const li = document.createElement('li');
            li.textContent = n;
            mitNews.appendChild(li);
          });
        }
      } else {
        mitContent.textContent = 'No hay sugerencias disponibles.';
        if(mitNews) mitNews.innerHTML='';
      }
    } catch(e){
      mitContent.textContent = 'No hay sugerencias disponibles.';
      if(mitNews) mitNews.innerHTML='';
    }
  }

  function appendChat(role, html){
    if(!mitChatLog) return;
    const p=document.createElement('p');
    p.className=role;
    if(role==='assistant') p.innerHTML='<strong>MIT:</strong> '+html.replace(/\n/g,'<br>');
    else p.textContent='Tú: '+html;
    mitChatLog.appendChild(p);
    mitChatLog.scrollTop=mitChatLog.scrollHeight;
  }

  async function sendMitChat(){
    if(!mitChatInput) return;
    const msg=mitChatInput.value.trim();
    if(!msg) return;
    appendChat('user', msg);
    mitChatInput.value='';
    try {
      const resp = await fetch(KVT_AJAX,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},credentials:'same-origin',body:new URLSearchParams({action:'kvt_mit_chat', nonce:KVT_MIT_NONCE, message:msg})});
      const json = await resp.json();
      if(json && json.success && json.data && json.data.reply){
        appendChat('assistant', json.data.reply);
      }
    } catch(e){}
  }

  mitChatSend && mitChatSend.addEventListener('click', sendMitChat);
  mitChatInput && mitChatInput.addEventListener('keydown', e=>{ if(e.key==='Enter'){ e.preventDefault(); sendMitChat(); }});

  function showView(view){
    if(!filtersBar || !tableWrap || !calendarWrap) return;
    if(calendarMiniWrap) calendarMiniWrap.style.display='none';
    if(mitWrap) mitWrap.style.display='none';
    if(mitChatWrap) mitChatWrap.style.display='none';
    if(keywordBoard) keywordBoard.style.display='none';
    if(aiBoard) aiBoard.style.display='none';
    if(boardsView) boardsView.style.display='none';
    if(emailView) emailView.style.display='none';
    if(widgetsWrap) widgetsWrap.style.display='flex';
    if(view==='ats'){
      filtersBar.style.display='flex';
      tableWrap.style.display='block';
      calendarWrap.style.display='none';
      if(boardBase) boardBase.style.display='none';
      if(overview) overview.style.display='block';
      if(atsBar) atsBar.style.display='flex';
      const tbl = el('#kvt_table'); if(tbl) tbl.style.display='table';
      const pager = el('#kvt_table_pager'); if(pager) pager.style.display='block';
      if(activityWrap) activityWrap.style.display='block';
      if(boardWrap) boardWrap.style.display='none';
      if(toggleKanban){ toggleKanban.style.display='inline-block'; toggleKanban.textContent='Mostrar Kanban'; }
      refresh();
    } else if(view==='calendario'){
      filtersBar.style.display='none';
      tableWrap.style.display='none';
      calendarWrap.style.display='block';
      if(activityWrap) activityWrap.style.display='none';
      if(boardWrap) boardWrap.style.display='none';
      if(toggleKanban) toggleKanban.style.display='none';
      renderCalendar();
    } else if(view==='base'){
      filtersBar.style.display='none';
      tableWrap.style.display='block';
      calendarWrap.style.display='none';
      if(overview) overview.style.display='none';
      if(atsBar) atsBar.style.display='none';
      const tbl = el('#kvt_table'); if(tbl) tbl.style.display='none';
      const pager = el('#kvt_table_pager'); if(pager) pager.style.display='none';
      if(boardBase) boardBase.style.display='block';
      if(activityWrap) activityWrap.style.display='none';
      if(boardWrap) boardWrap.style.display='none';
      if(toggleKanban) toggleKanban.style.display='none';
      switchBoardTab('candidates');
    } else if(view==='detalles'){
      filtersBar.style.display='none';
      tableWrap.style.display='none';
      calendarWrap.style.display='none';
      if(activityWrap) activityWrap.style.display='block';
      if(boardWrap) boardWrap.style.display='none';
      if(toggleKanban) toggleKanban.style.display='none';
      if(calendarMiniWrap) calendarMiniWrap.style.display='block';
      fetchDashboard().then(d=>{ if(d.success) renderActivityDashboard(d.data); });
    } else if(view==='mit'){
      filtersBar.style.display='none';
      tableWrap.style.display='none';
      calendarWrap.style.display='none';
      if(activityWrap) activityWrap.style.display='none';
      if(boardWrap) boardWrap.style.display='none';
      if(toggleKanban) toggleKanban.style.display='none';
      if(widgetsWrap) widgetsWrap.style.display='none';
      if(mitWrap) { mitWrap.style.display='block'; loadMit(); }
    } else if(view==='chat'){
      filtersBar.style.display='none';
      tableWrap.style.display='none';
      calendarWrap.style.display='none';
      if(activityWrap) activityWrap.style.display='none';
      if(boardWrap) boardWrap.style.display='none';
      if(toggleKanban) toggleKanban.style.display='none';
      if(widgetsWrap) widgetsWrap.style.display='none';
      if(mitChatWrap) mitChatWrap.style.display='block';
    } else if(view==='ai'){
      filtersBar.style.display='none';
      tableWrap.style.display='none';
      calendarWrap.style.display='none';
      if(overview) overview.style.display='none';
      if(atsBar) atsBar.style.display='none';
      if(activityWrap) activityWrap.style.display='none';
      if(boardWrap) boardWrap.style.display='none';
      if(boardBase) boardBase.style.display='none';
      if(toggleKanban) toggleKanban.style.display='none';
      if(widgetsWrap) widgetsWrap.style.display='none';
      if(aiBoard) aiBoard.style.display='block';
    } else if(view==='keyword'){
      filtersBar.style.display='none';
      tableWrap.style.display='none';
      calendarWrap.style.display='none';
      if(overview) overview.style.display='none';
      if(atsBar) atsBar.style.display='none';
      if(activityWrap) activityWrap.style.display='none';
      if(boardWrap) boardWrap.style.display='none';
      if(boardBase) boardBase.style.display='none';
      if(toggleKanban) toggleKanban.style.display='none';
      if(widgetsWrap) widgetsWrap.style.display='none';
      if(keywordBoard) keywordBoard.style.display='block';
    } else if(view==='boards'){
      filtersBar.style.display='none';
      tableWrap.style.display='none';
      calendarWrap.style.display='none';
      if(overview) overview.style.display='none';
      if(atsBar) atsBar.style.display='none';
      if(activityWrap) activityWrap.style.display='none';
      if(boardWrap) boardWrap.style.display='none';
      if(boardBase) boardBase.style.display='none';
      if(toggleKanban) toggleKanban.style.display='none';
      if(widgetsWrap) widgetsWrap.style.display='none';
      if(boardsView) boardsView.style.display='block';
    } else if(view==='email'){
      filtersBar.style.display='none';
      tableWrap.style.display='none';
      calendarWrap.style.display='none';
      if(overview) overview.style.display='none';
      if(atsBar) atsBar.style.display='none';
      if(activityWrap) activityWrap.style.display='none';
      if(boardWrap) boardWrap.style.display='none';
      if(boardBase) boardBase.style.display='none';
      if(toggleKanban) toggleKanban.style.display='none';
      if(widgetsWrap) widgetsWrap.style.display='none';
      if(emailView){ emailView.style.display='block'; loadEmailCandidates(); }
    } else {
      filtersBar.style.display='none';
      tableWrap.style.display='none';
      calendarWrap.style.display='none';
      if(activityWrap) activityWrap.style.display='none';
      if(boardWrap) boardWrap.style.display='none';
      if(toggleKanban) toggleKanban.style.display='none';
    }
  }

  if(stageSelect){
    stageSelect.innerHTML = '<option value="">Todas las etapas</option>' + KVT_STATUSES.map(s=>'<option value="'+escAttr(s)+'">'+esc(s)+'</option>').join('');
  }
  const infoModal = el('#kvt_info_modal');
  const infoClose = el('#kvt_info_close');
  const infoBody  = el('#kvt_info_body');

  const fbModal = el('#kvt_feedback_modal');
  const fbClose = el('#kvt_feedback_close');
  const fbName  = el('#kvt_fb_name');
  const fbText  = el('#kvt_fb_text');
  const fbSave  = el('#kvt_fb_save');
  let fbCandidate = null;
  let modalSelectMode = false;

  const modal      = el('#kvt_modal');
  const modalClose = el('.kvt-modal-close', modal);
  const modalList  = el('#kvt_modal_list', modal);
  const modalName   = el('#kvt_modal_name', modal);
  const modalRole   = el('#kvt_modal_role', modal);
  const modalLoc    = el('#kvt_modal_location', modal);
  const modalAssign = el('#kvt_modal_assign', modal);
  const modalPrev  = el('#kvt_modal_prev', modal);
  const modalNext  = el('#kvt_modal_next', modal);
  const modalPage  = el('#kvt_modal_pageinfo', modal);
  const modalCtx   = {list: modalList, page: modalPage, prev: modalPrev, next: modalNext, name: modalName, role: modalRole, loc: modalLoc, assign: modalAssign, close: closeModal};
  const boardCtx   = {list: boardList, page: boardPage, prev: boardPrev, next: boardNext, name: boardName, role: boardRole, loc: boardLoc, assign: boardAssign, close: null};
  const tabs = els('.kvt-tab', modal);
  const tabCandidates = el('#kvt_tab_candidates', modal);
  const tabClients = el('#kvt_tab_clients', modal);
  const tabProcesses = el('#kvt_tab_processes', modal);
  const tabAI = el('#kvt_tab_ai', modal);
  const tabKeyword = el('#kvt_tab_keyword', modal);
  const clientsList = el('#kvt_clients_list', modal);
  const processesList = el('#kvt_processes_list', modal);
  const boardTabs = els('#kvt_board_tabs .kvt-tab');
  const boardTabCandidates = el('#kvt_board_tab_candidates');
  const boardTabClients = el('#kvt_board_tab_clients');
  const boardTabProcesses = el('#kvt_board_tab_processes');
  const boardClientsList = el('#kvt_board_clients_list');
  const boardProcessesList = el('#kvt_board_processes_list');
  const procStatusFilter = el('#kvt_proc_status');
  const procClientFilter = el('#kvt_proc_client');
  const aiInput = el('#kvt_ai_input', modal);
  const aiBtn = el('#kvt_ai_search', modal);
  const aiResults = el('#kvt_ai_results', modal);
  const keywordInput = el('#kvt_keyword_input', modal);
  const keywordBtn = el('#kvt_keyword_search', modal);
  const keywordResults = el('#kvt_keyword_results', modal);
  const keywordBoardInput = el('#kvt_keyword_board_input');
  const keywordBoardBtn = el('#kvt_keyword_board_search');
  const keywordBoardResults = el('#kvt_keyword_board_results');
  const aiBoardInput = el('#kvt_ai_board_input');
  const aiBoardBtn = el('#kvt_ai_board_search');
  const aiBoardResults = el('#kvt_ai_board_results');
  const keywordCountry = el('#kvt_keyword_country', modal);
  const keywordBoardCountry = el('#kvt_keyword_board_country');
  const aiCountry = el('#kvt_ai_country', modal);
  const aiBoardCountry = el('#kvt_ai_board_country');

  function renderCountrySelect(sel){
    if(!sel) return;
    sel.innerHTML = '<option value="">Todos los países</option>' + COUNTRY_OPTIONS.map(c=>'<option value="'+escAttr(c)+'">'+esc(c)+'</option>').join('');
  }
  renderCountrySelect(keywordCountry);
  renderCountrySelect(keywordBoardCountry);
  renderCountrySelect(aiCountry);
  renderCountrySelect(aiBoardCountry);

  if (CLIENT_VIEW) {
    if (selClient) { selClient.value = CLIENT_ID; selClient.disabled = true; }
    if (selProcess) { selProcess.value = PROCESS_ID; selProcess.disabled = true; }
    const actions = el('.kvt-actions');
    if (actions) actions.style.display = 'none';
    if (selInfo) selInfo.style.display = 'none';
    if (IS_ADMIN) {
      const gear = document.createElement('button');
      gear.type = 'button';
      gear.className = 'kvt-config-client';
      gear.innerHTML = '<span class="dashicons dashicons-admin-generic"></span>';
      const toolbar = actions ? actions.parentNode : null;
      if (toolbar) toolbar.appendChild(gear);
      gear.addEventListener('click', ()=>{ buildShareOptions(); shareModal.style.display='flex'; });
    }
  }

  function getClientById(id){
    if(!Array.isArray(window.KVT_CLIENT_MAP)) return null;
    id = parseInt(id,10);
    return window.KVT_CLIENT_MAP.find(c=>c.id===id) || null;
  }
  function getProcessById(id){
    if(!Array.isArray(window.KVT_PROCESS_MAP)) return null;
    id = parseInt(id,10);
    return window.KVT_PROCESS_MAP.find(p=>p.id===id) || null;
  }

  function buildShareOptions(){
    if(shareFieldsWrap){
      const fieldsList = KVT_COLUMNS.filter(c=>c.key !== 'cv_uploaded');
      shareFieldsWrap.innerHTML = fieldsList.map(c=>{
        const chk = !ALLOWED_FIELDS.length || ALLOWED_FIELDS.includes(c.key) ? 'checked' : '';
        return '<label><input type="checkbox" value="'+escAttr(c.key)+'" '+chk+'> '+esc(c.label)+'</label>';
      }).join('<br>');
    }
    if(shareStepsWrap){
      shareStepsWrap.innerHTML = KVT_STATUSES.map(s=>{
        const chk = !ALLOWED_STEPS.length || ALLOWED_STEPS.includes(s) ? 'checked' : '';
        return '<label><input type="checkbox" value="'+escAttr(s)+'" '+chk+'> '+esc(s)+'</label>';
      }).join('<br>');
    }
    if(shareFieldsAll) shareFieldsAll.checked = els('input[type="checkbox"]', shareFieldsWrap).every(cb=>cb.checked);
    if(shareStepsAll) shareStepsAll.checked = els('input[type="checkbox"]', shareStepsWrap).every(cb=>cb.checked);
    if(shareComments) shareComments.checked = ALLOW_COMMENTS;
  }

  function switchTab(target){
    if(tabCandidates) tabCandidates.classList.toggle('active', target==='candidates');
    if(tabClients) tabClients.classList.toggle('active', target==='clients');
    if(tabProcesses) tabProcesses.classList.toggle('active', target==='processes');
    if(tabAI) tabAI.classList.toggle('active', target==='ai');
    if(tabKeyword) tabKeyword.classList.toggle('active', target==='keyword');
    tabs.forEach(b=>b.classList.toggle('active', b.dataset.target===target));
    if(target==='clients') listClients();
    if(target==='processes') listProcesses();
    if(target==='candidates') listProfiles(1, modalCtx);
  }
  tabs.forEach(b=>b.addEventListener('click', ()=>switchTab(b.dataset.target)));

  function switchBoardTab(target){
    if(boardTabCandidates) boardTabCandidates.classList.toggle('active', target==='candidates');
    if(boardTabClients) boardTabClients.classList.toggle('active', target==='clients');
    if(boardTabProcesses) boardTabProcesses.classList.toggle('active', target==='processes');
    boardTabs.forEach(b=>b.classList.toggle('active', b.dataset.target===target));
    if(target==='clients') listClients(boardClientsList);
    if(target==='processes') listProcesses(boardProcessesList);
    if(target==='candidates') listProfiles(1, boardCtx);
  }
  boardTabs.forEach(b=>b.addEventListener('click', ()=>switchBoardTab(b.dataset.target)));
  procStatusFilter && procStatusFilter.addEventListener('change', ()=>listProcesses());
  procClientFilter && procClientFilter.addEventListener('change', ()=>listProcesses());

  function openModal(tab='candidates', select=false){
    modalSelectMode = select;
    modal.style.display = 'flex';
    if(modalName) modalName.value = '';
    if(modalRole) modalRole.value = '';
    if(modalLoc) modalLoc.value = '';
    switchTab(tab);
  }
  function closeModal(){ modal.style.display = 'none'; modalSelectMode = false; }
  modalClose && modalClose.addEventListener('click', closeModal);
  modal && modal.addEventListener('click', (e)=>{ if(e.target===modal) closeModal(); });

  function filterProcessOptions(){
    if (!selClient || !selProcess) return;
    const clientId = parseInt(selClient.value || '0', 10);
    const current = selProcess.value;
    selProcess.innerHTML = '<option value="">— Todos —</option>';
    (window.KVT_PROCESS_MAP||[]).forEach(p=>{
      if (!clientId || p.client_id === clientId) {
        const opt = document.createElement('option');
        opt.value = String(p.id);
        opt.textContent = p.name;
        selProcess.appendChild(opt);
      }
    });
    if (current && Array.from(selProcess.options).some(o=>o.value===current)) selProcess.value = current;
  }

  function getClientIdForProcess(pid){
    const map = window.KVT_PROCESS_MAP || [];
    const item = map.find(p=>String(p.id)===String(pid));
    return item ? String(item.client_id) : '';
  }

  function ajaxForm(params){
    const body = new URLSearchParams(params);
    return fetch(KVT_AJAX, {
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded'},
      credentials:'same-origin',
      body:body.toString()
    }).then(r=>r.json());
  }

  function lastNoteSnippet(notes){
    const txt = String(notes||'').trim();
    if (!txt) return '';
    const lines = txt.split(/\r?\n/).map(l=>l.trim()).filter(Boolean);
    if (!lines.length) return '';
    const last = lines[lines.length-1];
    const words = last.split(/\s+/);
    const slice = words.slice(0,30).join(' ');
    return slice + (words.length>30 ? '…' : '');
  }

    function cardTemplate(c){
      const card = document.createElement('div');
      card.className = 'kvt-card';
      if (!CLIENT_VIEW && !CANDIDATE_VIEW) card.setAttribute('draggable','true');
      card.dataset.id = c.id;

      const head = document.createElement('div'); head.className='kvt-card-head';
      const title = document.createElement('p'); title.className = 'kvt-title';
      title.textContent = (c.meta.first_name||'') + ' ' + (c.meta.last_name||'');
      if (title.textContent.trim()==='') title.textContent = c.title;
      head.appendChild(title);
      if (c.meta.cv_url){
        const cv = document.createElement('a');
        cv.href = c.meta.cv_url; cv.target='_blank';
        cv.className = 'kvt-cv-link dashicons dashicons-media-document';
        cv.setAttribute('title','Ver CV');
        head.appendChild(cv);
      }

      let roleLine = null;
      if (c.meta.current_role){
        roleLine = document.createElement('p');
        roleLine.className = 'kvt-role';
        roleLine.textContent = c.meta.current_role;
      }

      const sub = document.createElement('p'); sub.className = 'kvt-sub';
      if (!(CLIENT_VIEW || CANDIDATE_VIEW) || ALLOWED_FIELDS.includes('notes')) {
        sub.textContent = lastNoteSnippet(c.meta.notes);
      }
      const tagsWrap = document.createElement('div'); tagsWrap.className = 'kvt-tags';
      if ((!(CLIENT_VIEW || CANDIDATE_VIEW) || ALLOWED_FIELDS.includes('tags')) && c.meta && c.meta.tags){
        c.meta.tags.split(',').map(t=>t.trim()).filter(Boolean).forEach(t=>{
          const span = document.createElement('button');
          span.type = 'button';
          span.className = 'kvt-tag';
          span.textContent = t;
          tagsWrap.appendChild(span);
        });
      }
      const clientComments = Array.isArray(c.meta.client_comments) ? c.meta.client_comments : [];
      let myComment = null;
      if (clientComments.length) {
        if (CLIENT_VIEW) {
          const bySlug = clientComments.filter(cc=>cc.slug===CLIENT_SLUG);
          if (bySlug.length) myComment = bySlug[bySlug.length-1];
        } else {
          myComment = clientComments[clientComments.length-1];
        }
      }
      let follow;
      let commentLine;
      if (c.meta.next_action && (!(CLIENT_VIEW || CANDIDATE_VIEW) || ALLOWED_FIELDS.includes('next_action'))){
        follow = document.createElement('p');
        follow.className = 'kvt-followup';
        const ico = document.createElement('span');
        ico.className = 'dashicons dashicons-clock';
        follow.appendChild(ico);
        const noteTxt = c.meta.next_action_note && (!(CLIENT_VIEW || CANDIDATE_VIEW) || ALLOWED_FIELDS.includes('next_action_note')) ? ' — ' + c.meta.next_action_note : '';
        follow.appendChild(document.createTextNode(' Próxima acción: ' + c.meta.next_action + noteTxt));
        const parts = c.meta.next_action.split('/');
        if(parts.length===3){
          const dt = new Date(parts[2], parts[1]-1, parts[0]);
          const today = new Date(); today.setHours(0,0,0,0);
          if(dt <= today) card.classList.add('kvt-overdue');
        }
      }
      if (myComment && (!(CLIENT_VIEW || CANDIDATE_VIEW) || ALLOW_COMMENTS)){
        commentLine = document.createElement('p');
        commentLine.className = 'kvt-followup';
        const ico2 = document.createElement('span');
        ico2.className = 'dashicons dashicons-warning';
        commentLine.appendChild(ico2);
        const cmTxt = ' Comentario: ' + ((!CLIENT_VIEW && myComment.name)? myComment.name + ': ' : '') + myComment.comment;
        commentLine.appendChild(document.createTextNode(cmTxt));
      }

    const expand = document.createElement('div'); expand.className='kvt-expand';
    const btn = document.createElement('button'); btn.type='button'; btn.textContent='Ver perfil';
    let btnDel;
    if (!CLIENT_VIEW && !CANDIDATE_VIEW) {
      btnDel = document.createElement('button');
      btnDel.type='button'; btnDel.className='kvt-delete dashicons dashicons-trash'; btnDel.setAttribute('title','Eliminar candidato');
      expand.appendChild(btn); expand.appendChild(btnDel);
    } else {
      expand.appendChild(btn);
      if (CLIENT_VIEW && ALLOW_COMMENTS) {
        const cBtn = document.createElement('button');
        cBtn.type='button';
        cBtn.textContent = 'Dar feedback';
        expand.appendChild(cBtn);
        cBtn.addEventListener('click', ()=>{
          fbCandidate = c.id;
          fbName.value = localStorage.getItem('kvtClientName') || '';
          fbText.value='';
          fbModal.style.display='flex';
        });
      }
    }

    btn.addEventListener('click', ()=>{
      openProfile(c);
    });

    if (!CLIENT_VIEW && !CANDIDATE_VIEW) {
      card.addEventListener('dragstart', e=>{
        card.classList.add('dragging'); e.dataTransfer.setData('text/plain', String(c.id));
      });
      card.addEventListener('dragend', ()=> card.classList.remove('dragging'));

      btnDel.addEventListener('click', ()=>{
        if (!confirm('¿Quitar a este candidato del proceso/cliente actual?')) return;
        ajaxForm({
          action:'kvt_unassign_candidate',
          _ajax_nonce:KVT_NONCE,
          id:String(c.id),
          client_id: selClient ? selClient.value || '' : '',
          process_id: selProcess ? selProcess.value || '' : ''
        })
          .then(j=>{
            if (!j.success) return alert(j.data && j.data.msg ? j.data.msg : 'No se pudo eliminar.');
            card.remove();
          });
      });
    }

      card.appendChild(head); if(roleLine) card.appendChild(roleLine); card.appendChild(tagsWrap); if (follow) card.appendChild(follow); if (commentLine) card.appendChild(commentLine); card.appendChild(sub);
    card.appendChild(expand);

    return card;
  }

  function buildProfileHTML(c){
    const m = c.meta||{};
    if (CLIENT_VIEW || CANDIDATE_VIEW) {
      const fields = (ALLOWED_FIELDS.length ? ALLOWED_FIELDS : KVT_COLUMNS.map(col=>col.key)).filter(f=>f!=='public_notes');
      const half = Math.ceil(fields.length/2);
      const make = fs=>fs.map(key=>{
        const col = KVT_COLUMNS.find(co=>co.key===key);
        const label = col ? col.label : key;
        return '<dt><strong>'+esc(label)+'</strong></dt><dd>'+esc(m[key]||'')+'</dd>';
      }).join('');
      let html = '<div class="kvt-profile-cols"><dl class="kvt-profile-col">'+make(fields.slice(0,half))+'</dl><dl class="kvt-profile-col">'+make(fields.slice(half))+'</dl></div>';
      const comments = Array.isArray(m.client_comments) ? m.client_comments : [];
      if(comments.length && ALLOW_COMMENTS){
        const items = comments.map(cc=>'<li><strong>'+esc(cc.name)+':</strong> '+esc(cc.comment)+'</li>').join('');
        html += '<div class="kvt-feedback-section"><h4>Comentarios</h4><ul class="kvt-feedback-list">'+items+'</ul></div>';
      }
      return html;
    }
    const input = (field,val,type='text',ph='',cls='')=>'<input class="kvt-input'+(cls?' '+cls:'')+'" data-field="'+field+'" type="'+type+'" value="'+esc(val||'')+'" placeholder="'+esc(ph||'')+'">';
    const kvInp = (label, html)=>'<dt>'+esc(label)+'</dt><dd>'+html+'</dd>';

    const left =
      kvInp('Nombre',       input('first_name', m.first_name||'')) +
      kvInp('Apellidos',    input('last_name', m.last_name||'')) +
      kvInp('Email',        input('email', m.email||'', 'email')) +
      kvInp('Teléfono',     input('phone', m.phone||'')) +
      kvInp('País',         input('country', m.country||'')) +
      kvInp('Ciudad',       input('city', m.city||'')) +
      kvInp('Subir CV',     '<input class=\"kvt-input kvt-cv-file\" type=\"file\" accept=\".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document\">'+
                            '<button type=\"button\" class=\"kvt-upload-cv\" style=\"margin-top:6px\">Subir y guardar</button>');

    const right =
      kvInp('Proceso', '<select class="kvt-assign-process"></select><button type="button" class="kvt-assign-btn">Asignar</button>') +
      kvInp('Puesto actual', input('current_role', m.current_role||'')) +
      kvInp('Etiquetas',         input('tags', m.tags||'')) +
      kvInp('CV (URL)',     input('cv_url', m.cv_url||'', 'url', 'https://...')) +
      kvInp('Fecha subida', input('cv_uploaded', m.cv_uploaded||'', 'text', 'DD/MM/YYYY', 'kvt-date'));

    const log = Array.isArray(m.activity_log) ? m.activity_log : [];
    const fieldLabels = {first_name:'Nombre',last_name:'Apellidos',email:'Email',phone:'Teléfono',country:'País',city:'Ciudad',current_role:'Puesto actual',tags:'Etiquetas',cv_url:'CV (URL)',cv_uploaded:'Fecha subida',next_action:'Próxima acción',next_action_note:'Comentario acción'};
    const logItems = log.map(it=>{
      const when = esc(it.time||'');
      const who  = esc(it.author||'');
      let text='';
      if(it.type==='status'){
        text = 'Etapa → '+esc(it.status||'');
        if(it.comment) text += ' — '+esc(it.comment);
      } else if(it.type==='task_add'){
        text = 'Añadió próxima acción '+esc(it.date||'');
        if(it.note) text += ' — '+esc(it.note);
      } else if(it.type==='task_done'){
        text = 'Completó acción '+esc(it.date||'');
        if(it.note) text += ' — '+esc(it.note);
        if(it.comment) text += ' — '+esc(it.comment);
      } else if(it.type==='task_deleted'){
        text = 'Eliminó acción '+esc(it.date||'');
        if(it.note) text += ' — '+esc(it.note);
      } else if(it.type==='created'){
        text = 'Creó el perfil';
      } else if(it.type==='update'){
        const fields = Array.isArray(it.fields)?it.fields.map(f=>fieldLabels[f]||f).join(', '):'';
        text = 'Actualizó '+esc(fields);
      } else if(it.type==='note'){
        text = 'Añadió nota: '+esc(it.note||'');
      } else if(it.type==='assign'){
        text = 'Asignó al proceso '+esc(it.process||'');
      } else if(it.type==='unassign'){
        text = 'Desasignó del proceso '+esc(it.process||'');
      }
      return '<li>'+when+' — '+who+': '+text+'</li>';
    }).join('');

    const showActivity = !CLIENT_VIEW && !CANDIDATE_VIEW;
    const activityTab = showActivity ? '<div id="kvt_profile_tab_activity" class="kvt-tab-panel"><div class="kvt-profile-activity">'+(logItems?('<ul>'+logItems+'</ul>'):'<p>No hay actividad</p>')+'</div></div>' : '';

    const notesRaw = btoa(unescape(encodeURIComponent(m.notes||'')));
    const notesArr = String(m.notes||'').split('\n').filter(Boolean);
    const notesList = notesArr.map(line=>{
      const parts = line.split('|');
      return '<li>'+esc(parts[0]||'')+' — '+esc(parts[1]||'')+': '+esc(parts[2]||'')+'</li>';
    }).join('');

    const tabs = '<div class="kvt-tabs">'+
      '<button type="button" class="kvt-tab active" data-target="info">Info</button>'+
      '<button type="button" class="kvt-tab" data-target="notes">Notas</button>'+
      '<button type="button" class="kvt-tab" data-target="next">Próxima acción</button>'+
      (showActivity?'<button type="button" class="kvt-tab" data-target="activity">Actividad</button>':'')+
      '</div>';

    let fbHTML = '';
    const comments = Array.isArray(m.client_comments) ? m.client_comments : [];
    if(comments.length){
      const items = comments.map(cc=>'<li><strong>'+esc(cc.name)+':</strong> '+esc(cc.comment)+'</li>').join('');
      fbHTML = '<div class="kvt-feedback-section"><h4>Comentarios</h4><ul class="kvt-feedback-list">'+items+'</ul></div>';
    }
    const infoTab = '<div id="kvt_profile_tab_info" class="kvt-tab-panel active">'+
      '<div class="kvt-profile-cols"><dl class="kvt-profile-col">'+left+'</dl><dl class="kvt-profile-col">'+right+'</dl></div>'+ fbHTML +
      '<button type="button" class="kvt-save-profile">Guardar perfil</button>'+
      '</div>';

    const notesTab = '<div id="kvt_profile_tab_notes" class="kvt-tab-panel" data-notes="'+notesRaw+'">'+
      '<ul class="kvt-notes-list">'+notesList+'</ul>'+
      '<textarea class="kvt-new-note" placeholder="Añadir nota"></textarea>'+
      '<button type="button" class="kvt-add-note">Guardar nota</button>'+
      '</div>';

    const nextTab = '<div id="kvt_profile_tab_next" class="kvt-tab-panel"><dl>'+
      kvInp('Fecha', input('next_action', m.next_action||'', 'text', 'DD/MM/YYYY', 'kvt-date'))+
      kvInp('Comentario', input('next_action_note', m.next_action_note||''))+
      '</dl><button type="button" class="kvt-save-next">Guardar próxima acción</button></div>';

    return tabs + infoTab + notesTab + nextTab + (showActivity?activityTab:'');
  }

  function enableProfileEditHandlers(card, id){
    const inputs = card.querySelectorAll('.kvt-input[data-field]:not([type="file"])');
    const btnSaveProfile = card.querySelector('.kvt-save-profile');
    if (!btnSaveProfile) return;

    const maskDate = e => {
      let v = e.target.value.replace(/[^0-9]/g,'').slice(0,8);
      if (v.length > 4) v = v.replace(/(\d{2})(\d{2})(\d+)/,'$1-$2-$3');
      else if (v.length > 2) v = v.replace(/(\d{2})(\d+)/,'$1-$2');
      e.target.value = v;
    };
    card.querySelectorAll('.kvt-date').forEach(i=>i.addEventListener('input', maskDate));

    btnSaveProfile.addEventListener('click', ()=>{
      const payload = {};
      inputs.forEach(i=>{ payload[i.dataset.field] = i.value || ''; });
      fetch(KVT_AJAX, {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:new URLSearchParams({action:'kvt_update_profile', _ajax_nonce:KVT_NONCE, id, ...payload}).toString()})
        .then(r=>r.json()).then(j=>{
          if(!j.success) return alert(j.data && j.data.msg ? j.data.msg : 'No se pudo guardar el perfil.');
          const title = card.querySelector('.kvt-title');
          if (title) title.textContent = (payload.first_name+' '+payload.last_name).trim() || title.textContent;
          const tagWrap = card.querySelector('.kvt-tags');
          if (tagWrap){
            tagWrap.innerHTML = '';
            if (payload.tags){
              payload.tags.split(',').map(t=>t.trim()).filter(Boolean).forEach(t=>{
                const span = document.createElement('button');
                span.type = 'button';
                span.className = 'kvt-tag';
                span.textContent = t;
                tagWrap.appendChild(span);
              });
            }
          }
          const actList = card.querySelector('#kvt_profile_tab_activity .kvt-profile-activity ul');
          if(actList){
            const stamp = new Date().toLocaleString();
            const user = KVT_CURRENT_USER || '';
            const fields = Object.keys(payload).join(', ');
            const li = document.createElement('li');
            li.textContent = stamp+' — '+user+': Actualizó '+fields;
            actList.prepend(li);
          }
          const roleLine = card.querySelector('.kvt-role');
          if(roleLine){
            roleLine.textContent = payload.current_role || '';
          } else if(payload.current_role){
            const rl = document.createElement('p');
            rl.className='kvt-role';
            rl.textContent = payload.current_role;
            const head = card.querySelector('.kvt-card-head');
            if(head) head.after(rl);
          }
          alert('Perfil guardado.');
          if(boardCtx && boardCtx.list) listProfiles(currentPage, boardCtx);
          if(modalCtx && modalCtx.list) listProfiles(currentPage, modalCtx);
          refresh();
        });
    });
  }

  function enableCvUploadHandlers(card, id){
    const fileInput = card.querySelector('.kvt-cv-file');
    const urlInput  = card.querySelector('.kvt-input[data-field="cv_url"]');
    const dateInput = card.querySelector('.kvt-input[data-field="cv_uploaded"]');
    const btnUpload = card.querySelector('.kvt-upload-cv');
    if (!fileInput || !btnUpload) return;
    btnUpload.addEventListener('click', async ()=>{
      if (!fileInput.files || !fileInput.files[0]) { alert('Selecciona un archivo.'); return; }
      const file = fileInput.files[0];
      const fd = new FormData();
      fd.append('action','kvt_upload_cv');
      fd.append('_ajax_nonce', KVT_NONCE);
      fd.append('id', id);
      fd.append('file', file);
      if (file.type === 'application/pdf') {
        let txt = await extractPdfWithPDFjs(file);
        if (!txt) txt = await ocrPdfWithTesseract(file);
        if (txt) fd.append('cv_text', txt);
      }
      const res = await fetch(KVT_AJAX, { method:'POST', body: fd });
      const j = await res.json();
      if(!j.success) return alert(j.data && j.data.msg ? j.data.msg : 'No se pudo subir el CV.');
      if (urlInput) urlInput.value = j.data.url || '';
      if (dateInput) dateInput.value = j.data.date || '';
      if(j.data.fields){
        ['first_name','last_name','email','phone','country','city','current_role'].forEach(f=>{
          const inp = card.querySelector('.kvt-input[data-field="'+f+'"]');
          if(inp && j.data.fields[f]) inp.value = j.data.fields[f];
        });
      }
      alert('CV subido y guardado.');
      refresh();
    });
  }

  function setupInfoTabs(container){
    const tabs = container.querySelectorAll('.kvt-tabs .kvt-tab');
    const panels = container.querySelectorAll('.kvt-tab-panel');
    tabs.forEach(btn=>{
      btn.addEventListener('click', ()=>{
        const target = btn.dataset.target;
        tabs.forEach(b=>b.classList.toggle('active', b===btn));
        panels.forEach(p=>p.classList.toggle('active', p.id==='kvt_profile_tab_'+target));
      });
    });
  }

  function enableNotesTab(container, id){
    const tab = container.querySelector('#kvt_profile_tab_notes');
    if(!tab) return;
    const list = tab.querySelector('.kvt-notes-list');
    const txt = tab.querySelector('.kvt-new-note');
    const btn = tab.querySelector('.kvt-add-note');
    let raw = '';
    try{ raw = tab.dataset.notes ? decodeURIComponent(escape(atob(tab.dataset.notes))) : ''; } catch(e){ raw=''; }
    if(btn) btn.addEventListener('click', ()=>{
      const note = txt.value.trim();
      if(!note){ alert('Escribe una nota.'); return; }
      const now = new Date();
      const stamp = now.toLocaleString();
      const user = KVT_CURRENT_USER || '';
      const line = stamp+'|'+user+'|'+note;
      const newRaw = raw ? raw+'\n'+line : line;
      const params = new URLSearchParams();
      params.set('action','kvt_update_notes');
      params.set('_ajax_nonce', KVT_NONCE);
      params.set('id', id);
      params.set('notes', newRaw);
      params.set('note', note);
      params.set('author', user);
      fetch(KVT_AJAX,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:params.toString()})
        .then(r=>r.json()).then(j=>{
          if(!j.success) return alert(j.data && j.data.msg ? j.data.msg : 'No se pudo guardar la nota.');
          raw = newRaw;
          const li = document.createElement('li');
          li.textContent = stamp+' — '+user+': '+note;
          if(list) list.prepend(li);
          const actList = container.querySelector('#kvt_profile_tab_activity .kvt-profile-activity ul');
          if(actList){
            const li2 = document.createElement('li');
            li2.textContent = stamp+' — '+user+': Añadió nota: '+note;
            actList.prepend(li2);
          }
          txt.value='';
          refresh();
        });
    });
  }

  function enableNextActionTab(container, id){
    const tab = container.querySelector('#kvt_profile_tab_next');
    if(!tab) return;
    const dateInput = tab.querySelector('.kvt-input[data-field="next_action"]');
    const noteInput = tab.querySelector('.kvt-input[data-field="next_action_note"]');
    const btn = tab.querySelector('.kvt-save-next');
    if(!dateInput || !btn) return;
    const maskDate = e => { let v = e.target.value.replace(/[^0-9]/g,'').slice(0,8); if (v.length>4) v = v.replace(/(\d{2})(\d{2})(\d+)/,'$1-$2-$3'); else if (v.length>2) v = v.replace(/(\d{2})(\d+)/,'$1-$2'); e.target.value = v; };
    dateInput.addEventListener('input', maskDate);
    btn.addEventListener('click', ()=>{
      const date = formatInputDate(dateInput.value);
      const note = noteInput ? noteInput.value || '' : '';
      if(!date) { alert('Fecha requerida'); return; }
      const params = new URLSearchParams();
      params.set('action','kvt_add_task');
      params.set('_ajax_nonce', KVT_NONCE);
      params.set('id', id);
      params.set('date', date);
      params.set('time', '');
      params.set('note', note);
      params.set('author', KVT_CURRENT_USER || '');
      fetch(KVT_AJAX,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:params.toString()})
        .then(r=>r.json()).then(j=>{
          if(!j.success) return alert(j.data && j.data.msg ? j.data.msg : 'No se pudo guardar la próxima acción.');
          alert('Próxima acción guardada.');
          refresh();
        });
    });
  }

  function setupAssignProcess(container, id){
    const sel = container.querySelector('.kvt-assign-process');
    const btn = container.querySelector('.kvt-assign-btn');
    if(!sel || !btn) return;
    sel.innerHTML = '<option value="">Seleccionar</option>';
    fetchProcessesList().then(j=>{
      if(j.success && j.data && Array.isArray(j.data.items)){
        j.data.items.forEach(p=>{
          const opt = document.createElement('option');
          opt.value = p.id;
          opt.textContent = p.name + (p.client ? ' — '+p.client : '');
          if(p.client_id) opt.dataset.client = p.client_id;
          sel.appendChild(opt);
        });
      }
    });
    btn.addEventListener('click', ()=>{
      const opt = sel.options[sel.selectedIndex];
      const proc = sel.value;
      if(!proc){ alert('Seleccione un proceso'); return; }
      const params = new URLSearchParams();
      params.set('action','kvt_assign_candidate');
      params.set('_ajax_nonce', KVT_NONCE);
      params.set('candidate_id', id);
      params.set('process_id', proc);
      if(opt && opt.dataset.client) params.set('client_id', opt.dataset.client);
      fetch(KVT_AJAX,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:params.toString()})
        .then(r=>r.json()).then(j=>{
          if(j.success){ alert('Candidato asignado.'); infoModal.style.display='none'; refresh(); }
          else alert(j.data && j.data.msg ? j.data.msg : 'No se pudo asignar.');
        });
    });
  }

  function openProfile(c){
    if(!c) return;
    infoBody.innerHTML = buildProfileHTML(c);
    setupInfoTabs(infoBody);
    if(!CLIENT_VIEW && !CANDIDATE_VIEW){
      enableProfileEditHandlers(infoBody, String(c.id));
      enableCvUploadHandlers(infoBody, String(c.id));
      setupAssignProcess(infoBody, String(c.id));
      enableNotesTab(infoBody, String(c.id));
      enableNextActionTab(infoBody, String(c.id));
    }
    infoModal.style.display='flex';
  }

  function enableDnD(){
    els('.kvt-dropzone').forEach(zone=>{
      zone.addEventListener('dragover', e=>{ e.preventDefault(); });
      zone.addEventListener('drop', e=>{
        e.preventDefault();
        const id = e.dataTransfer.getData('text/plain');
        const newStatus = zone.dataset.status;
        const card = el('.kvt-card[data-id="'+id+'"]');
        if (card) zone.appendChild(card);
        ajaxForm({action:'kvt_update_status', _ajax_nonce:KVT_NONCE, id:id, status:newStatus});
      });
    });
  }

  function fetchCandidates(){
    const params = new URLSearchParams();
    params.set('action','kvt_get_candidates');
    params.set('_ajax_nonce', KVT_NONCE);
    params.set('client', selClient ? selClient.value : '');
    params.set('process', selProcess ? selProcess.value : '');
    params.set('page', currentPage);
    return fetch(KVT_AJAX, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:params.toString() }).then(r=>r.json());
  }

  function fetchDashboard(){
    const params = new URLSearchParams();
    params.set('action','kvt_get_dashboard');
    params.set('_ajax_nonce', KVT_NONCE);
    return fetch(KVT_AJAX, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:params.toString() }).then(r=>r.json());
  }

  function fetchOutlookEvents(){
    const params = new URLSearchParams();
    params.set('action','kvt_get_outlook_events');
    params.set('_ajax_nonce', KVT_NONCE);
    return fetch(KVT_AJAX,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:params.toString()}).then(r=>r.json());
  }

  function loadOutlookEvents(){
    fetchOutlookEvents().then(j=>{
      if(j.success && Array.isArray(j.data)){
        j.data.forEach(e=>{
          e.manual = true;
          e.text = fixUnicode(e.text||'');
          if(e.candidate) e.candidate = fixUnicode(e.candidate);
          if(e.process)   e.process   = fixUnicode(e.process);
          if(e.client)    e.client    = fixUnicode(e.client);
          calendarEvents.push(e);
        });
        renderCalendarSmall();
        renderCalendar();
      }
    });
  }

  async function loadMitCalendar(ev){
    const btn = ev?.target;
    if(btn){ btn.disabled = true; btn.classList.add('loading'); }
    try {
      const resp = await fetch(KVT_AJAX, {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        credentials:'same-origin',
        body:new URLSearchParams({action:'kvt_mit_suggestions', nonce:KVT_MIT_NONCE})
      });
      const json = await resp.json();
      if(json && json.success && json.data && Array.isArray(json.data.agenda)){
        const today = new Date(); today.setHours(0,0,0,0);
        calendarEvents = calendarEvents.filter(e=>e.manual);
        json.data.agenda.forEach(item=>{
          const parts = item.date.split('/');
          if(parts.length===3){
            const d = new Date(parts[2], parts[1]-1, parts[0]);
            const day = d.getDay();
            if(d < today) return; // skip past dates
            if(day===0 || day===6) return; // skip weekends
            calendarEvents.push({
              date:item.date,
              time:'',
              text:fixUnicode(item.text),
              strategy:fixUnicode(item.strategy),
              template:fixUnicode(item.template),
              done:false,
              manual:false
            });
          }
        });
        renderCalendar();
        renderCalendarSmall();
      }
    } catch(e){} finally {
      if(btn){ btn.disabled = false; btn.classList.remove('loading'); }
    }
  }

  let mitDetailModal;

  function openMitDetail(ev){
    if(!mitDetailModal){
      mitDetailModal = document.createElement('div');
      mitDetailModal.id='kvt_mit_detail';
      mitDetailModal.innerHTML='<div id="kvt_mit_detail_box"><button type="button" id="kvt_mit_detail_close">&times;</button><div id="kvt_mit_detail_body"></div></div>';
      document.body.appendChild(mitDetailModal);
      mitDetailModal.addEventListener('click', e=>{ if(e.target===mitDetailModal || e.target.id==='kvt_mit_detail_close') mitDetailModal.style.display='none'; });
    }
    const body = el('#kvt_mit_detail_body');
    body.innerHTML='<h4>'+esc(ev.text)+'</h4>'+(ev.strategy?'<p>'+esc(ev.strategy)+'</p>':'')+(ev.template||'');
    mitDetailModal.style.display='flex';
  }

  function fetchProcessesList(){
    const params = new URLSearchParams();
    params.set('action','kvt_list_processes');
    params.set('_ajax_nonce', KVT_NONCE);
    return fetch(KVT_AJAX,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:params.toString()}).then(r=>r.json());
  }

  function fetchClientsList(){
    const params = new URLSearchParams();
    params.set('action','kvt_list_clients');
    params.set('_ajax_nonce', KVT_NONCE);
    return fetch(KVT_AJAX,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:params.toString()}).then(r=>r.json());
  }

  function fetchCandidatesAll(procId=''){
    const params = new URLSearchParams();
    params.set('action','kvt_get_candidates');
    params.set('_ajax_nonce', KVT_NONCE);
    params.set('all','1');
    params.set('page',1);
    if(procId) params.set('process', procId);
    return fetch(KVT_AJAX,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:params.toString()}).then(r=>r.json());
  }

  function dismissComment(id, idx, card){
    const params = new URLSearchParams();
    params.set('action','kvt_dismiss_comment');
    params.set('_ajax_nonce', KVT_NONCE);
    params.set('id', id);
    params.set('index', idx);
    fetch(KVT_AJAX,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:params.toString()})
      .then(r=>r.json()).then(j=>{
        if(j.success && card){
          const zone = card.parentElement;
          card.remove();
          if(zone && !zone.children.length){
            const col = zone.parentElement; if(col) col.remove();
          }
          if(!board.children.length){
            const empty = document.createElement('div');
            empty.className='kvt-empty';
            empty.textContent='No hay notificaciones pendientes.';
            board.appendChild(empty);
          }
        }
      });
  }

  function renderDashboard(data){
    board.innerHTML = '';

    if (data.comments && data.comments.length) {
      const col = document.createElement('div');
      col.className = 'kvt-col';
      const h = document.createElement('h3'); h.textContent = 'Comentarios de clientes';
      const zone = document.createElement('div'); zone.className = 'kvt-dropzone';
      data.comments.forEach(c=>{
        const card = document.createElement('div'); card.className='kvt-card';
        card.innerHTML = '<p class="kvt-title">'+esc(c.candidate)+'</p>'+
                         '<p class="kvt-sub">'+esc(c.client+(c.process?' / '+c.process:''))+'</p>'+
                         '<p>'+esc(c.comment)+' — '+esc(c.name)+'</p>';
        const btn = document.createElement('button');
        btn.type='button'; btn.className='kvt-delete dashicons dashicons-no-alt';
        btn.addEventListener('click', ()=>dismissComment(c.candidate_id, c.index, card));
        card.appendChild(btn);
        zone.appendChild(card);
      });
      col.appendChild(h); col.appendChild(zone); board.appendChild(col);
    }

    if (data.upcoming && data.upcoming.length) {
      const col = document.createElement('div');
      col.className = 'kvt-col';
      const h = document.createElement('h3'); h.textContent = 'Próximas acciones (7 días)';
      const zone = document.createElement('div'); zone.className = 'kvt-dropzone';
      data.upcoming.forEach(c=>{
        const card = document.createElement('div'); card.className='kvt-card';
        card.innerHTML = '<p class="kvt-title">'+esc(c.candidate)+'</p>'+
                         '<p class="kvt-sub">'+esc(c.client+(c.process?' / '+c.process:''))+'</p>'+
                         '<p class="kvt-followup"><span class="dashicons dashicons-clock"></span> '+esc(formatInputDate(c.date))+(c.note?' — '+esc(c.note):'')+'</p>';
        zone.appendChild(card);
      });
      col.appendChild(h); col.appendChild(zone); board.appendChild(col);
    }

    if (data.overdue && data.overdue.length) {
      const col = document.createElement('div');
      col.className = 'kvt-col';
      const h = document.createElement('h3'); h.textContent = 'Acciones vencidas';
      const zone = document.createElement('div'); zone.className = 'kvt-dropzone';
      data.overdue.forEach(c=>{
        const card = document.createElement('div'); card.className='kvt-card kvt-overdue';
        card.innerHTML = '<p class="kvt-title">'+esc(c.candidate)+'</p>'+
                         '<p class="kvt-sub">'+esc(c.client+(c.process?' / '+c.process:''))+'</p>'+
                         '<p class="kvt-followup"><span class="dashicons dashicons-clock"></span> '+esc(formatInputDate(c.date))+(c.note?' — '+esc(c.note):'')+'</p>';
        zone.appendChild(card);
      });
      col.appendChild(h); col.appendChild(zone); board.appendChild(col);
    }

    if (!board.children.length) {
      const empty = document.createElement('div');
      empty.className = 'kvt-empty';
      empty.textContent = 'No hay notificaciones pendientes.';
      board.appendChild(empty);
    }
  }

  function renderData(data){
    if(CANDIDATE_VIEW){
      data = Array.isArray(data) ? data.filter(r=>String(r.id)===String(CANDIDATE_ID)) : [];
    }
    const baseMode = !selClient.value && !selProcess.value;
    if(overview){
      overview.style.display = baseMode ? 'none' : 'block';
      overview.innerHTML = '';
    }
    if(atsBar) atsBar.style.display = 'flex';
    board.innerHTML = '';
    if(boardBase) boardBase.style.display='none';
    const tbl = el('#kvt_table');
    if(tbl) tbl.style.display='table';
    KVT_STATUSES.forEach(st=>{
      const col = document.createElement('div');
      col.className = 'kvt-col'; col.dataset.status = st;
      const h = document.createElement('h3'); h.textContent = st;
      const zone = document.createElement('div'); zone.className = 'kvt-dropzone'; zone.dataset.status = st;
      col.appendChild(h); col.appendChild(zone); board.appendChild(col);
    });

    if (!data || data.length === 0) {
      const empty = document.createElement('div');
      empty.className = 'kvt-empty';
      empty.innerHTML = 'Selecciona un <strong>Cliente</strong> o un <strong>Proceso</strong> para ver candidatos.';
      board.prepend(empty);
    }

    data.forEach(c=>{
      const zone = el('.kvt-dropzone[data-status="'+(c.status||'')+'"]') || el('.kvt-dropzone');
      if (zone) {
        const card = cardTemplate(c);
        zone.appendChild(card);
      }
    });

    if (!CLIENT_VIEW && !CANDIDATE_VIEW) enableDnD();
    allRows = Array.isArray(data) ? data : [];
    if(CANDIDATE_VIEW) selectedCandidateId = CANDIDATE_ID;
    filterTable();
  }

  function renderTable(rows){
    if(!tHead || !tBody) return;
    const showSelect = forceSelect || (searchInput && searchInput.value.trim() !== '');
    tHead.innerHTML = (showSelect?'<th><input type="checkbox" id="kvt_select_all"></th>':'')+'<th>Candidato/a</th><th>Etapas</th>';
    tBody.innerHTML = rows.map(r=>{
        const nameTxt = esc(((r.meta.first_name||'')+' '+(r.meta.last_name||'')).trim());
        const icons=[];
        if(r.meta.cv_url){
          icons.push('<a href="'+escAttr(r.meta.cv_url)+'" class="kvt-name-icon dashicons dashicons-media-document" target="_blank" title="Ver CV"></a>');
        }
        const comments=Array.isArray(r.meta.client_comments)?r.meta.client_comments:[];
        let cm=null;
        if(comments.length){
          if(CLIENT_VIEW){
            const bySlug = comments.filter(cc=>cc.slug===CLIENT_SLUG);
            if(bySlug.length) cm = bySlug[bySlug.length-1];
          } else {
            cm = comments[comments.length-1];
          }
        }
        if(cm && (!(CLIENT_VIEW || CANDIDATE_VIEW) || ALLOW_COMMENTS)){
          icons.push('<span class="kvt-name-icon kvt-alert" title="'+escAttr(cm.comment)+'">!</span>');
        }
        if(r.meta.next_action && (!(CLIENT_VIEW || CANDIDATE_VIEW) || ALLOWED_FIELDS.includes('next_action'))){
          const parts=r.meta.next_action.split('/');
          let overdue=false;
          if(parts.length===3){
            const d=new Date(parts[2],parts[1]-1,parts[0]);
            const today=new Date();today.setHours(0,0,0,0);
            overdue=d<=today;
          }
          const note=r.meta.next_action_note? ' — '+r.meta.next_action_note:'';
          icons.push('<span class="kvt-name-icon dashicons dashicons-clock'+(overdue?' overdue':'')+'" title="'+escAttr(r.meta.next_action+note)+'"></span>');
        }
        const noteSrc = (!(CLIENT_VIEW || CANDIDATE_VIEW) || ALLOWED_FIELDS.includes('notes')) ? r.meta.notes : '';
        const snip = lastNoteSnippet(noteSrc);
        if(snip){ icons.push('<span class="kvt-name-icon dashicons dashicons-format-chat" title="'+escAttr(snip)+'"></span>'); }
        const del = (!CLIENT_VIEW && !CANDIDATE_VIEW) ? '<span class="dashicons dashicons-trash kvt-row-remove" data-id="'+escAttr(r.id)+'"></span>' : '';
        const feedbackBtn = (CLIENT_VIEW && ALLOW_COMMENTS) ? '<button type="button" class="kvt-feedback-btn" data-id="'+escAttr(r.id)+'">Enviar comentarios</button>' : '';
        const name = del+'<a href="#" class="kvt-row-view" data-id="'+escAttr(r.id)+'">'+nameTxt+'</a>'+icons.join('')+feedbackBtn;
        const stepStatuses = KVT_STATUSES.filter(s=>s !== 'Descartados');
        let cidx = stepStatuses.indexOf(r.status||'');
        if((r.status||'') === 'Descartados') cidx = stepStatuses.length;
        const parts = stepStatuses.map((s,idx)=>{
          let cls = 'kvt-stage-step';
          if(idx < cidx) cls += ' done';
          else if(idx === cidx) cls += ' current';
          const label = idx < cidx ? '&#10003;' : esc(s);
          return '<button type="button" class="'+cls+'" data-id="'+escAttr(r.id)+'" data-status="'+escAttr(s)+'" title="'+escAttr(s)+'">'+label+'</button>';
        }).join('');
        const checkCell = showSelect?'<td><input type="checkbox" class="kvt-row-select" value="'+escAttr(r.id)+'"></td>':'';
        return '<tr>'+checkCell+'<td>'+name+'</td><td class="kvt-stage-cell">'+parts+'</td></tr>';
      }).join('');
    if(showSelect){
      const selAll = el('#kvt_select_all');
      if(selAll){
        selAll.addEventListener('change', ()=>{ els('.kvt-row-select', tBody).forEach(cb=>cb.checked = selAll.checked); });
        if(forceSelect) selAll.checked = true;
      }
      if(forceSelect) els('.kvt-row-select', tBody).forEach(cb=>cb.checked = true);
    }
  }

  function renderActivity(rows){
    if(!activityDue || !activityUpcoming || !activityNotify) return;
    const today = new Date(); today.setHours(0,0,0,0);
    const due=[]; const upcoming=[]; const notifs=[]; const logs=[];
    calendarEvents = [];
    rows.forEach(r=>{
      const nameRaw = ((r.meta.first_name||'')+' '+(r.meta.last_name||'')).trim();
      const nameTxt = fixUnicode(nameRaw);
      if(r.meta.next_action){
        const parts = r.meta.next_action.split('/');
        if(parts.length===3){
          const d = new Date(parts[2], parts[1]-1, parts[0]);
          const noteRaw = r.meta.next_action_note||'';
          const note = fixUnicode(noteRaw);
          const item = '<li data-id="'+escAttr(r.id)+'"><a href="#" class="kvt-row-view" data-id="'+escAttr(r.id)+'">'+esc(nameTxt)+'</a> - '+esc(r.meta.next_action)+(note?' — '+esc(note):'')+' <span class="kvt-task-done dashicons dashicons-yes" title="Marcar como hecha"></span><span class="kvt-task-delete dashicons dashicons-no" title="Eliminar"></span></li>';
          (d <= today ? due : upcoming).push(item);
          const ds = parts.join('/');
          calendarEvents.push({date: ds, text: nameTxt, candidate_id:r.id, done:false, manual:true});
        }
      }
      if(Array.isArray(r.meta.client_comments)){
        r.meta.client_comments.forEach((cc,idx)=>{
          if(!cc.dismissed){
            const comment = fixUnicode(cc.comment);
            const cName = cc.name ? fixUnicode(cc.name) : '';
            const cDate = cc.date ? formatInputDate(cc.date.split(' ')[0]) : '';
            const meta = [];
            if(cName) meta.push(esc(cName));
            if(cDate) meta.push(esc(cDate));
            const metaStr = meta.join(' / ');
            const item = '<li data-id="'+escAttr(r.id)+'" data-index="'+idx+'"><a href="#" class="kvt-row-view" data-id="'+escAttr(r.id)+'">'+esc(nameTxt)+'</a> — '+(metaStr?metaStr+' — ':'')+esc(comment)+' <span class="kvt-comment-dismiss dashicons dashicons-no" title="Descartar"></span></li>';
            notifs.push(item);
          }
        });
      }
      if(Array.isArray(r.meta.activity_log)){
        r.meta.activity_log.forEach(l=>{
          let msg='';
          const lStatus = fixUnicode(l.status||'');
          const lComment = fixUnicode(l.comment||'');
          const lNote = fixUnicode(l.note||'');
          switch(l.type){
            case 'status':
              msg='Estado a '+esc(lStatus)+(lComment?' — '+esc(lComment):'');
              break;
            case 'task_add':
              msg='Tarea '+esc(l.date)+(lNote?' — '+esc(lNote):'');
              break;
            case 'task_done':
              msg='Tarea completada '+esc(l.date)+(lComment?' — '+esc(lComment):'');
              break;
            case 'task_deleted':
              msg='Tarea eliminada '+esc(l.date)+(lNote?' — '+esc(lNote):'');
              break;
            default:
              msg=esc(fixUnicode(l.type||''));
          }
          const author = fixUnicode(l.author||'');
          const time = fixUnicode(l.time||'');
          logs.push({time: esc(time), text: esc(nameTxt)+' — '+msg+(author?' ('+esc(author)+')':'')});
        });
      }
    });
    activityDue.innerHTML = due.join('') || '<li>No hay tareas pendientes</li>';
    activityUpcoming.innerHTML = upcoming.join('') || '<li>No hay tareas próximas</li>';
    activityNotify.innerHTML = notifs.join('') || '<li>No hay notificaciones</li>';
    if(activityLog){
      logs.sort((a,b)=>a.time<b.time?1:-1);
      activityLog.innerHTML = logs.length ? logs.map(l=>'<li>'+l.time+' - '+l.text+'</li>').join('') : '<li>No hay actividad</li>';
    }
    renderCalendarSmall();
    loadOutlookEvents();
  }

  function renderActivityDashboard(data){
    if(!activityDue || !activityUpcoming || !activityNotify) return;
    calendarEvents = [];
    const due = (data.overdue||[]).map(c=>{
      const noteTxt = c.note ? fixUnicode(c.note) : '';
      const candTxt = fixUnicode(c.candidate||'');
      const procTxt = fixUnicode(c.process||'');
      const clientTxt = fixUnicode(c.client||'');
      calendarEvents.push({date:formatInputDate(c.date), time:c.time||'', text:noteTxt, candidate:candTxt, process:procTxt, client:clientTxt, candidate_id:c.candidate_id, done:false, manual:true});
      const note = noteTxt ? ' — '+esc(noteTxt) : '';
      return '<li data-id="'+escAttr(c.candidate_id)+'"><a href="#" class="kvt-row-view" data-id="'+escAttr(c.candidate_id)+'">'+esc(candTxt)+'</a> - '+esc(formatInputDate(c.date))+(c.time?' '+esc(c.time):'')+note+' <span class="kvt-task-done dashicons dashicons-yes" title="Marcar como hecha"></span><span class="kvt-task-delete dashicons dashicons-no" title="Eliminar"></span></li>';
    });
    const upcoming = (data.upcoming||[]).map(c=>{
      const noteTxt = c.note ? fixUnicode(c.note) : '';
      const candTxt = fixUnicode(c.candidate||'');
      const procTxt = fixUnicode(c.process||'');
      const clientTxt = fixUnicode(c.client||'');
      calendarEvents.push({date:formatInputDate(c.date), time:c.time||'', text:noteTxt, candidate:candTxt, process:procTxt, client:clientTxt, candidate_id:c.candidate_id, done:false, manual:true});
      const note = noteTxt ? ' — '+esc(noteTxt) : '';
      return '<li data-id="'+escAttr(c.candidate_id)+'"><a href="#" class="kvt-row-view" data-id="'+escAttr(c.candidate_id)+'">'+esc(candTxt)+'</a> - '+esc(formatInputDate(c.date))+(c.time?' '+esc(c.time):'')+note+' <span class="kvt-task-done dashicons dashicons-yes" title="Marcar como hecha"></span><span class="kvt-task-delete dashicons dashicons-no" title="Eliminar"></span></li>';
    });
    const notifs = (data.comments||[]).map(c=>{
      const candTxt = fixUnicode(c.candidate||'');
      const clientTxt = fixUnicode(c.client||'');
      const nameTxt = fixUnicode(c.name||'');
      const dateTxt = fixUnicode(c.date||'');
      const commentTxt = fixUnicode(c.comment||'');
      const meta = [clientTxt];
      if(nameTxt) meta.push(nameTxt);
      if(dateTxt) meta.push(dateTxt);
      const metaStr = meta.filter(Boolean).map(m=>esc(m)).join(' / ');
      return '<li data-id="'+escAttr(c.candidate_id)+'" data-index="'+escAttr(c.index)+'"><a href="#" class="kvt-row-view" data-id="'+escAttr(c.candidate_id)+'">'+esc(candTxt)+'</a> — '+(metaStr?metaStr+' — ':'')+esc(commentTxt)+' <span class="kvt-comment-dismiss dashicons dashicons-no" title="Descartar"></span></li>';
    });
    const logs = (data.logs||[]).sort((a,b)=>a.time<b.time?1:-1);
    activityDue.innerHTML = due.join('') || '<li>No hay tareas pendientes</li>';
    activityUpcoming.innerHTML = upcoming.join('') || '<li>No hay tareas próximas</li>';
    activityNotify.innerHTML = notifs.join('') || '<li>No hay notificaciones</li>';
    if(activityLog) activityLog.innerHTML = logs.length ? logs.map(l=>'<li>'+esc(fixUnicode(l.time))+' - '+esc(fixUnicode(l.text))+'</li>').join('') : '<li>No hay actividad</li>';
    renderCalendarSmall();
    loadOutlookEvents();
  }

  function removeCalendarEvent(idx){
    const ev = calendarEvents[idx];
    const finish = ()=>{
      calendarEvents.splice(idx,1);
      renderCalendar();
      renderCalendarSmall();
      refresh();
    };
    if(ev && ev.candidate_id){
      const params = new URLSearchParams();
      params.set('action','kvt_delete_task');
      params.set('_ajax_nonce', KVT_NONCE);
      params.set('id', ev.candidate_id);
      params.set('author', KVT_CURRENT_USER || '');
      fetch(KVT_AJAX,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},credentials:'same-origin',body:params.toString()}).then(r=>r.json()).then(finish);
    } else {
      finish();
    }
  }

  function renderCalendar(){
    if(!calendarWrap) return;
    const first = new Date(calYear, calMonth, 1);
    const last = new Date(calYear, calMonth+1, 0);
    const today = new Date();
    const todayStr = (today.getDate()<10?'0'+today.getDate():today.getDate())+'/'+(today.getMonth()+1<10?'0'+(today.getMonth()+1):(today.getMonth()+1))+'/'+today.getFullYear();
    const dayNames = ['Dom','Lun','Mar','Mié','Jue','Vie','Sáb'];
    const monthName = first.toLocaleString('default',{month:'long'});
    let html = '<div class="kvt-cal-controls"><button type="button" id="kvt_cal_prev">&lt;</button><span class="kvt-cal-title">'+esc(monthName)+' '+calYear+'</span><span class="kvt-cal-nav"><button type="button" id="kvt_cal_next">&gt;</button><button type="button" id="kvt_cal_mit" class="kvt-btn kvt-mit-btn">Sugerencias MIT</button></span></div>';
    html += '<div class="kvt-cal-add"><input type="text" id="kvt_cal_date" placeholder="DD/MM/YYYY"><input type="time" id="kvt_cal_time"><input type="text" id="kvt_cal_text" placeholder="Evento"><select id="kvt_cal_process"><option value="">Proceso (opcional)</option></select><select id="kvt_cal_candidate"><option value="">Candidato (opcional)</option></select><select id="kvt_cal_client"><option value="">Cliente (opcional)</option></select><button type="button" id="kvt_cal_add">Añadir</button></div>';
    html += '<div class="kvt-cal-head">'+dayNames.map(d=>'<div>'+d+'</div>').join('')+'</div><div class="kvt-cal-grid">';
    for(let i=0;i<first.getDay();i++) html += '<div class="kvt-cal-cell"></div>';
    for(let d=1; d<=last.getDate(); d++){
      const ds = (d<10?'0'+d:d)+'/'+(calMonth+1<10?'0'+(calMonth+1):(calMonth+1))+'/'+calYear;
      const ev = calendarEvents.map((e,idx)=>Object.assign({idx},e)).filter(e=>e.date===ds);
      let cls = 'kvt-cal-cell';
      if(ds===todayStr) cls += ' today';
      if(ev.length) cls += ' has-event';
      html += '<div class="'+cls+'" data-date="'+ds+'"><span class="kvt-cal-day">'+d+'</span>';
      ev.forEach(e=>{
        let lbl='';
        if(e.time) lbl+=esc(fixUnicode(e.time))+' ';
        lbl+=esc(fixUnicode(e.text));
        const parts=[];
        if(e.candidate) parts.push(esc(fixUnicode(e.candidate)));
        if(e.process)   parts.push(esc(fixUnicode(e.process)));
        if(e.client)    parts.push(esc(fixUnicode(e.client)));
        if(parts.length) lbl+=' <em>- '+parts.join(' / ')+'</em>';
        const evCls = 'kvt-cal-event'+(e.done?' done':'')+(e.manual?' manual':' suggested');
        const dragAttr = e.manual?'':' draggable="true"';
        if(e.manual){
          html += '<span class="'+evCls+'" data-idx="'+e.idx+'"'+dragAttr+'>'+lbl+'</span><button class="kvt-cal-remove" data-idx="'+e.idx+'">x</button>';
        } else {
          html += '<span class="'+evCls+'" data-idx="'+e.idx+'"'+dragAttr+'>'+lbl+'</span><button class="kvt-cal-accept" data-idx="'+e.idx+'">&#10003;</button><button class="kvt-cal-reject" data-idx="'+e.idx+'">&#10005;</button>';
        }
      });
      html += '</div>';
    }
    const fill = (first.getDay()+last.getDate())%7;
    if(fill!==0){ for(let i=0;i<7-fill;i++) html += '<div class="kvt-cal-cell"></div>'; }
    html += '</div>';
    calendarWrap.innerHTML = html;
    const prevBtn = el('#kvt_cal_prev', calendarWrap);
    const nextBtn = el('#kvt_cal_next', calendarWrap);
    const addBtn  = el('#kvt_cal_add', calendarWrap);
    const dateInp = el('#kvt_cal_date', calendarWrap);
    const timeInp = el('#kvt_cal_time', calendarWrap);
    const textInp = el('#kvt_cal_text', calendarWrap);
    const procSel = el('#kvt_cal_process', calendarWrap);
    const candSel = el('#kvt_cal_candidate', calendarWrap);
    const mitBtn  = el('#kvt_cal_mit', calendarWrap);
    const clientSel = el('#kvt_cal_client', calendarWrap);
    populateCalProcesses(procSel);
    populateCalCandidates('', candSel);
    populateCalClients(clientSel);
    procSel.addEventListener('change', ()=>{ populateCalCandidates(procSel.value, candSel); });
    prevBtn.addEventListener('click', ()=>{ calMonth--; if(calMonth<0){calMonth=11; calYear--; } renderCalendar(); });
    nextBtn.addEventListener('click', ()=>{ calMonth++; if(calMonth>11){calMonth=0; calYear++; } renderCalendar(); });
    addBtn.addEventListener('click', ()=>{ if(dateInp.value && textInp.value.trim()){ const dateFmt = formatInputDate(dateInp.value); const procName = procSel.value?procSel.options[procSel.selectedIndex].text:''; const candName = candSel.value?candSel.options[candSel.selectedIndex].text:''; const clientName = clientSel.value?clientSel.options[clientSel.selectedIndex].text:''; calendarEvents.push({date:dateFmt, time:timeInp.value, text:textInp.value.trim(), process:procName, candidate:candName, client:clientName, done:false, manual:true}); renderCalendar(); }});
    calendarWrap.querySelectorAll('.kvt-cal-event').forEach(evEl=>{
      evEl.addEventListener('dragstart', e=>{ dragIdx = parseInt(evEl.dataset.idx,10); });
      evEl.addEventListener('click', ()=>{ const idx=parseInt(evEl.dataset.idx,10); const ev=calendarEvents[idx]; if(ev.manual){ ev.done=!ev.done; renderCalendar(); } else { openMitDetail(ev); }});
    });
    calendarWrap.querySelectorAll('.kvt-cal-remove').forEach(btn=>{
      btn.addEventListener('click', e=>{ e.stopPropagation(); const idx=parseInt(btn.dataset.idx,10); removeCalendarEvent(idx); });
    });
    calendarWrap.querySelectorAll('.kvt-cal-accept').forEach(btn=>{
      btn.addEventListener('click', e=>{ e.stopPropagation(); const idx=parseInt(btn.dataset.idx,10); calendarEvents[idx].manual=true; renderCalendar(); });
    });
    calendarWrap.querySelectorAll('.kvt-cal-reject').forEach(btn=>{
      btn.addEventListener('click', e=>{ e.stopPropagation(); const idx=parseInt(btn.dataset.idx,10); removeCalendarEvent(idx); });
    });
    calendarWrap.querySelectorAll('.kvt-cal-cell').forEach(cell=>{
      cell.addEventListener('dragover', e=>e.preventDefault());
      cell.addEventListener('drop', e=>{
        e.preventDefault();
        if(dragIdx!==null){
          calendarEvents[dragIdx].date = cell.dataset.date;
          calendarEvents[dragIdx].manual = true;
          dragIdx = null;
          renderCalendar();
          renderCalendarSmall();
        }
      });
    });
    mitBtn.addEventListener('click', loadMitCalendar);
  }

    function renderCalendarSmall(){
      if(!calendarSmall) return;
      const first = new Date(calYear, calMonth, 1);
      const last = new Date(calYear, calMonth+1, 0);
      const dayNames = ['Dom','Lun','Mar','Mié','Jue','Vie','Sáb'];
      const monthName = first.toLocaleString('default',{month:'long'});
      const today = new Date();
      const todayStr = (today.getDate()<10?'0'+today.getDate():today.getDate())+'/'+(today.getMonth()+1<10?'0'+(today.getMonth()+1):(today.getMonth()+1))+'/'+today.getFullYear();
      let html = '<div class="kvt-cal-controls"><button type="button" id="kvt_cal_prev_s">&lt;</button><span class="kvt-cal-title">'+esc(monthName)+' '+calYear+'</span><span class="kvt-cal-nav"><button type="button" id="kvt_cal_next_s">&gt;</button><button type="button" id="kvt_cal_mit_s" class="kvt-btn kvt-mit-btn">Sugerencias MIT</button></span></div>';
      html += '<div class="kvt-cal-add">'
        +'<label>Fecha<input type="text" id="kvt_cal_date_s" placeholder="DD/MM/YYYY"></label>'
        +'<label>Hora<input type="time" id="kvt_cal_time_s"></label>'
        +'<label>Tarea<input type="text" id="kvt_cal_text_s" placeholder="Descripción"></label>'
        +'<label>Proceso<select id="kvt_cal_process_s"><option value="">(opcional)</option></select></label>'
        +'<label>Candidato<select id="kvt_cal_candidate_s"><option value="">(opcional)</option></select></label>'
        +'<label>Cliente<select id="kvt_cal_client_s"><option value="">(opcional)</option></select></label>'
        +'<button type="button" id="kvt_cal_add_s">Añadir</button>'
      +'</div><span class="kvt-hint">Proceso, candidato y cliente son opcionales</span>';
      html += '<div class="kvt-cal-head">'+dayNames.map(d=>'<div>'+d+'</div>').join('')+'</div><div class="kvt-cal-grid">';
      for(let i=0;i<first.getDay();i++) html += '<div class="kvt-cal-cell"></div>';
      for(let d=1; d<=last.getDate(); d++){
        const ds = (d<10?'0'+d:d)+'/'+(calMonth+1<10?'0'+(calMonth+1):(calMonth+1))+'/'+calYear;
        const ev = calendarEvents.map((e,idx)=>Object.assign({idx},e)).filter(e=>e.date===ds);
        let cls = 'kvt-cal-cell';
        if(ds===todayStr) cls += ' today';
        if(ev.length) cls += ' has-event';
        html += '<div class="'+cls+'" data-date="'+ds+'"><span class="kvt-cal-day">'+d+'</span>';
        ev.forEach(e=>{
          let lbl = esc(fixUnicode(e.text));
          if(e.time) lbl += ' '+esc(fixUnicode(e.time));
          const parts=[];
          if(e.candidate) parts.push(esc(fixUnicode(e.candidate)));
          if(e.process)   parts.push(esc(fixUnicode(e.process)));
          if(e.client)    parts.push(esc(fixUnicode(e.client)));
          if(parts.length) lbl += ' <em>- '+parts.join(' / ')+'</em>';
          const evCls = 'kvt-cal-event'+(e.done?' done':'')+(e.manual?' manual':' suggested');
          const dragAttr = e.manual?'':' draggable="true"';
          if(e.manual){
            html += '<span class="'+evCls+'" data-idx="'+e.idx+'"'+dragAttr+'>'+lbl+'</span><button class="kvt-cal-remove" data-idx="'+e.idx+'">x</button>';
          } else {
            html += '<span class="'+evCls+'" data-idx="'+e.idx+'"'+dragAttr+'>'+lbl+'</span><button class="kvt-cal-accept" data-idx="'+e.idx+'">&#10003;</button><button class="kvt-cal-reject" data-idx="'+e.idx+'">&#10005;</button>';
          }
        });
        html += '</div>';
      }
      const fill = (first.getDay()+last.getDate())%7;
      if(fill!==0){ for(let i=0;i<7-fill;i++) html += '<div class="kvt-cal-cell"></div>'; }
      html += '</div>';
      calendarSmall.innerHTML = html;
      const prevBtn = el('#kvt_cal_prev_s', calendarSmall);
      const nextBtn = el('#kvt_cal_next_s', calendarSmall);
      const addBtn  = el('#kvt_cal_add_s', calendarSmall);
      const dateInp = el('#kvt_cal_date_s', calendarSmall);
      const timeInp = el('#kvt_cal_time_s', calendarSmall);
      const textInp = el('#kvt_cal_text_s', calendarSmall);
      const procSel = el('#kvt_cal_process_s', calendarSmall);
      const candSel = el('#kvt_cal_candidate_s', calendarSmall);
      const mitBtn  = el('#kvt_cal_mit_s', calendarSmall);
      const clientSel = el('#kvt_cal_client_s', calendarSmall);
      populateCalProcesses(procSel);
      populateCalCandidates('', candSel);
      populateCalClients(clientSel);
      procSel.addEventListener('change', ()=>{ populateCalCandidates(procSel.value, candSel); });
      prevBtn.addEventListener('click', ()=>{ calMonth--; if(calMonth<0){calMonth=11; calYear--; } renderCalendarSmall(); });
      nextBtn.addEventListener('click', ()=>{ calMonth++; if(calMonth>11){calMonth=0; calYear++; } renderCalendarSmall(); });
      addBtn.addEventListener('click', ()=>{ if(dateInp.value && textInp.value.trim()){ const dateFmt = formatInputDate(dateInp.value); const procName = procSel.value?procSel.options[procSel.selectedIndex].text:''; const candName = candSel.value?candSel.options[candSel.selectedIndex].text:''; const clientName = clientSel.value?clientSel.options[clientSel.selectedIndex].text:''; calendarEvents.push({date:dateFmt, time:timeInp.value, text:textInp.value.trim(), process:procName, candidate:candName, client:clientName, done:false, manual:true}); renderCalendarSmall(); }});
      calendarSmall.querySelectorAll('.kvt-cal-event').forEach(evEl=>{
        evEl.addEventListener('dragstart', e=>{ dragIdx = parseInt(evEl.dataset.idx,10); });
        evEl.addEventListener('click', ()=>{ const idx=parseInt(evEl.dataset.idx,10); const ev=calendarEvents[idx]; if(ev.manual){ ev.done=!ev.done; renderCalendarSmall(); } else { openMitDetail(ev); }});
      });
      calendarSmall.querySelectorAll('.kvt-cal-remove').forEach(btn=>{ btn.addEventListener('click', e=>{ e.stopPropagation(); const idx=parseInt(btn.dataset.idx,10); removeCalendarEvent(idx); }); });
      calendarSmall.querySelectorAll('.kvt-cal-accept').forEach(btn=>{ btn.addEventListener('click', e=>{ e.stopPropagation(); const idx=parseInt(btn.dataset.idx,10); calendarEvents[idx].manual=true; renderCalendarSmall(); }); });
      calendarSmall.querySelectorAll('.kvt-cal-reject').forEach(btn=>{ btn.addEventListener('click', e=>{ e.stopPropagation(); const idx=parseInt(btn.dataset.idx,10); removeCalendarEvent(idx); }); });
      calendarSmall.querySelectorAll('.kvt-cal-cell').forEach(cell=>{
        cell.addEventListener('dragover', e=>e.preventDefault());
        cell.addEventListener('drop', e=>{
          e.preventDefault();
          if(dragIdx!==null){
            calendarEvents[dragIdx].date = cell.dataset.date;
            calendarEvents[dragIdx].manual = true;
            dragIdx = null;
            renderCalendarSmall();
            renderCalendar();
          }
        });
      });
      mitBtn.addEventListener('click', loadMitCalendar);
    }

  function renderOverview(rows){
    if(!overview) return;
    const pid = selProcess ? selProcess.value : '';
    if(!pid){ overview.style.display='none'; overview.innerHTML=''; return; }
    const p = getProcessById(pid);
    if(!p){ overview.style.display='none'; overview.innerHTML=''; return; }
    const creator = p.creator || '—';
    let days = '';
    if(p.created){
      const parts = p.created.split('/');
      if(parts.length>=3){
        const d = new Date(parts[0],parts[1]-1,parts[2]);
        const today = new Date(); today.setHours(0,0,0,0);
        days = Math.max(0, Math.floor((today-d)/86400000));
      }
    }
    const count = rows.length;
    const sts = KVT_STATUSES.filter(s=>s !== 'Descartados');
    let maxIdx = -1; let maxStage = '—';
    rows.forEach(r=>{
      const idx = sts.indexOf(r.status||'');
      if(idx>maxIdx){ maxIdx=idx; maxStage=r.status||'—'; }
    });
    overview.style.display='block';
    overview.innerHTML = '<strong>Creado por:</strong> '+esc(creator)+' | <strong>Abierto hace:</strong> '+(days!==''?days:0)+' días | <strong>Candidatos vinculados:</strong> '+count+' | <strong>Etapa más avanzada:</strong> '+esc(maxStage);
  }

  function populateTaskProcesses(){
    if(!taskProcess) return;
    fetchProcessesList().then(j=>{
      if(j.success){
        taskProcess.innerHTML = '<option value="">Proceso (opcional)</option>' + j.data.items.filter(p=>p.status==='active').map(p=>'<option value="'+escAttr(p.id)+'">'+esc(p.name)+'</option>').join('');
      }
    });
  }

  function populateTaskCandidates(procId=''){
    if(!taskCandidate) return;
    fetchCandidatesAll(procId).then(j=>{
      if(j.success && Array.isArray(j.data.items)){
        taskCandidate.innerHTML = '<option value="">Candidato (opcional)</option>' + j.data.items.map(r=>{
          const name = esc(((r.meta.first_name||'')+' '+(r.meta.last_name||'')).trim());
          return '<option value="'+escAttr(r.id)+'">'+name+'</option>';
        }).join('');
      }
    });
  }

  function populateCalProcesses(sel){
    if(!sel) return;
    fetchProcessesList().then(j=>{
      if(j.success){
        sel.innerHTML = '<option value="">Proceso (opcional)</option>' + j.data.items.filter(p=>p.status==='active').map(p=>'<option value="'+escAttr(p.id)+'">'+esc(p.name)+'</option>').join('');
      }
    });
  }

  function populateCalCandidates(procId, sel){
    if(!sel) return;
    fetchCandidatesAll(procId).then(j=>{
      if(j.success && Array.isArray(j.data.items)){
        sel.innerHTML = '<option value="">Candidato (opcional)</option>' + j.data.items.map(r=>{
          const name = esc(((r.meta.first_name||'')+' '+(r.meta.last_name||'')).trim());
          return '<option value="'+escAttr(r.id)+'">'+name+'</option>';
        }).join('');
      }
    });
  }

  function populateCalClients(sel){
    if(!sel) return;
    fetchClientsList().then(j=>{
      if(j.success){
        sel.innerHTML = '<option value="">Cliente (opcional)</option>' + j.data.items.map(c=>'<option value="'+escAttr(c.id)+'">'+esc(c.name)+'</option>').join('');
      }
    });
  }

  function filterTable(){
    let rows = allRows.slice();
    const q = searchInput ? searchInput.value.toLowerCase().trim() : '';
    if(q){
      rows = rows.filter(r=>{
        const blob = (r.meta.first_name+' '+r.meta.last_name+' '+(r.client||'')+' '+(r.process||'')+' '+Object.values(r.meta).join(' ')).toLowerCase();
        return blob.includes(q);
      });
    }
    const st = (!selClient.value && !selProcess.value) ? '' : (stageSelect ? stageSelect.value : '');
    if(st){ rows = rows.filter(r=>r.status===st); }
    renderTable(rows);
  }

  function updatePager(){
    if(tablePager) tablePager.style.display = 'none';
  }

  function syncExportHidden(){
    el('#kvt_export_client').value  = selClient ? selClient.value : '';
    el('#kvt_export_process').value = selProcess ? selProcess.value : '';
  }

  function updateSelectedInfo(){
    const pid = selProcess && selProcess.value ? selProcess.value : '';
    if (addCandidate) addCandidate.style.display = pid ? 'inline-block' : 'none';
    if(!selInfo && !boardProcInfo){ return; }
    if(!pid){
      if(selInfo) selInfo.style.display='none';
      if(boardProcInfo) boardProcInfo.style.display='none';
      return;
    }
    if(!Array.isArray(window.KVT_PROCESS_MAP)){
      if(selInfo) selInfo.style.display='none';
      if(boardProcInfo) boardProcInfo.style.display='none';
      return;
    }
    const p = window.KVT_PROCESS_MAP.find(x=>String(x.id)===pid);
    if(!p){
      if(selInfo) selInfo.style.display='none';
      if(boardProcInfo) boardProcInfo.style.display='none';
      return;
    }
    const cl = getClientById(p.client_id);
    const clientName = cl ? cl.name||'' : (p.client||'');
    const statusMap = {active:'Activo', completed:'Cerrado', closed:'Cancelado'};
    const status = statusMap[p.status] || (p.status ? p.status.charAt(0).toUpperCase()+p.status.slice(1) : '');
    let days='';
    if(!CANDIDATE_VIEW && p.created){
      const cd = new Date(p.created);
      if(!isNaN(cd)) days = Math.floor((Date.now()-cd.getTime())/86400000)+' días';
    }
    const parts = [
      'Proceso: '+esc(p.name||''),
      'Cliente: '+esc(clientName),
      'Estado: '+esc(status)
    ];
    if(!CANDIDATE_VIEW){
      if(days) parts.push('Activo: '+days);
      if(typeof p.candidates!=='undefined') parts.push('Candidatos: '+p.candidates);
      if(p.job_stage) parts.push('Etapa: '+esc(p.job_stage));
    }
    const html = parts.map(t=>'<span>'+t+'</span>').join('');
    if(selInfo){ selInfo.style.display='flex'; selInfo.innerHTML = html; }
    if(boardProcInfo){ boardProcInfo.style.display='flex'; boardProcInfo.innerHTML = html; }
  }

  const exportForm = el('#kvt_export_form');
  btnXLS && btnXLS.addEventListener('click', ()=>{ el('#kvt_export_format').value='xls'; syncExportHidden(); exportForm.submit(); });
  btnAllXLS && btnAllXLS.addEventListener('click', ()=>{ exportAllFormat.value='xls'; exportAllForm && exportAllForm.submit(); });
  boardExportXls && boardExportXls.addEventListener('click', ()=>{ boardExportFormat.value='xls'; boardExportAllForm && boardExportAllForm.submit(); });
  const triggerLoadRoles = btn => {
    if(btn) btn.disabled = true;
    ajaxForm({action:'kvt_generate_roles', _ajax_nonce:KVT_NONCE})
      .then(res => {
        if(btn) btn.disabled = false;
        if(res && res.success){
          refresh();
          listProfiles(currentPage, boardCtx);
        } else {
          const msg = res && res.data && res.data.msg ? res.data.msg : 'No se pudo cargar roles y empresas';
          alert(msg);
        }
      })
      .catch(() => {
        if(btn) btn.disabled = false;
        alert('Error de red al cargar roles y empresas');
      });
  };
  navLoadRoles && navLoadRoles.addEventListener('click', e=>{ e.preventDefault(); triggerLoadRoles(navLoadRoles); });
  infoClose && infoClose.addEventListener('click', ()=>{ infoModal.style.display='none'; });
  infoModal && infoModal.addEventListener('click', e=>{ if(e.target===infoModal) infoModal.style.display='none'; });
  fbClose && fbClose.addEventListener('click', ()=>{ fbModal.style.display='none'; });
  fbModal && fbModal.addEventListener('click', e=>{ if(e.target===fbModal) fbModal.style.display='none'; });
  fbSave && fbSave.addEventListener('click', ()=>{
    const name = fbName.value.trim();
    const msg = fbText.value.trim();
    if(!name || !msg){ alert('Completa todos los campos'); return; }
    localStorage.setItem('kvtClientName', name);
    const p = new URLSearchParams();
    p.set('action','kvt_client_comment');
    p.set('_ajax_nonce', KVT_NONCE);
    p.set('id', fbCandidate||'');
    p.set('slug', CLIENT_SLUG);
    p.set('name', name);
    p.set('comment', msg);
    fetch(KVT_AJAX,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:p.toString()})
      .then(r=>r.json()).then(j=>{
        if(j.success){ fbModal.style.display='none'; refresh(); }
        else alert('No se pudo guardar.');
      });
  });
  aiBtn && aiBtn.addEventListener('click', ()=>{
    const desc = (aiInput.value||'').trim();
    if(!desc) return;
    aiResults.innerHTML = '<div class="kvt-loading">Buscando...</div>';
    aiBtn.disabled = true;
    const params = new URLSearchParams();
    params.set('action','kvt_ai_search');
    params.set('_ajax_nonce', KVT_NONCE);
    params.set('description', desc);
    const c = aiCountry ? aiCountry.value : '';
    if(c) params.set('country', c);
    fetch(KVT_AJAX,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:params.toString()})
      .then(r=>r.json()).then(j=>{
        aiBtn.disabled = false;
        if(!j.success){ alert('No se pudo buscar.'); aiResults.innerHTML=''; return; }
        const items = Array.isArray(j.data.items)?j.data.items:[];
        if(!items.length){ aiResults.innerHTML = '<p>No hay coincidencias reales.</p>'; return; }
        aiResults.innerHTML = items.map(it=>{
          const m = it.meta||{};
          const name = esc((m.first_name||'')+' '+(m.last_name||''));
          const cv = m.cv_url?'<a href="'+escAttr(m.cv_url)+'" class="kvt-cv-link dashicons dashicons-media-document" target="_blank" title="Ver CV"></a>':'';
          const roleLoc = [m.current_role||'', [m.country||'', m.city||''].map(s=>s.trim()).filter(Boolean).join(', ')].filter(Boolean).join(' / ');
          return '<div class="kvt-card-mini" data-id="'+it.id+'">'+
            '<h4>'+name+cv+'</h4>'+
            (roleLoc?'<p class="kvt-ai-meta">'+esc(roleLoc)+'</p>':'')+
            '<p class="kvt-ai-summary">'+esc(it.summary||'')+'</p>'+
            '<div class="kvt-mini-actions"><button type="button" class="kvt-btn kvt-secondary kvt-mini-view">Ver perfil</button></div>'+
          '</div>';
        }).join('');
        els('.kvt-mini-view', aiResults).forEach(b=>{
          b.addEventListener('click', ()=>{
            const card = b.closest('.kvt-card-mini');
            const id = card ? card.dataset.id : '';
            const item = items.find(i=>String(i.id)===id);
            if(item) openProfile(item);
          });
        });
      }).catch(()=>{ aiBtn.disabled=false; aiResults.innerHTML=''; });
  });

  aiBoardBtn && aiBoardBtn.addEventListener('click', ()=>{
    const desc = (aiBoardInput.value||'').trim();
    if(!desc) return;
    aiBoardResults.innerHTML = '<div class="kvt-loading">Buscando...</div>';
    aiBoardBtn.disabled = true;
    const params = new URLSearchParams();
    params.set('action','kvt_ai_search');
    params.set('_ajax_nonce', KVT_NONCE);
    params.set('description', desc);
    const c = aiBoardCountry ? aiBoardCountry.value : '';
    if(c) params.set('country', c);
    fetch(KVT_AJAX,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:params.toString()})
      .then(r=>r.json()).then(j=>{
        aiBoardBtn.disabled = false;
        if(!j.success){ alert('No se pudo buscar.'); aiBoardResults.innerHTML=''; return; }
        const items = Array.isArray(j.data.items)?j.data.items:[];
        if(!items.length){ aiBoardResults.innerHTML = '<p>No hay coincidencias reales.</p>'; return; }
        aiBoardResults.innerHTML = items.map(it=>{
          const m = it.meta||{};
          const name = esc((m.first_name||'')+' '+(m.last_name||''));
          const cv = m.cv_url?'<a href="'+escAttr(m.cv_url)+'" class="kvt-cv-link dashicons dashicons-media-document" target="_blank" title="Ver CV"></a>':'';
          const roleLoc = [m.current_role||'', [m.country||'', m.city||''].map(s=>s.trim()).filter(Boolean).join(', ')].filter(Boolean).join(' / ');
          return '<div class="kvt-card-mini" data-id="'+it.id+'">'+
            '<h4>'+name+cv+'</h4>'+
            (roleLoc?'<p class="kvt-ai-meta">'+esc(roleLoc)+'</p>':'')+
            '<p class="kvt-ai-summary">'+esc(it.summary||'')+'</p>'+
            '<div class="kvt-mini-actions"><button type="button" class="kvt-btn kvt-secondary kvt-mini-view">Ver perfil</button></div>'+
          '</div>';
        }).join('');
        els('.kvt-mini-view', aiBoardResults).forEach(b=>{
          b.addEventListener('click', ()=>{
            const card = b.closest('.kvt-card-mini');
            const id = card ? card.dataset.id : '';
            const item = items.find(i=>String(i.id)===id);
            if(item) openProfile(item);
          });
        });
      }).catch(()=>{ aiBoardBtn.disabled=false; aiBoardResults.innerHTML=''; });
  });

  keywordBtn && keywordBtn.addEventListener('click', ()=>{
    const query = (keywordInput.value||'').trim();
    if(!query) return;
    keywordResults.innerHTML = '<div class="kvt-loading">Buscando...</div>';
    keywordBtn.disabled = true;
    const params = new URLSearchParams();
    params.set('action','kvt_keyword_search');
    params.set('_ajax_nonce', KVT_NONCE);
    params.set('query', query);
    const c = keywordCountry ? keywordCountry.value : '';
    if(c) params.set('country', c);
    fetch(KVT_AJAX,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:params.toString()})
      .then(r=>r.json()).then(j=>{
        keywordBtn.disabled = false;
        if(!j.success){ alert('No se pudo buscar.'); keywordResults.innerHTML=''; return; }
        const items = Array.isArray(j.data.items)?j.data.items:[];
        if(!items.length){ keywordResults.innerHTML = '<p>No hay coincidencias.</p>'; return; }
        keywordResults.innerHTML = items.map(it=>{
          const m = it.meta||{};
          const name = esc((m.first_name||'')+' '+(m.last_name||''));
          const cv = m.cv_url?'<a href="'+escAttr(m.cv_url)+'" class="kvt-cv-link dashicons dashicons-media-document" target="_blank" title="Ver CV"></a>':'';
          const roleLoc = [m.current_role||'', [m.country||'', m.city||''].map(s=>s.trim()).filter(Boolean).join(', ')].filter(Boolean).join(' / ');
          const matches = (it.matches||[]).join(', ');
          return '<div class="kvt-card-mini" data-id="'+it.id+'">'+
            '<h4>'+name+cv+'</h4>'+
            (roleLoc?'<p class="kvt-ai-meta">'+esc(roleLoc)+'</p>':'')+
            '<p class="kvt-ai-summary"><strong>Palabras clave:</strong> '+esc(matches)+'</p>'+
            '<div class="kvt-mini-actions"><button type="button" class="kvt-btn kvt-secondary kvt-mini-view">Ver perfil</button></div>'+
          '</div>';
        }).join('');
        els('.kvt-mini-view', keywordResults).forEach(b=>{
          b.addEventListener('click', ()=>{
            const card = b.closest('.kvt-card-mini');
            const id = card ? card.dataset.id : '';
            const item = items.find(i=>String(i.id)===id);
            if(item) openProfile(item);
          });
        });
      }).catch(()=>{ keywordBtn.disabled=false; keywordResults.innerHTML=''; });
  });

  keywordBoardBtn && keywordBoardBtn.addEventListener('click', ()=>{
    const query = (keywordBoardInput.value||'').trim();
    if(!query) return;
    keywordBoardResults.innerHTML = '<div class="kvt-loading">Buscando...</div>';
    keywordBoardBtn.disabled = true;
    const params = new URLSearchParams();
    params.set('action','kvt_keyword_search');
    params.set('_ajax_nonce', KVT_NONCE);
    params.set('query', query);
    const c = keywordBoardCountry ? keywordBoardCountry.value : '';
    if(c) params.set('country', c);
    fetch(KVT_AJAX,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:params.toString()})
      .then(r=>r.json()).then(j=>{
        keywordBoardBtn.disabled = false;
        if(!j.success){ alert('No se pudo buscar.'); keywordBoardResults.innerHTML=''; return; }
        const items = Array.isArray(j.data.items)?j.data.items:[];
        if(!items.length){ keywordBoardResults.innerHTML = '<p>No hay coincidencias.</p>'; return; }
        keywordBoardResults.innerHTML = items.map(it=>{
          const m = it.meta||{};
          const name = esc((m.first_name||'')+' '+(m.last_name||''));
          const cv = m.cv_url?'<a href="'+escAttr(m.cv_url)+'" class="kvt-cv-link dashicons dashicons-media-document" target="_blank" title="Ver CV"></a>':'';
          const roleLoc = [m.current_role||'', [m.country||'', m.city||''].map(s=>s.trim()).filter(Boolean).join(', ')].filter(Boolean).join(' / ');
          const matches = (it.matches||[]).join(', ');
          return '<div class="kvt-card-mini" data-id="'+it.id+'">'+
            '<h4>'+name+cv+'</h4>'+
            (roleLoc?'<p class="kvt-ai-meta">'+esc(roleLoc)+'</p>':'')+
            '<p class="kvt-ai-summary"><strong>Palabras clave:</strong> '+esc(matches)+'</p>'+
            '<div class="kvt-mini-actions"><button type="button" class="kvt-btn kvt-secondary kvt-mini-view">Ver perfil</button></div>'+
          '</div>';
        }).join('');
        els('.kvt-mini-view', keywordBoardResults).forEach(b=>{
          b.addEventListener('click', ()=>{
            const card = b.closest('.kvt-card-mini');
            const id = card ? card.dataset.id : '';
            const item = items.find(i=>String(i.id)===id);
            if(item) openProfile(item);
          });
        });
      }).catch(()=>{ keywordBoardBtn.disabled=false; keywordBoardResults.innerHTML=''; });
  });

  toggleKanban && toggleKanban.addEventListener('click', () => {
    if(!boardWrap) return;
    const hidden = boardWrap.style.display === 'none' || !boardWrap.style.display;
    boardWrap.style.display = hidden ? 'block' : 'none';
    toggleKanban.textContent = hidden ? 'Ocultar Kanban' : 'Mostrar Kanban';
  });

  tBody && tBody.addEventListener('click', e=>{
    const fbBtn = e.target.closest('.kvt-feedback-btn');
    if(fbBtn && CLIENT_VIEW && ALLOW_COMMENTS){
      e.preventDefault();
      fbCandidate = fbBtn.dataset.id;
      fbName.value = localStorage.getItem('kvtClientName') || '';
      fbText.value='';
      fbModal.style.display='flex';
      return;
    }
    if(CLIENT_VIEW || CANDIDATE_VIEW) return;
    const step = e.target.closest('.kvt-stage-step');
    if(step){
      stageId = step.dataset.id;
      stageNext = step.dataset.status;
      if(stageModal) stageModal.style.display='flex';
      return;
    }
    const del = e.target.closest('.kvt-row-remove');
    if(del){
      if(!confirm('¿Quitar a este candidato del proceso?')) return;
      const id = del.dataset.id;
      ajaxForm({
        action:'kvt_unassign_candidate',
        _ajax_nonce:KVT_NONCE,
        id:id,
        client_id: selClient ? selClient.value || '' : '',
        process_id: selProcess ? selProcess.value || '' : ''
      }).then(j=>{ if(j.success) del.closest('tr').remove(); else alert('No se pudo eliminar.'); });
    }
  });

  stageClose && stageClose.addEventListener('click', ()=>{ if(stageModal) stageModal.style.display='none'; });
  stageModal && stageModal.addEventListener('click', e=>{ if(e.target===stageModal) stageModal.style.display='none'; });
  stageForm && stageForm.addEventListener('submit', e=>{
    e.preventDefault();
    const params = new URLSearchParams();
    params.set('action','kvt_update_status');
    params.set('_ajax_nonce', KVT_NONCE);
    params.set('id', stageId);
    params.set('status', stageNext);
    params.set('comment', stageComment.value.trim());
    params.set('author', KVT_CURRENT_USER || '');
    fetch(KVT_AJAX,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:params.toString()})
      .then(r=>r.json()).then(()=>{ if(stageModal) stageModal.style.display='none'; refresh(); });
  });
  activityTabs.forEach(tab=>{
    tab.addEventListener('click', ()=>{
      activityTabs.forEach(t=>t.classList.remove('active'));
      activityViews.forEach(v=>v.style.display='none');
      tab.classList.add('active');
      const pane = el('#kvt_activity_'+tab.dataset.target);
      if(pane) pane.style.display='block';
    });
  });

  taskForm && taskForm.addEventListener('submit', e=>{
    e.preventDefault();
    const id = taskCandidate.value;
    const date = formatInputDate(taskDate.value);
    const time = taskTime.value;
    const note = taskNote.value;
    if(!id || !date) return;
    const params = new URLSearchParams();
    params.set('action','kvt_add_task');
    params.set('_ajax_nonce', KVT_NONCE);
    params.set('id', id);
    params.set('date', date);
    params.set('time', time);
    params.set('note', note);
    params.set('author', KVT_CURRENT_USER || '');
    fetch(KVT_AJAX,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:params.toString()})
      .then(r=>r.json()).then(()=>{ refresh(); taskForm.reset(); if(taskModalWrap) taskModalWrap.style.display='none'; });
  });

  function handleTaskClick(e){
    const li = e.target.closest('li');
    if(!li) return;
    const id = li.dataset.id;
    if(e.target.classList.contains('kvt-task-done')){
      const comment = prompt('Comentario (opcional)','') || '';
      const params = new URLSearchParams();
      params.set('action','kvt_complete_task');
      params.set('_ajax_nonce', KVT_NONCE);
      params.set('id', id);
      params.set('author', KVT_CURRENT_USER || '');
      params.set('comment', comment);
      fetch(KVT_AJAX,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:params.toString()})
        .then(r=>r.json()).then(()=>refresh());
    } else if(e.target.classList.contains('kvt-task-delete')){
      if(!confirm('¿Eliminar tarea?')) return;
      const params = new URLSearchParams();
      params.set('action','kvt_delete_task');
      params.set('_ajax_nonce', KVT_NONCE);
      params.set('id', id);
      params.set('author', KVT_CURRENT_USER || '');
      fetch(KVT_AJAX,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:params.toString()})
        .then(r=>r.json()).then(()=>refresh());
    }
  }

  function handleNotificationClick(e){
    const li = e.target.closest('li');
    if(!li) return;
    if(e.target.classList.contains('kvt-comment-dismiss')){
      const id = li.dataset.id;
      const idx = li.dataset.index;
      dismissComment(id, idx);
      refresh();
    }
  }

  activityDue && activityDue.addEventListener('click', handleTaskClick);
  activityUpcoming && activityUpcoming.addEventListener('click', handleTaskClick);
  activityNotify && activityNotify.addEventListener('click', handleNotificationClick);

  searchInput && searchInput.addEventListener('input', filterTable);
  stageSelect && stageSelect.addEventListener('change', filterTable);
  document.addEventListener('click', e=>{
    if(e.target.classList.contains('kvt-row-view')){
      const id = e.target.dataset.id;
      selectedCandidateId = parseInt(id,10) || 0;
      selectedCandidateIds = [selectedCandidateId];
      const cand = allRows.find(r=>String(r.id)===id);
      if(cand) openProfile(cand);
    }
  });

  function refresh(){
    fetchCandidates().then(j=>{
      if(j.success){
        totalPages = j.data.pages || 1;
        renderData(j.data.items || []);
        const baseMode = !selClient.value && !selProcess.value;
        if(baseMode){
          fetchDashboard().then(d=>{ if(d.success) renderActivityDashboard(d.data); });
        } else {
          renderActivity(allRows);
          renderOverview(allRows);
        }
        populateTaskCandidates();
        updatePager();
      } else {
        alert('Error cargando datos');
      }
    });
  }
  selClient && selClient.addEventListener('change', ()=>{ currentPage=1; filterProcessOptions(); refresh(); updateSelectedInfo(); });
  selProcess && selProcess.addEventListener('change', ()=>{ currentPage=1; refresh(); updateSelectedInfo(); });
  btnTaskOpen && btnTaskOpen.addEventListener('click', e=>{ e.preventDefault(); if(taskModalWrap){ taskModalWrap.style.display='flex'; populateTaskProcesses(); populateTaskCandidates(); } });
  taskProcess && taskProcess.addEventListener('change', ()=>{ populateTaskCandidates(taskProcess.value); });
  taskClose && taskClose.addEventListener('click', ()=>{ if(taskModalWrap) taskModalWrap.style.display='none'; });
  taskModalWrap && taskModalWrap.addEventListener('click', e=>{ if(e.target===taskModalWrap) taskModalWrap.style.display='none'; });
  btnShare && btnShare.addEventListener('click', e=>{
    e.preventDefault();
    if (!selClient || !selClient.value || !selProcess || !selProcess.value) {
      alert('Selecciona un cliente y un proceso.');
      return;
    }
    shareMode = 'client';
    buildShareOptions();
    if(shareModal) shareModal.style.display='flex';
  });
  tablePrev && tablePrev.addEventListener('click', ()=>{ if(currentPage>1){ currentPage--; refresh(); } });
  tableNext && tableNext.addEventListener('click', ()=>{ if(currentPage<totalPages){ currentPage++; refresh(); } });
  shareClose && shareClose.addEventListener('click', ()=>{ shareModal.style.display='none'; forceSelect=false; selectedCandidateIds=[]; selectedCandidateId=0; EDIT_SLUG=''; });
  shareModal && shareModal.addEventListener('click', e=>{ if(e.target===shareModal){ shareModal.style.display='none'; forceSelect=false; selectedCandidateIds=[]; selectedCandidateId=0; EDIT_SLUG=''; }});
  shareFieldsAll && shareFieldsAll.addEventListener('change', ()=>{
    els('input[type="checkbox"]', shareFieldsWrap).forEach(cb=>cb.checked = shareFieldsAll.checked);
  });
  shareStepsAll && shareStepsAll.addEventListener('change', ()=>{
    els('input[type="checkbox"]', shareStepsWrap).forEach(cb=>cb.checked = shareStepsAll.checked);
  });
  shareFieldsWrap && shareFieldsWrap.addEventListener('change', ()=>{
    shareFieldsAll.checked = els('input[type="checkbox"]', shareFieldsWrap).every(cb=>cb.checked);
  });
  shareStepsWrap && shareStepsWrap.addEventListener('change', ()=>{
    shareStepsAll.checked = els('input[type="checkbox"]', shareStepsWrap).every(cb=>cb.checked);
  });
  boardsView && boardsView.addEventListener('click', e=>{
    const cfgBtn = e.target.closest('.kvt-config-board');
    if (cfgBtn) {
      e.preventDefault();
      EDIT_SLUG = cfgBtn.dataset.slug || '';
      shareMode = cfgBtn.dataset.type === 'candidate' ? 'candidate' : 'client';
      if (selClient) selClient.value = cfgBtn.dataset.client || '';
      filterProcessOptions();
      if (selProcess) selProcess.value = cfgBtn.dataset.process || '';
      selectedCandidateId = shareMode === 'candidate' ? parseInt(cfgBtn.dataset.candidate || '0', 10) : 0;
      selectedCandidateIds = selectedCandidateId ? [selectedCandidateId] : [];
      ALLOWED_FIELDS = JSON.parse(cfgBtn.dataset.fields || '[]');
      ALLOWED_STEPS = JSON.parse(cfgBtn.dataset.steps || '[]');
      ALLOW_COMMENTS = cfgBtn.dataset.comments === '1';
      refresh();
      updateSelectedInfo();
      buildShareOptions();
      shareModal && (shareModal.style.display = 'flex');
      return;
    }
    const delBtn = e.target.closest('.kvt-delete-board');
    if (delBtn) {
      e.preventDefault();
      if (!confirm('¿Eliminar este tablero?')) return;
      const params = new URLSearchParams();
      params.set('action', 'kvt_delete_board');
      params.set('_ajax_nonce', KVT_NONCE);
      params.set('type', delBtn.dataset.type);
      params.set('slug', delBtn.dataset.slug);
      fetch(KVT_AJAX, { method: 'POST', body: params }).then(r => r.json()).then(j => {
        if (j.success) {
          const tr = delBtn.closest('tr');
          tr && tr.remove();
        } else {
          alert('Error eliminando tablero');
        }
      });
    }
  });

    shareGenerate && shareGenerate.addEventListener('click', ()=>{
      const fields = els('input[type="checkbox"]', shareFieldsWrap).filter(cb=>cb.checked).map(cb=>cb.value);
      const steps  = els('input[type="checkbox"]', shareStepsWrap).filter(cb=>cb.checked).map(cb=>cb.value);
      const baseParams = () => {
        const params = new URLSearchParams();
        params.set('action','kvt_generate_share_link');
        params.set('_ajax_nonce', KVT_NONCE);
        params.set('client', selClient.value);
        params.set('process', selProcess.value);
        params.set('page', '');
        fields.forEach(f=>params.append('fields[]', f));
        steps.forEach(s=>params.append('steps[]', s));
        if(shareComments && shareComments.checked) params.set('comments','1');
        if((CLIENT_VIEW || CANDIDATE_VIEW) && CLIENT_SLUG) params.set('slug', CLIENT_SLUG);
        else if(EDIT_SLUG) params.set('slug', EDIT_SLUG);
        return params;
      };
      if(shareMode==='candidate' && selectedCandidateIds.length>1){
        const promises = selectedCandidateIds.map(id=>{
          const params = baseParams();
          params.set('candidate', id);
          return fetch(KVT_AJAX,{method:'POST',body:params}).then(r=>r.json());
        });
        Promise.all(promises).then(res=>{
          const urls = res.filter(j=>j.success && j.data && j.data.slug).map(j=>KVT_HOME + j.data.slug);
          if(urls.length) prompt('Enlaces para compartir', urls.join('\n'));
          else alert('Error generando enlace');
          shareModal.style.display='none';
          EDIT_SLUG='';
          forceSelect = false;
          selectedCandidateIds = [];
          selectedCandidateId = 0;
          refresh();
        });
        return;
      }
      const params = baseParams();
      if(shareMode==='candidate') params.set('candidate', selectedCandidateId);
      fetch(KVT_AJAX,{method:'POST',body:params}).then(r=>r.json()).then(j=>{
        if(j.success && j.data && j.data.slug){
          const slug = j.data.slug;
          if(!CLIENT_VIEW && !CANDIDATE_VIEW){
            const url = KVT_HOME + slug;
            if(shareMode==='client'){
              CLIENT_LINKS[selClient.value+'|'+selProcess.value] = slug;
              prompt('Enlace para compartir', url);
              shareModal.style.display='none';
              EDIT_SLUG='';
              updateSelectedInfo();
            } else {
              prompt('Enlace para compartir', url);
              shareModal.style.display='none';
              EDIT_SLUG='';
            }
          } else {
            shareModal.style.display='none';
            EDIT_SLUG='';
            location.reload();
          }
          forceSelect = false;
          selectedCandidateIds = [];
          selectedCandidateId = 0;
          refresh();
        } else {
          alert('Error generando enlace');
        }
      });
    });

  updateSelectedInfo();

  // Base list (modal or board)
  function listProfiles(page, ctx){
    currentPage = page || 1;
    ctx = ctx || modalCtx;
    const params = new URLSearchParams();
    params.set('action','kvt_list_profiles');
    params.set('_ajax_nonce', KVT_NONCE);
    params.set('page', currentPage);
    params.set('name', ctx.name ? ctx.name.value : '');
    params.set('role', ctx.role ? ctx.role.value : '');
    params.set('location', ctx.loc ? ctx.loc.value : '');
    fetch(KVT_AJAX,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:params.toString()})
      .then(r=>r.json())
      .then(j=>{
        if(!j.success) return alert('No se pudo cargar la lista.');
        const {items,pages} = j.data;
        const procSel = selProcess && selProcess.value;
        const cliSel  = selClient && selClient.value;
        const allowAdd = !!(procSel && (cliSel || getClientIdForProcess(procSel)));
        const filterActive = (ctx.name && ctx.name.value) || (ctx.role && ctx.role.value) || (ctx.loc && ctx.loc.value);
        const showSelect = modalSelectMode || filterActive;
        let html = items.map(it=>{
          const m = it.meta||{};
          const name = esc((m.first_name||'')+' '+(m.last_name||''));
          const role = esc(m.current_role||'');
          const loc = [m.country||'', m.city||''].map(s=>s.trim()).filter(Boolean).map(esc).join(', ');
          const firstParts = ['<a href="#" class="kvt-name kvt-mini-view" data-id="'+it.id+'">'+name+'</a>'];
          if(role) firstParts.push('('+role+')');
          if(loc) firstParts.push(loc);
          const firstLine = firstParts.join(' / ');
          const date = esc(m.cv_uploaded||'');
          const infoParts = ['Candidato/a'];
          if(date) infoParts.push(date);
          const infoLine = '<em>'+infoParts.join(' / ')+'</em>';
          const cv = m.cv_url?'<a href="'+escAttr(m.cv_url)+'" class="kvt-cv-link dashicons dashicons-media-document" target="_blank" title="Ver CV"></a>':'';
          const firstLineWithCv = firstLine.replace('</a>', '</a>'+cv);
          const check = showSelect?'<div class="kvt-check"><input type="checkbox" class="kvt-select" value="'+it.id+'" aria-label="Seleccionar"></div>':'';
          const addBtn = allowAdd?'<button type="button" class="kvt-btn kvt-mini-add" data-id="'+it.id+'">Añadir</button>':'';
          const editBtn = '<button type="button" class="kvt-edit kvt-mini-view kvt-mini-edit dashicons dashicons-edit" data-id="'+it.id+'" data-label="Editar perfil" aria-label="Editar perfil"></button>';
            return '<div class="kvt-card-mini" data-id="'+it.id+'">'+
            '<div class="kvt-row'+(showSelect?' with-check':'')+'">'+
              check+
              '<div>'+firstLineWithCv+'<br>'+infoLine+'</div>'+
              '<div class="kvt-meta"><button type="button" class="kvt-delete kvt-mini-delete dashicons dashicons-trash" data-id="'+it.id+'" aria-label="Eliminar"></button>'+editBtn+addBtn+'</div>'+
            '</div>'+
          '</div>';
        }).join('');
        ctx.list.innerHTML = html;
        ctx.page.textContent = 'Página '+currentPage+' de '+(pages||1);
        if(ctx.prev) ctx.prev.style.display = pages>1 ? 'inline-block' : 'none';
        if(ctx.next) ctx.next.style.display = pages>1 ? 'inline-block' : 'none';
        if(allowAdd){
          els('.kvt-mini-add', ctx.list).forEach(b=>{
            b.addEventListener('click', ()=>{
              const id = b.getAttribute('data-id');
              const proc = selProcess.value;
              let cli  = selClient.value;
              if(!cli) cli = getClientIdForProcess(proc);
              if(!proc || !cli){ alert('Seleccione cliente y proceso en el tablero.'); return; }
              const p = new URLSearchParams();
              p.set('action','kvt_assign_candidate');
              p.set('_ajax_nonce', KVT_NONCE);
              p.set('candidate_id', id);
              p.set('process_id', proc);
              p.set('client_id', cli);
              fetch(KVT_AJAX,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:p.toString()})
                .then(r=>r.json()).then(j=>{
                  if(!j.success) return alert(j.data && j.data.msg ? j.data.msg : 'No se pudo asignar.');
                  alert('Candidato asignado.');
                  if(ctx.close) ctx.close();
                  refresh();
                });
            });
          });
        }
        if(ctx.assign) ctx.assign.style.display = (showSelect && allowAdd) ? 'inline-flex' : 'none';
        els('.kvt-mini-view', ctx.list).forEach(b=>{
          b.addEventListener('click', e=>{
            e.preventDefault();
            const id = b.dataset.id || (b.closest('.kvt-card-mini') && b.closest('.kvt-card-mini').dataset.id);
            const item = items.find(it=>String(it.id)===String(id));
            if(item) openProfile(item);
          });
        });
        els('.kvt-mini-delete', ctx.list).forEach(b=>{
          b.addEventListener('click', ()=>{
            if(!confirm('¿Enviar este candidato a la papelera?')) return;
            const id = b.getAttribute('data-id');
            const p = new URLSearchParams();
            p.set('action','kvt_delete_candidate');
            p.set('_ajax_nonce', KVT_NONCE);
            p.set('id', id);
            fetch(KVT_AJAX,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:p.toString()})
              .then(r=>r.json()).then(j=>{
                if(!j.success) return alert(j.data && j.data.msg ? j.data.msg : 'No se pudo eliminar.');
                alert('Candidato eliminado.');
                listProfiles(currentPage, ctx);
                refresh();
              });
          });
        });
        // profile editing handled in modal
      });
  }
  modalPrev && modalPrev.addEventListener('click', ()=>{ if(currentPage>1) listProfiles(currentPage-1, modalCtx); });
  modalNext && modalNext.addEventListener('click', ()=>{ listProfiles(currentPage+1, modalCtx); });
  boardPrev && boardPrev.addEventListener('click', ()=>{ if(currentPage>1) listProfiles(currentPage-1, boardCtx); });
  boardNext && boardNext.addEventListener('click', ()=>{ listProfiles(currentPage+1, boardCtx); });
  modalAssign && modalAssign.addEventListener('click', ()=>{
    const ids = Array.from(els('.kvt-select:checked', modalList)).map(cb=>cb.value);
    if(!ids.length){ alert('Seleccione candidatos'); return; }
    const proc = selProcess && selProcess.value;
    let cli  = selClient && selClient.value;
    if(!proc){ alert('Seleccione proceso en el tablero.'); return; }
    if(!cli) cli = getClientIdForProcess(proc);
    const assignOne = id => {
      const p = new URLSearchParams();
      p.set('action','kvt_assign_candidate');
      p.set('_ajax_nonce', KVT_NONCE);
      p.set('candidate_id', id);
      p.set('process_id', proc);
      p.set('client_id', cli);
      return fetch(KVT_AJAX,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:p.toString()}).then(r=>r.json());
    };
    Promise.all(ids.map(assignOne)).then(()=>{ alert('Candidatos asignados.'); closeModal(); refresh(); });
  });
  boardAssign && boardAssign.addEventListener('click', ()=>{
    const ids = Array.from(els('.kvt-select:checked', boardList)).map(cb=>cb.value);
    if(!ids.length){ alert('Seleccione candidatos'); return; }
    const proc = selProcess && selProcess.value;
    let cli  = selClient && selClient.value;
    if(!proc){ alert('Seleccione proceso en el tablero.'); return; }
    if(!cli) cli = getClientIdForProcess(proc);
    const assignOne = id => {
      const p = new URLSearchParams();
      p.set('action','kvt_assign_candidate');
      p.set('_ajax_nonce', KVT_NONCE);
      p.set('candidate_id', id);
      p.set('process_id', proc);
      p.set('client_id', cli);
      return fetch(KVT_AJAX,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:p.toString()}).then(r=>r.json());
    };
    Promise.all(ids.map(assignOne)).then(()=>{ alert('Candidatos asignados.'); refresh(); });
  });
  addCandidate && addCandidate.addEventListener('click', ()=>{ openModal('candidates'); });
  addCandidateTable && addCandidateTable.addEventListener('click', ()=>{
    if(!selProcess || !selProcess.value){ alert('Seleccione proceso en el tablero.'); return; }
    openModal('candidates', true);
  });
  let mto=null;
  [modalName, modalRole, modalLoc].forEach(inp=>{
    inp && inp.addEventListener('input', ()=>{ clearTimeout(mto); mto=setTimeout(()=>listProfiles(1, modalCtx),300); });
  });
  [boardName, boardRole, boardLoc].forEach(inp=>{
    inp && inp.addEventListener('input', ()=>{ clearTimeout(mto); mto=setTimeout(()=>listProfiles(1, boardCtx),300); });
  });

  function listClients(target){
    const params = new URLSearchParams();
    params.set('action','kvt_list_clients');
    params.set('_ajax_nonce', KVT_NONCE);
    fetch(KVT_AJAX,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:params.toString()})
      .then(r=>r.json()).then(j=>{
        if(!j.success) return alert('No se pudo cargar la lista.');
        if(Array.isArray(window.KVT_CLIENT_MAP)){
          j.data.items.forEach(c=>{
            const idx = window.KVT_CLIENT_MAP.findIndex(x=>x.id===c.id);
            if(idx>=0){
              window.KVT_CLIENT_MAP[idx].name = c.name;
              window.KVT_CLIENT_MAP[idx].contact_name = c.contact_name;
              window.KVT_CLIENT_MAP[idx].contact_email = c.contact_email;
              window.KVT_CLIENT_MAP[idx].contact_phone = c.contact_phone;
              window.KVT_CLIENT_MAP[idx].description = c.description;
              window.KVT_CLIENT_MAP[idx].sector = c.sector;
              window.KVT_CLIENT_MAP[idx].meetings = c.meetings;
            } else {
              window.KVT_CLIENT_MAP.push(c);
            }
          });
        }
        const targets = target ? [target] : [clientsList, boardClientsList].filter(Boolean);
        const html = j.data.items.map(c=>{
          const subs=[];
          if(c.contact_name) subs.push(esc(c.contact_name)+(c.contact_email?' ('+esc(c.contact_email)+')':''));
          if(c.contact_phone) subs.push(esc(c.contact_phone));
          if(c.sector) subs.push(esc(c.sector));
          if(c.description) subs.push(esc(c.description));
          if(c.processes && c.processes.length) subs.push(esc(c.processes.join(', ')));
          const subHtml = subs.length?'<br><span class="kvt-sub">'+subs.join(' / ')+'</span>':'';
          return '<div class="kvt-row kvt-client-row" ' +
            'data-id="'+escAttr(c.id)+'" ' +
            'data-name="'+escAttr(c.name)+'" ' +
            'data-contact-name="'+escAttr(c.contact_name||'')+'" ' +
            'data-contact-email="'+escAttr(c.contact_email||'')+'" ' +
            'data-contact-phone="'+escAttr(c.contact_phone||'')+'" ' +
            'data-sector="'+escAttr(c.sector||'')+'" ' +
            'data-desc="'+escAttr(c.description||'')+'" ' +
            'data-meetings="'+escAttr(c.meetings||'')+'">'+
            '<div><span class="kvt-name">'+esc(c.name)+'</span>'+subHtml+'</div>'+
            '<div class="kvt-meta"><button type="button" class="kvt-edit-profile dashicons dashicons-edit"></button></div>'+
          '</div>';
        }).join('');
        targets.forEach(t=>{
          t.innerHTML = html;
          els('.kvt-client-row', t).forEach(r=>r.addEventListener('click', handleClientClick));
          els('.kvt-edit-profile', t).forEach(b=>b.addEventListener('click', e=>{ e.stopPropagation(); handleClientClick(e); }));
        });
      });
  }

  function listProcesses(target){
    const params = new URLSearchParams();
    params.set('action','kvt_list_processes');
    params.set('_ajax_nonce', KVT_NONCE);
    const st = procStatusFilter ? procStatusFilter.value : '';
    const cl = procClientFilter ? procClientFilter.value : '';
    if(st) params.set('status', st);
    if(cl) params.set('client', cl);
    fetch(KVT_AJAX,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:params.toString()})
      .then(r=>r.json()).then(j=>{
        if(!j.success) return alert('No se pudo cargar la lista.');
        if(Array.isArray(window.KVT_PROCESS_MAP)){
          j.data.items.forEach(p=>{
            const idx = window.KVT_PROCESS_MAP.findIndex(x=>x.id===p.id);
            if(idx>=0){
              window.KVT_PROCESS_MAP[idx].name = p.name;
              window.KVT_PROCESS_MAP[idx].contact_name = p.contact_name;
              window.KVT_PROCESS_MAP[idx].contact_email = p.contact_email;
              window.KVT_PROCESS_MAP[idx].description = p.description;
              window.KVT_PROCESS_MAP[idx].creator = p.creator;
              window.KVT_PROCESS_MAP[idx].created = p.created;
              window.KVT_PROCESS_MAP[idx].status = p.status;
              window.KVT_PROCESS_MAP[idx].days = p.days;
              window.KVT_PROCESS_MAP[idx].job_stage = p.job_stage;
              window.KVT_PROCESS_MAP[idx].candidates = p.candidates;
              if(typeof p.client!=='undefined') window.KVT_PROCESS_MAP[idx].client = p.client;
              if(typeof p.client_id!=='undefined') window.KVT_PROCESS_MAP[idx].client_id = p.client_id;
              if(typeof p.meetings!=='undefined') window.KVT_PROCESS_MAP[idx].meetings = p.meetings;
            } else {
              window.KVT_PROCESS_MAP.push(p);
            }
          });
        }
        const targets = target ? [target] : [processesList, boardProcessesList].filter(Boolean);
        const statuses = {active:'Activo',completed:'Completado',closed:'Cerrado'};
        const html = j.data.items.map(p=>{
          const subs=[];
          if(p.client) subs.push('Empresa: '+esc(p.client));
          subs.push('Creado por '+esc(p.creator||'')+(p.created?' el '+esc(p.created):''));
          subs.push('Candidatos: '+(p.candidates||0)+' / Etapa: '+esc(p.job_stage||''));
          const subHtml = '<br><span class="kvt-sub">'+subs.join(' / ')+'</span>';
          const sel = '<select class="kvt-process-status" data-id="'+escAttr(p.id)+'">'+
            Object.keys(statuses).map(s=>'<option value="'+s+'"'+(p.status===s?' selected':'')+'>'+statuses[s]+'</option>').join('')+
            '</select>';
          const days = '<span class="kvt-active-days">Activo '+p.days+' días</span>';
          return '<div class="kvt-row kvt-process-row" data-id="'+escAttr(p.id)+'" data-client="'+escAttr(p.client_id||'')+'">'+
            '<div><span class="kvt-name">'+esc(p.name)+'</span>'+subHtml+'</div>'+
            '<div class="kvt-meta">'+sel+' '+days+' <button type="button" class="kvt-btn kvt-edit-process" data-id="'+escAttr(p.id)+'" data-name="'+escAttr(p.name||'')+'" data-client-id="'+escAttr(p.client_id||'')+'" data-contact-name="'+escAttr(p.contact_name||'')+'" data-contact-email="'+escAttr(p.contact_email||'')+'" data-desc="'+escAttr(p.description||'')+'" data-meetings="'+escAttr(p.meetings||'')+'">Editar</button></div>'+
          '</div>';
        }).join('');
        targets.forEach(t=>{
          t.innerHTML = html;
          els('.kvt-process-status', t).forEach(s=>{
            s.addEventListener('change', ()=>{
              const id = s.getAttribute('data-id');
              const val = s.value;
              const p = new URLSearchParams();
              p.set('action','kvt_update_process_status');
              p.set('_ajax_nonce', KVT_NONCE);
              p.set('id', id);
              p.set('status', val);
              fetch(KVT_AJAX,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:p.toString()})
                .then(r=>r.json()).then(()=>listProcesses(target));
            });
          });
        });
      });
  }
  // Create candidate modal
    const cmodal = el('#kvt_create_modal');
  const cclose = el('#kvt_create_close');
  const cfirst   = el('#kvt_new_first');
  const clast    = el('#kvt_new_last');
  const cemail   = el('#kvt_new_email');
  const cphone   = el('#kvt_new_phone');
  const ccountry = el('#kvt_new_country');
  const ccity    = el('#kvt_new_city');
  const crole    = el('#kvt_new_role');
  const ccompany = el('#kvt_new_company');
  const ctags    = el('#kvt_new_tags');
  const ccvurl   = el('#kvt_new_cv_url');
  const ccvfile  = el('#kvt_new_cv_file');
  const ccvupload= el('#kvt_new_cv_upload');
  const ccli     = el('#kvt_new_client');
  const cproc    = el('#kvt_new_process');
  const csubmit  = el('#kvt_new_submit');

  function openCModal(){
    if (selClient && selClient.value) ccli.value = selClient.value;
    // Populate process by client if map available
    if (window.KVT_PROCESS_MAP && Array.isArray(window.KVT_PROCESS_MAP)) {
      const cid = parseInt(ccli.value||'0',10);
      cproc.innerHTML = '<option value=\"\">— Proceso —</option>';
      window.KVT_PROCESS_MAP.forEach(p=>{
        if (!cid || p.client_id === cid) {
          const opt = document.createElement('option');
          opt.value = String(p.id); opt.textContent = p.name;
          cproc.appendChild(opt);
        }
      });
    }
    cfirst.value=''; clast.value=''; cemail.value='';
    if (cphone)   cphone.value='';
    if (ccountry) ccountry.value='';
    if (ccity)    ccity.value='';
    if (crole)    crole.value='';
    if (ccompany) ccompany.value='';
    if (ctags)    ctags.value='';
    if (ccvurl)   ccvurl.value='';
    if (ccvfile)  ccvfile.value='';
    cmodal.style.display = 'flex';
  }
  function closeCModal(){ cmodal.style.display='none'; }
  cclose && cclose.addEventListener('click', closeCModal);
  cmodal && cmodal.addEventListener('click', (e)=>{ if(e.target===cmodal) closeCModal(); });
  document.querySelectorAll('.kvt-add-candidate').forEach(btn => {
    btn.addEventListener('click', openCModal);
  });
  ccli && ccli.addEventListener('change', ()=>{
    if (!window.KVT_PROCESS_MAP || !Array.isArray(window.KVT_PROCESS_MAP)) return;
    const cid = parseInt(ccli.value||'0',10);
    cproc.innerHTML = '<option value=\"\">— Proceso —</option>';
    window.KVT_PROCESS_MAP.forEach(p=>{ if(!cid || p.client_id===cid){ const o=document.createElement('option'); o.value=String(p.id); o.textContent=p.name; cproc.appendChild(o);} });
  });
  ccvupload && ccvupload.addEventListener('click', async ()=>{
    if (!ccvfile || !ccvfile.files || !ccvfile.files[0]) { alert('Selecciona un archivo.'); return; }
    const file = ccvfile.files[0];
    const fd = new FormData();
    fd.append('action','kvt_parse_cv');
    fd.append('_ajax_nonce', KVT_NONCE);
    fd.append('file', file);
    if (file.type === 'application/pdf') {
      let txt = await extractPdfWithPDFjs(file);
      if (!txt) txt = await ocrPdfWithTesseract(file);
      if (txt) fd.append('cv_text', txt);
    }
    const res = await fetch(KVT_AJAX,{method:'POST',body:fd});
    const j = await res.json();
    if(!j.success) return alert(j.data && j.data.msg ? j.data.msg : 'No se pudo analizar el CV.');
    if(j.data.fields){
      if(cfirst && j.data.fields.first_name) cfirst.value = j.data.fields.first_name;
      if(clast && j.data.fields.last_name) clast.value = j.data.fields.last_name;
      if(cemail && j.data.fields.email) cemail.value = j.data.fields.email;
      if(cphone && j.data.fields.phone) cphone.value = j.data.fields.phone;
      if(ccountry && j.data.fields.country) ccountry.value = j.data.fields.country;
      if(ccity && j.data.fields.city) ccity.value = j.data.fields.city;
      if(crole && j.data.fields.role) crole.value = j.data.fields.role;
      if(ccompany && j.data.fields.company) ccompany.value = j.data.fields.company;
    }
    alert('Datos del CV cargados.');
  });
    csubmit && csubmit.addEventListener('click', ()=>{
    const params = new URLSearchParams();
    params.set('action','kvt_create_candidate');
    params.set('_ajax_nonce', KVT_NONCE);
    params.set('first_name', cfirst.value||'');
    params.set('last_name',  clast.value||'');
    params.set('email',      cemail.value||'');
    params.set('phone',      cphone && cphone.value ? cphone.value : '');
    params.set('country',    ccountry && ccountry.value ? ccountry.value : '');
    params.set('city',       ccity && ccity.value ? ccity.value : '');
    params.set('current_role', crole && crole.value ? crole.value : '');
    params.set('company',    ccompany && ccompany.value ? ccompany.value : '');
    params.set('tags',       ctags && ctags.value ? ctags.value : '');
    params.set('cv_url',     ccvurl && ccvurl.value ? ccvurl.value : '');
    params.set('client_id',  ccli.value||'');
    params.set('process_id', cproc.value||'');
    fetch(KVT_AJAX,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:params.toString()})
      .then(r=>r.json()).then(async j=>{
        if(!j.success) return alert(j.data && j.data.msg ? j.data.msg : 'No se pudo crear el candidato.');
        const newId = j.data.id;
        if (ccvfile && ccvfile.files && ccvfile.files[0]) {
          const fd = new FormData();
          fd.append('action','kvt_upload_cv');
          fd.append('_ajax_nonce', KVT_NONCE);
          fd.append('id', newId);
          fd.append('file', ccvfile.files[0]);
          const upRes = await fetch(KVT_AJAX,{method:'POST',body:fd});
          const upJ = await upRes.json();
          if(!upJ.success) alert(upJ.data && upJ.data.msg ? upJ.data.msg : 'No se pudo subir el CV.');
        }
        alert('Candidato creado (#'+newId+').');
        closeCModal(); refresh();
      });
    });

    // Create client modal
    const clmodal = el('#kvt_new_client_modal');
    const clclose = el('#kvt_new_client_close');
    const clname  = el('#kvt_client_name');
    const clcont  = el('#kvt_client_contact');
    const clemail = el('#kvt_client_email');
    const clphone = el('#kvt_client_phone');
    const clsector = el('#kvt_client_sector');
    const cldesc  = el('#kvt_client_desc');
    const clsigtxt = el('#kvt_client_sig_text');
    const clsigfile= el('#kvt_client_sig_file');
    const clsigparse = el('#kvt_client_sig_parse');
    const clsubmit= el('#kvt_client_submit');
    const clmeet  = el('#kvt_client_meetings_modal');
    const clmeetList = el('#kvt_client_meetings_list');
    const claddmeet = el('#kvt_client_add_meeting');
    const clsaveMeet = el('#kvt_client_save_meetings');
    const clTabs = clmodal ? els('.kvt-tab', clmodal) : [];
    const clPanels = clmodal ? els('.kvt-tab-panel', clmodal) : [];
    const mtmodal = el('#kvt_client_meeting_modal');
    const mtclose = el('#kvt_client_meeting_close');
    const mtperson = el('#kvt_meeting_person');
    const mtdate = el('#kvt_meeting_date');
    const mtdetails = el('#kvt_meeting_details');
    const mtsave = el('#kvt_meeting_save');
    function activateClTab(target){ clTabs.forEach(b=>b.classList.toggle('active', b.dataset.target===target)); clPanels.forEach(p=>p.classList.toggle('active', p.id==='kvt_client_tab_'+target)); }
    function renderMeetingList(){
      if(!clmeetList) return;
      const lines = clmeet && clmeet.value ? clmeet.value.split('\n').filter(Boolean) : [];
      clmeetList.innerHTML='';
      lines.forEach((line,idx)=>{
        const parts = line.split(' | ');
        const date = parts[0]||'';
        const person = parts[1]||'';
        const details = parts.length>3 ? parts.slice(2,-1).join(' | ') : parts.slice(2).join(' | ');
        const author = parts.length>3 ? parts[parts.length-1] : '';
        const li=document.createElement('li');
        li.dataset.idx=idx;
        li.innerHTML='<strong>'+esc(date)+'</strong> - '+esc(person)+': ';
        const span=document.createElement('span');
        const short = details.length>300 ? details.slice(0,300)+'…' : details;
        span.textContent=short;
        li.appendChild(span);
        if(details.length>300){
          const more=document.createElement('button'); more.textContent='Ver todo'; more.className='kvt-meeting-more';
          more.addEventListener('click', ()=>{ if(span.textContent===short){ span.textContent=details; more.textContent='Ver menos'; } else { span.textContent=short; more.textContent='Ver todo'; } });
          li.appendChild(more);
        }
        if(author){ const a=document.createElement('em'); a.textContent=' ('+author+')'; li.appendChild(a); }
        const edit=document.createElement('button'); edit.textContent='Editar'; edit.className='kvt-meeting-edit';
        const del=document.createElement('button'); del.textContent='Eliminar'; del.className='kvt-meeting-del';
        li.appendChild(edit); li.appendChild(del);
        clmeetList.appendChild(li);
      });
    }
    function openClModal(){ clmodal.dataset.edit=''; clname.value=''; clcont.value=''; clemail.value=''; clphone.value=''; if(clsector) clsector.value=''; cldesc.value=''; if(clmeet) clmeet.value=''; renderMeetingList(); if(clsigtxt) clsigtxt.value=''; if(clsigfile) clsigfile.value=''; clsubmit.textContent='Crear'; activateClTab('info'); clmodal.style.display='flex'; }
    function openEditClModal(c){ clmodal.dataset.edit=c.id; clname.value=c.name||''; clcont.value=c.contact_name||''; clemail.value=c.contact_email||''; clphone.value=c.contact_phone||''; if(clsector) clsector.value=c.sector||''; cldesc.value=c.description||''; if(clmeet) clmeet.value=c.meetings||''; renderMeetingList(); if(clsigtxt) clsigtxt.value=''; if(clsigfile) clsigfile.value=''; clsubmit.textContent='Guardar'; activateClTab('info'); clmodal.style.display='flex'; }
    function closeClModal(){ clmodal.style.display='none'; clmodal.dataset.edit=''; clsubmit.textContent='Crear'; if(cldesc) cldesc.value=''; if(clmeet) clmeet.value=''; if(clsector) clsector.value=''; renderMeetingList(); if(clsigtxt) clsigtxt.value=''; if(clsigfile) clsigfile.value=''; activateClTab('info'); }
    clclose && clclose.addEventListener('click', closeClModal);
    clmodal && clmodal.addEventListener('click', e=>{ if(e.target===clmodal) closeClModal(); });
    clTabs.forEach(btn=>btn.addEventListener('click', ()=>activateClTab(btn.dataset.target)));
    const btnAddClient = el('#kvt_add_client_btn');
    btnAddClient && btnAddClient.addEventListener('click', openClModal);
    claddmeet && claddmeet.addEventListener('click', ()=>{ if(mtperson) mtperson.value=''; if(mtdate) mtdate.value=new Date().toISOString().slice(0,10); if(mtdetails) mtdetails.value=''; mtmodal.dataset.edit=''; mtmodal.style.display='flex'; });
    clsaveMeet && clsaveMeet.addEventListener('click', ()=>{ clsubmit && clsubmit.click(); });
    function closeMeetingModal(){ mtmodal.style.display='none'; mtmodal.dataset.edit=''; }
    mtclose && mtclose.addEventListener('click', closeMeetingModal);
    mtmodal && mtmodal.addEventListener('click', e=>{ if(e.target===mtmodal) closeMeetingModal(); });
    mtsave && mtsave.addEventListener('click', ()=>{ const person = mtperson.value.trim(); const date = mtdate.value || new Date().toISOString().slice(0,10); const details = mtdetails.value.trim(); if(person && details){ const line = [date, person, details.replace(/\n/g,' '), KVT_CURRENT_USER || ''].join(' | '); const lines = clmeet && clmeet.value ? clmeet.value.split('\n').filter(Boolean) : []; const idx = mtmodal.dataset.edit; if(idx!==undefined && idx!==''){ lines[idx] = line; } else { lines.push(line); } clmeet.value = lines.join('\n'); renderMeetingList(); closeMeetingModal(); } else { alert('Complete persona y detalles.'); } });
    clmeetList && clmeetList.addEventListener('click', e=>{ const li=e.target.closest('li'); if(!li) return; const idx = li.dataset.idx; const lines = clmeet && clmeet.value ? clmeet.value.split('\n').filter(Boolean) : []; if(e.target.classList.contains('kvt-meeting-del')){ lines.splice(idx,1); clmeet.value = lines.join('\n'); renderMeetingList(); } else if(e.target.classList.contains('kvt-meeting-edit')){ const parts = lines[idx].split(' | '); if(mtperson) mtperson.value = parts[1]||''; if(mtdate) mtdate.value = parts[0]||new Date().toISOString().slice(0,10); if(mtdetails) mtdetails.value = parts.length>3 ? parts.slice(2,-1).join(' | ') : parts.slice(2).join(' | '); mtmodal.dataset.edit=idx; mtmodal.style.display='flex'; } });
    clsigparse && clsigparse.addEventListener('click', ()=>{
      const fd = new FormData();
      fd.append('action','kvt_parse_signature');
      fd.append('_ajax_nonce', KVT_NONCE);
      if(clsigtxt && clsigtxt.value) fd.append('signature_text', clsigtxt.value);
      if(clsigfile && clsigfile.files[0]) fd.append('signature_image', clsigfile.files[0]);
      fetch(KVT_AJAX,{method:'POST',body:fd}).then(r=>r.json()).then(j=>{
        if(!j.success) return alert(j.data && j.data.msg ? j.data.msg : 'No se pudo extraer.');
        if(j.data.company) clname.value=j.data.company;
        if(j.data.contact) clcont.value=j.data.contact;
        if(j.data.email)   clemail.value=j.data.email;
        if(j.data.phone)   clphone.value=j.data.phone;
        if(j.data.description) cldesc.value=j.data.description;
      });
    });
    clsubmit && clsubmit.addEventListener('click', ()=>{
      const params = new URLSearchParams();
      const editing = clmodal.dataset.edit;
      params.set('action', editing ? 'kvt_update_client' : 'kvt_create_client');
      params.set('_ajax_nonce', KVT_NONCE);
      if(editing) params.set('id', editing);
      params.set('name', clname.value||'');
      params.set('contact_name', clcont.value||'');
      params.set('contact_email', clemail.value||'');
      params.set('contact_phone', clphone.value||'');
      params.set('sector', clsector.value||'');
      params.set('description', cldesc.value||'');
      params.set('meetings', clmeet && clmeet.value ? clmeet.value : '');
      fetch(KVT_AJAX,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:params.toString()})
        .then(r=>r.json()).then(j=>{
          if(!j.success) return alert(j.data && j.data.msg ? j.data.msg : 'No se pudo guardar.');
          if(editing){
            alert('Cliente actualizado (#'+editing+').');
            const cid=parseInt(editing,10); const obj=getClientById(cid); if(obj){ obj.name=clname.value||''; obj.contact_name=clcont.value||''; obj.contact_email=clemail.value||''; obj.contact_phone=clphone.value||''; obj.sector=clsector.value||''; obj.description=cldesc.value||''; obj.meetings=clmeet && clmeet.value?clmeet.value:''; }
            const opt = selClient ? selClient.querySelector('option[value="'+cid+'"]') : null; if(opt) opt.textContent = clname.value||'';
            closeClModal(); listClients(); updateSelectedInfo();
          } else {
            alert('Cliente creado (#'+j.data.id+').');
            closeClModal(); location.reload();
          }
        });
    });

    // Create process modal
    const pmodal = el('#kvt_new_process_modal');
    const pclose = el('#kvt_new_process_close');
    const pname  = el('#kvt_process_name_new');
    const pcli   = el('#kvt_process_client_new');
    const pcontact = el('#kvt_process_contact_new');
    const pemail   = el('#kvt_process_email_new');
    const pdesc    = el('#kvt_process_desc_new');
    const psubmit  = el('#kvt_process_submit');
    const pTabs = pmodal ? els('.kvt-tab', pmodal) : [];
    const pPanels = pmodal ? els('.kvt-tab-panel', pmodal) : [];
    const prmeet  = el('#kvt_process_meetings_modal');
    const prmeetList = el('#kvt_process_meetings_list');
    const praddmeet = el('#kvt_process_add_meeting');
    const prsaveMeet = el('#kvt_process_save_meetings');
    const prmtmodal = el('#kvt_process_meeting_modal');
    const prmtclose = el('#kvt_process_meeting_close');
    const prmtperson = el('#kvt_proc_meeting_person');
    const prmtdate   = el('#kvt_proc_meeting_date');
    const prmtdetails= el('#kvt_proc_meeting_details');
    const prmtsave   = el('#kvt_proc_meeting_save');
    function activatePTab(target){ pTabs.forEach(b=>b.classList.toggle('active', b.dataset.target===target)); pPanels.forEach(p=>p.classList.toggle('active', p.id==='kvt_process_tab_'+target)); }
    function renderProcessMeetingList(){
      if(!prmeetList) return;
      const lines = prmeet && prmeet.value ? prmeet.value.split('\n').filter(Boolean) : [];
      prmeetList.innerHTML='';
      lines.forEach((line,idx)=>{
        const parts = line.split(' | ');
        const date = parts[0]||'';
        const person = parts[1]||'';
        const details = parts.length>3 ? parts.slice(2,-1).join(' | ') : parts.slice(2).join(' | ');
        const author = parts.length>3 ? parts[parts.length-1] : '';
        const li=document.createElement('li');
        li.dataset.idx=idx;
        li.innerHTML='<strong>'+esc(date)+'</strong> - '+esc(person)+': ';
        const span=document.createElement('span');
        const short = details.length>300 ? details.slice(0,300)+'…' : details;
        span.textContent=short;
        li.appendChild(span);
        if(details.length>300){
          const more=document.createElement('button'); more.textContent='Ver todo'; more.className='kvt-meeting-more';
          more.addEventListener('click', ()=>{ if(span.textContent===short){ span.textContent=details; more.textContent='Ver menos'; } else { span.textContent=short; more.textContent='Ver todo'; } });
          li.appendChild(more);
        }
        if(author){ const a=document.createElement('em'); a.textContent=' ('+author+')'; li.appendChild(a); }
        const edit=document.createElement('button'); edit.textContent='Editar'; edit.className='kvt-meeting-edit';
        const del=document.createElement('button'); del.textContent='Eliminar'; del.className='kvt-meeting-del';
        li.appendChild(edit); li.appendChild(del);
        prmeetList.appendChild(li);
      });
    }
    function openPModal(){
      pmodal.dataset.edit='';
      pname.value='';
      pcli.value='';
      if(pcontact) pcontact.value='';
      if(pemail) pemail.value='';
      if(pdesc) pdesc.value='';
      if(prmeet) prmeet.value='';
      renderProcessMeetingList();
      psubmit.textContent='Crear';
      activatePTab('info');
      pmodal.style.display='flex';
    }
    function openEditPModal(p){
      pmodal.dataset.edit=p.id;
      pname.value=p.name||'';
      pcli.value=p.client_id?String(p.client_id):'';
      if(pcontact) pcontact.value=p.contact_name||'';
      if(pemail) pemail.value=p.contact_email||'';
      if(pdesc) pdesc.value=p.description||'';
      if(prmeet) prmeet.value=p.meetings||'';
      renderProcessMeetingList();
      psubmit.textContent='Guardar';
      activatePTab('info');
      pmodal.style.display='flex';
    }
    function closePModal(){ pmodal.style.display='none'; pmodal.dataset.edit=''; psubmit.textContent='Crear'; if(prmeet) prmeet.value=''; renderProcessMeetingList(); activatePTab('info'); }
    pclose && pclose.addEventListener('click', closePModal);
    pmodal && pmodal.addEventListener('click', e=>{ if(e.target===pmodal) closePModal(); });
    pTabs.forEach(btn=>btn.addEventListener('click', ()=>activatePTab(btn.dataset.target)));
    const btnAddProcess = el('#kvt_add_process_btn');
    btnAddProcess && btnAddProcess.addEventListener('click', openPModal);
    praddmeet && praddmeet.addEventListener('click', ()=>{ if(prmtperson) prmtperson.value=''; if(prmtdate) prmtdate.value=new Date().toISOString().slice(0,10); if(prmtdetails) prmtdetails.value=''; prmtmodal.dataset.edit=''; prmtmodal.style.display='flex'; });
    prsaveMeet && prsaveMeet.addEventListener('click', ()=>{ psubmit && psubmit.click(); });
    function closeProcessMeetingModal(){ prmtmodal.style.display='none'; prmtmodal.dataset.edit=''; }
    prmtclose && prmtclose.addEventListener('click', closeProcessMeetingModal);
    prmtmodal && prmtmodal.addEventListener('click', e=>{ if(e.target===prmtmodal) closeProcessMeetingModal(); });
    prmtsave && prmtsave.addEventListener('click', ()=>{ const person = prmtperson.value.trim(); const date = prmtdate.value || new Date().toISOString().slice(0,10); const details = prmtdetails.value.trim(); if(person && details){ const line = [date, person, details.replace(/\n/g,' '), KVT_CURRENT_USER || ''].join(' | '); const lines = prmeet && prmeet.value ? prmeet.value.split('\n').filter(Boolean) : []; const idx = prmtmodal.dataset.edit; if(idx!==undefined && idx!==''){ lines[idx] = line; } else { lines.push(line); } prmeet.value = lines.join('\n'); renderProcessMeetingList(); closeProcessMeetingModal(); } else { alert('Complete persona y detalles.'); } });
    prmeetList && prmeetList.addEventListener('click', e=>{ const li=e.target.closest('li'); if(!li) return; const idx = li.dataset.idx; const lines = prmeet && prmeet.value ? prmeet.value.split('\n').filter(Boolean) : []; if(e.target.classList.contains('kvt-meeting-del')){ lines.splice(idx,1); prmeet.value = lines.join('\n'); renderProcessMeetingList(); } else if(e.target.classList.contains('kvt-meeting-edit')){ const parts = lines[idx].split(' | '); if(prmtperson) prmtperson.value = parts[1]||''; if(prmtdate) prmtdate.value = parts[0]||new Date().toISOString().slice(0,10); if(prmtdetails) prmtdetails.value = parts.length>3 ? parts.slice(2,-1).join(' | ') : parts.slice(2).join(' | '); prmtmodal.dataset.edit=idx; prmtmodal.style.display='flex'; } });
    psubmit && psubmit.addEventListener('click', ()=>{
      const params = new URLSearchParams();
      const editing = pmodal.dataset.edit;
      params.set('action', editing ? 'kvt_update_process' : 'kvt_create_process');
      params.set('_ajax_nonce', KVT_NONCE);
      if(editing) params.set('id', editing);
      params.set('name', pname.value||'');
      params.set('client_id', pcli.value||'');
      if(pcontact) params.set('contact_name', pcontact.value||'');
      if(pemail) params.set('contact_email', pemail.value||'');
      if(pdesc) params.set('description', pdesc.value||'');
      if(prmeet) params.set('meetings', prmeet.value||'');
      fetch(KVT_AJAX,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:params.toString()})
        .then(r=>r.json()).then(j=>{
          if(!j.success) return alert(j.data && j.data.msg ? j.data.msg : 'No se pudo guardar.');
          if(editing){
            alert('Proceso actualizado (#'+editing+').');
            const pid=parseInt(editing,10); const obj=getProcessById(pid); if(obj){ obj.name=pname.value||''; obj.client_id=parseInt(pcli.value||'0',10); obj.contact_name=pcontact?pcontact.value:''; obj.contact_email=pemail?pemail.value:''; obj.description=pdesc?pdesc.value:''; obj.meetings=prmeet && prmeet.value?prmeet.value:''; }
            const opt = selProcess ? selProcess.querySelector('option[value="'+pid+'"]') : null; if(opt) opt.textContent = pname.value||'';
            closePModal(); listProcesses(); updateSelectedInfo();
          } else {
            alert('Proceso creado (#'+j.data.id+').');
            closePModal(); location.reload();
          }
        });
    });

    const handleClientClick = e=>{
      const row = e.target.closest('.kvt-client-row');
      if(!row) return;
      let data = getClientById ? getClientById(row.dataset.id) : null;
      if(!data){
        data = {
          id: parseInt(row.dataset.id,10),
          name: row.dataset.name || '',
          contact_name: row.dataset.contactName || '',
          contact_email: row.dataset.contactEmail || '',
          contact_phone: row.dataset.contactPhone || '',
          description: row.dataset.desc || '',
          sector: row.dataset.sector || '',
          meetings: row.dataset.meetings || ''
        };
      }
      openEditClModal(data);
    };
    const handleProcessClick = e=>{
      const btn = e.target.closest('.kvt-edit-process');
      if(!btn) return;
      let data = getProcessById(btn.dataset.id);
      if(!data){
        data = {
          id: parseInt(btn.dataset.id,10),
          name: btn.dataset.name || '',
          client_id: btn.dataset.clientId?parseInt(btn.dataset.clientId,10):0,
          contact_name: btn.dataset.contactName || '',
          contact_email: btn.dataset.contactEmail || '',
          description: btn.dataset.desc || '',
          meetings: btn.dataset.meetings || ''
        };
      }
      openEditPModal(data);
    };
    const handleProcessSelect = e=>{
      const row = e.target.closest('.kvt-process-row');
      if(!row || e.target.closest('.kvt-edit-process') || e.target.classList.contains('kvt-process-status')) return;
      const pid = row.getAttribute('data-id');
      const cid = row.getAttribute('data-client') || '';
      if(selClient) selClient.value = cid;
      if(selProcess) selProcess.value = pid;
      showView('ats');
      refresh();
      updateSelectedInfo();
    };
    processesList && processesList.addEventListener('click', handleProcessClick);
    boardProcessesList && boardProcessesList.addEventListener('click', handleProcessClick);
    processesList && processesList.addEventListener('click', handleProcessSelect);
    boardProcessesList && boardProcessesList.addEventListener('click', handleProcessSelect);
    const handleInlineEdit = e=>{
      if(e.target.classList.contains('kvt-edit-client-inline')){
        const d=getClientById(e.target.dataset.id);
        if(d) openEditClModal(d);
      }
      if(e.target.classList.contains('kvt-edit-process-inline')){
        const d=getProcessById(e.target.dataset.id);
        if(d) openEditPModal(d);
      }
    };
    selInfo && selInfo.addEventListener('click', handleInlineEdit);
    boardProcInfo && boardProcInfo.addEventListener('click', handleInlineEdit);
    infoBody && infoBody.addEventListener('click', handleInlineEdit);

    emailGenerate && emailGenerate.addEventListener('click', async ()=>{
      const prompt=(emailPrompt.value||'').trim();
      if(!prompt){ alert('Escribe un prompt.'); return; }
      const res = await fetch(KVT_AJAX,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({action:'kvt_generate_email', _ajax_nonce:KVT_NONCE, prompt})});
      let json; try{ json = await res.json(); }catch(e){ alert('Error de servidor'); return; }
      if(json.success){ emailSubject.value=json.data.subject_template||''; emailBody.value=json.data.body_template||''; } else { alert('No se pudo generar'); }
    });

    emailPreviewBtn && emailPreviewBtn.addEventListener('click', ()=>{
      const subject=(emailSubject.value||'').trim();
      const body=(emailBody.value||'').trim();
      if(!subject || !body){ alert('Completa asunto y cuerpo.'); return; }
      const firstId = emailSelected.size ? Array.from(emailSelected)[0] : null;
      if(!firstId){ alert('Selecciona al menos un candidato.'); return; }
      const cand = emailCandidates.find(c=>String(c.id)===String(firstId));
      if(!cand){ alert('Candidato inválido'); return; }
      const m=cand.meta||{};
      const meta=Object.assign({}, m, {surname:m.last_name||'', role:m.process||'', board:m.board||'', sender:(emailFromName.value||KVT_FROM_NAME||'')});
      const repl=str=>str.replace(/{{(\w+)}}/g,(match,p)=>meta[p]||'');
      emailPrevSubject.textContent=repl(subject);
      let bodyHtml=repl(body).replace(/\n/g,'<br>');
      if(emailUseSig && emailUseSig.checked && KVT_SIGNATURE){
        bodyHtml+='<br><br>'+KVT_SIGNATURE.replace(/\n/g,'<br>');
      }
      emailPrevBody.innerHTML=bodyHtml;
      if(emailPrevModal) emailPrevModal.style.display='flex';
    });
    emailPrevClose && emailPrevClose.addEventListener('click', ()=>{ if(emailPrevModal) emailPrevModal.style.display='none'; });
    emailPrevModal && emailPrevModal.addEventListener('click', e=>{ if(e.target===emailPrevModal) emailPrevModal.style.display='none'; });

    emailSend && emailSend.addEventListener('click', async ()=>{
      const subject=(emailSubject.value||'').trim();
      const body=(emailBody.value||'').trim();
      if(!subject || !body){ alert('Completa asunto y cuerpo.'); return; }
      const recipients=Array.from(emailSelected).map(id=>{
        const it=emailCandidates.find(c=>String(c.id)===String(id));
        const m=it?it.meta:{};
        return {email:m.email||'', first_name:m.first_name||'', surname:m.last_name||'', country:m.country||'', city:m.city||'', role:m.process||'', status:m.status||'', client:m.client||'', board:m.board||''};
      }).filter(r=>r.email);
      if(!recipients.length){ alert('No hay candidatos seleccionados con email.'); return; }
      if(!confirm(`¿Enviar a ${recipients.length} contactos?`)) return;
      const payload={recipients, subject_template:subject, body_template:body, from_email:(emailFromEmail.value||'').trim(), from_name:(emailFromName.value||'').trim(), use_signature: emailUseSig && emailUseSig.checked ? 1 : 0};
      try{
        const out = await ajaxForm({action:'kvt_send_email', _ajax_nonce:KVT_NONCE, payload: JSON.stringify(payload)});
        emailStatusMsg.textContent = out && out.success ? `Enviados: ${out.data.sent}` : 'Enviados';
        if(out && out.success && out.data && out.data.log){
          KVT_SENT_EMAILS = out.data.log;
          renderSentEmails();
        }
      } catch(err){
        emailStatusMsg.textContent = 'Enviados';
      }
    });

    // Easier drag & drop: allow drop anywhere in column and highlight
  els('.kvt-col').forEach(col=>{
    col.addEventListener('dragover', e=>{ e.preventDefault(); col.classList.add('dragover'); });
    col.addEventListener('dragleave', ()=>{ col.classList.remove('dragover'); });
    col.addEventListener('drop', e=>{
      e.preventDefault(); col.classList.remove('dragover');
      const id = e.dataTransfer.getData('text/plain');
      const zone = col.querySelector('.kvt-dropzone');
      const newStatus = zone ? zone.dataset.status : col.dataset.status;
      const card = el('.kvt-card[data-id=\"'+id+'\"]');
      if (zone && card) zone.appendChild(card);
      ajaxForm({action:'kvt_update_status', _ajax_nonce:KVT_NONCE, id:id, status:newStatus});
    });
  });

  // Make dropzones taller
  els('.kvt-dropzone').forEach(zone=> zone.style.minHeight = '200px');

  // Safer process filter if map missing
  function safeHasProcessMap(){ return Array.isArray(window.KVT_PROCESS_MAP) && window.KVT_PROCESS_MAP.length>0; }
  const __origFilterProcessOptions = filterProcessOptions;
  filterProcessOptions = function(){
    if (!safeHasProcessMap()) return; // keep server-rendered options
    __origFilterProcessOptions();
  };

  // Init
  filterProcessOptions();
  refresh();
  updateSelectedInfo();
  if (typeof KVT_EDIT_BOARD !== 'undefined' && KVT_EDIT_BOARD) {
    const cfg = (KVT_BOARD_LINKS.client && KVT_BOARD_LINKS.client[KVT_EDIT_BOARD]) ? KVT_BOARD_LINKS.client[KVT_EDIT_BOARD] : (KVT_BOARD_LINKS.candidate && KVT_BOARD_LINKS.candidate[KVT_EDIT_BOARD] ? KVT_BOARD_LINKS.candidate[KVT_EDIT_BOARD] : null);
    if (cfg) {
      if (selClient) selClient.value = cfg.client || '';
      if (selProcess) selProcess.value = cfg.process || '';
      ALLOWED_FIELDS = Array.isArray(cfg.fields) ? cfg.fields : [];
      ALLOWED_STEPS = Array.isArray(cfg.steps) ? cfg.steps : [];
      ALLOW_COMMENTS = !!cfg.comments;
      if (cfg.candidate) { shareMode = 'candidate'; selectedCandidateId = parseInt(cfg.candidate,10) || 0; }
      filterProcessOptions();
      refresh();
      updateSelectedInfo();
      buildShareOptions();
      if (shareModal) shareModal.style.display = 'flex';
    }
  }
}
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', kvtInit);
} else {
  kvtInit();
}
JS;
            wp_add_inline_script('kvt-app', $js, 'after');
        }
    }

    /* Data API */
    public function ajax_get_candidates() {
        check_ajax_referer('kvt_nonce');

        $client_ids  = isset($_POST['client'])  ? array_filter(array_map('intval', explode(',', sanitize_text_field($_POST['client'])))) : [];
        $process_ids = isset($_POST['process']) ? array_filter(array_map('intval', explode(',', sanitize_text_field($_POST['process'])))) : [];
        $search      = isset($_POST['search'])  ? trim(sanitize_text_field($_POST['search'])) : '';
        $page        = isset($_POST['page'])    ? max(1, intval($_POST['page'])) : 1;
        $all         = isset($_POST['all'])     ? intval($_POST['all']) : 0;
        $status_vals = isset($_POST['status'])  ? array_filter(array_map('sanitize_text_field', explode(',', $_POST['status']))) : [];
        $countries   = isset($_POST['country']) ? array_filter(array_map('sanitize_text_field', explode(',', $_POST['country']))) : [];
        $cities      = isset($_POST['city'])    ? array_filter(array_map('sanitize_text_field', explode(',', $_POST['city']))) : [];

        $cand_links_opt = get_option('kvt_candidate_links', []);
        $board_map = [];
        if (is_array($cand_links_opt)) {
            foreach ($cand_links_opt as $slug => $cfg) {
                $cid = isset($cfg['candidate']) ? (int) $cfg['candidate'] : 0;
                if ($cid) $board_map[$cid] = home_url('/view-board/' . $slug . '/');
            }
        }

        $base_mode = empty($client_ids) && empty($process_ids);

        $tax_query = [];
        if (!$base_mode) {
            if (!empty($process_ids)) {
                $tax_query[] = ['taxonomy'=>self::TAX_PROCESS,'field'=>'term_id','terms'=>$process_ids];
                if (!empty($client_ids)) $tax_query[] = ['taxonomy'=>self::TAX_CLIENT,'field'=>'term_id','terms'=>$client_ids];
            } else {
                if (!empty($client_ids)) {
                    $proc_terms = get_terms(['taxonomy'=>self::TAX_PROCESS,'hide_empty'=>false]);
                    $proc_ids = [];
                    foreach ($proc_terms as $t) {
                        $cid = (int) get_term_meta($t->term_id, 'kvt_process_client', true);
                        if (in_array($cid, $client_ids, true)) $proc_ids[] = $t->term_id;
                    }
                    if (!empty($proc_ids)) {
                        $tax_query = [
                            'relation' => 'OR',
                            ['taxonomy'=>self::TAX_CLIENT, 'field'=>'term_id','terms'=>$client_ids],
                            ['taxonomy'=>self::TAX_PROCESS,'field'=>'term_id','terms'=>$proc_ids],
                        ];
                    } else {
                        $tax_query[] = ['taxonomy'=>self::TAX_CLIENT,'field'=>'term_id','terms'=>$client_ids];
                    }
                }
            }
        }

        $per_page = isset($_POST['per_page']) ? intval($_POST['per_page']) : (($base_mode && !$all) ? 10 : 999);
        if ($per_page <= 0) $per_page = 10;
        $args = [
            'post_type'      => self::CPT,
            'post_status'    => 'any',
            'posts_per_page' => $per_page,
            'paged'          => $page,
            'no_found_rows'  => $per_page >= 999,
        ];
        if (!empty($tax_query)) $args['tax_query'] = $tax_query;

        $meta_query = [];
        if ($search !== '') {
            $meta_query[] = [
                'relation' => 'OR',
                ['key'=>'kvt_first_name','value'=>$search,'compare'=>'LIKE'],
                ['key'=>'kvt_last_name', 'value'=>$search,'compare'=>'LIKE'],
                ['key'=>'kvt_email',     'value'=>$search,'compare'=>'LIKE'],
                ['key'=>'kvt_current_role','value'=>$search,'compare'=>'LIKE'],
                ['key'=>'first_name','value'=>$search,'compare'=>'LIKE'],
                ['key'=>'last_name', 'value'=>$search,'compare'=>'LIKE'],
                ['key'=>'email',     'value'=>$search,'compare'=>'LIKE'],
                ['key'=>'current_role','value'=>$search,'compare'=>'LIKE'],
            ];
        }
        if (!empty($status_vals)) {
            $meta_query[] = ['key'=>'kvt_status','value'=>$status_vals,'compare'=>'IN'];
        }
        if (!empty($countries)) {
            $meta_query[] = [
                'relation'=>'OR',
                ['key'=>'kvt_country','value'=>$countries,'compare'=>'IN'],
                ['key'=>'country','value'=>$countries,'compare'=>'IN'],
            ];
        }
        if (!empty($cities)) {
            $meta_query[] = [
                'relation'=>'OR',
                ['key'=>'kvt_city','value'=>$cities,'compare'=>'IN'],
                ['key'=>'city','value'=>$cities,'compare'=>'IN'],
            ];
        }
        if (!empty($meta_query)) {
            $args['meta_query'] = array_merge(['relation'=>'AND'], $meta_query);
        }

        $q = new WP_Query($args);
        $data = [];
        foreach ($q->posts as $p) {
            $notes_raw = get_post_meta($p->ID,'kvt_notes',true);
            if ($notes_raw === '') $notes_raw = get_post_meta($p->ID,'notes',true);
            $client_name  = $this->get_term_name($p->ID, self::TAX_CLIENT);
            $process_name = $this->get_term_name($p->ID, self::TAX_PROCESS);
            $meta = [
                'candidate'   => get_the_title($p),
                'status'      => get_post_meta($p->ID,'kvt_status',true),
                'client'      => $client_name,
                'process'     => $process_name,
                'int_no'      => $this->meta_get_compat($p->ID,'kvt_int_no',['int_no']),
                'first_name'  => $this->meta_get_compat($p->ID,'kvt_first_name',['first_name']),
                'last_name'   => $this->meta_get_compat($p->ID,'kvt_last_name',['last_name']),
                'email'       => $this->meta_get_compat($p->ID,'kvt_email',['email']),
                'phone'       => $this->meta_get_compat($p->ID,'kvt_phone',['phone']),
                'country'     => $this->meta_get_compat($p->ID,'kvt_country',['country']),
                'city'        => $this->meta_get_compat($p->ID,'kvt_city',['city']),
                'current_role'=> $this->meta_get_compat($p->ID,'kvt_current_role',['current_role']),
                'cv_url'      => $this->meta_get_compat($p->ID,'kvt_cv_url',['cv_url']),
                'cv_uploaded' => $this->fmt_date_ddmmyyyy($this->meta_get_compat($p->ID,'kvt_cv_uploaded',['cv_uploaded'])),
                'next_action' => $this->fmt_date_ddmmyyyy($this->meta_get_compat($p->ID,'kvt_next_action',['next_action'])),
                'next_action_note' => $this->meta_get_compat($p->ID,'kvt_next_action_note',['next_action_note']),
                'notes'       => $notes_raw,
                'notes_count' => $this->count_notes($notes_raw),
                'tags'        => $this->meta_get_compat($p->ID,'kvt_tags',['tags']),
                'client_comments' => (function($p_id){
                    $ccs = get_post_meta($p_id,'kvt_client_comments',true);
                    if (is_array($ccs)) {
                        foreach ($ccs as &$cc) {
                            if (!isset($cc['source'])) $cc['source'] = 'client';
                        }
                    }
                    return $ccs;
                })($p->ID),
                'activity_log' => get_post_meta($p->ID,'kvt_activity_log',true),
                'board'       => isset($board_map[$p->ID]) ? $board_map[$p->ID] : '',
            ];
            $data[] = [
                'id'     => $p->ID,
                'title'  => get_the_title($p),
                'status' => get_post_meta($p->ID,'kvt_status',true),
                'meta'   => $meta,
            ];
        }
        $pages = $per_page >= 999 ? 1 : $q->max_num_pages;
        wp_send_json_success(['items'=>$data,'pages'=>$pages]);
    }

    public function ajax_get_dashboard() {
        check_ajax_referer('kvt_nonce');

        $posts = get_posts([
            'post_type'   => self::CPT,
            'post_status' => 'any',
            'numberposts' => -1,
        ]);

        $comments = [];
        $upcoming = [];
        $overdue  = [];
        $logs     = [];
        $today    = strtotime('today');
        $nextWeek = strtotime('+7 days', $today);

        foreach ($posts as $p) {
            $client  = $this->get_term_name($p->ID, self::TAX_CLIENT);
            $process = $this->get_term_name($p->ID, self::TAX_PROCESS);
            $title   = get_the_title($p);

            $ccs = get_post_meta($p->ID, 'kvt_client_comments', true);
            if (is_array($ccs)) {
                foreach ($ccs as $idx => $cc) {
                    if (!empty($cc['comment']) && empty($cc['dismissed'])) {
                        $comments[] = [
                            'candidate_id' => $p->ID,
                            'candidate'    => $title,
                            'client'       => $client,
                            'process'      => $process,
                            'name'         => isset($cc['name']) ? $cc['name'] : '',
                            'date'         => isset($cc['date']) ? $this->fmt_date_ddmmyyyy($cc['date']) : '',
                            'comment'      => $cc['comment'],
                            'index'        => $idx,
                            'source'       => 'client',
                        ];
                    }
                }
            }

            $na = get_post_meta($p->ID, 'kvt_next_action', true);
            if ($na) {
                $ts = strtotime(str_replace('/', '-', $na));
                if ($ts) {
                    $item = [
                        'candidate_id' => $p->ID,
                        'candidate'    => $title,
                        'client'       => $client,
                        'process'      => $process,
                        'date'         => $this->fmt_date_ddmmyyyy($na),
                        'time'         => get_post_meta($p->ID, 'kvt_next_action_time', true),
                        'note'         => get_post_meta($p->ID, 'kvt_next_action_note', true),
                    ];
                    if ($ts < $today) {
                        $overdue[] = $item;
                    } elseif ($ts <= $nextWeek) {
                        $upcoming[] = $item;
                    }
                }
            }

            $acts = get_post_meta($p->ID, 'kvt_activity_log', true);
            if (is_array($acts)) {
                foreach ($acts as $act) {
                    $msg = '';
                    switch ($act['type'] ?? '') {
                        case 'status':
                            $msg = 'Estado a ' . ($act['status'] ?? '');
                            if (!empty($act['comment'])) $msg .= ' — ' . $act['comment'];
                            break;
                        case 'task_add':
                            $msg = 'Tarea ' . ($act['date'] ?? '');
                            if (!empty($act['note'])) $msg .= ' — ' . $act['note'];
                            break;
                        case 'task_done':
                            $msg = 'Tarea completada ' . ($act['date'] ?? '');
                            if (!empty($act['comment'])) $msg .= ' — ' . $act['comment'];
                            break;
                        case 'task_deleted':
                            $msg = 'Tarea eliminada ' . ($act['date'] ?? '');
                            if (!empty($act['note'])) $msg .= ' — ' . $act['note'];
                            break;
                        default:
                            $msg = $act['type'] ?? '';
                    }
                    $author = isset($act['author']) ? $act['author'] : '';
                    $time   = isset($act['time']) ? $act['time'] : '';
                    $logs[] = [
                        'time' => $time,
                        'text' => $title . ' — ' . $msg . ($author ? ' (' . $author . ')' : ''),
                    ];
                }
            }
        }

        wp_send_json_success([
            'comments' => $comments,
            'upcoming' => $upcoming,
            'overdue'  => $overdue,
            'logs'     => $logs,
        ]);
    }

    public function ajax_get_outlook_events() {
        check_ajax_referer('kvt_nonce');

        $tenant = get_option(self::OPT_O365_TENANT, '');
        $client = get_option(self::OPT_O365_CLIENT, '');
        $user   = get_option(self::OPT_SMTP_USER, '');
        $pass   = get_option(self::OPT_SMTP_PASS, '');
        if (!$tenant || !$client || !$user || !$pass) {
            wp_send_json_success([]);
        }

        $token_resp = wp_remote_post("https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/token", [
            'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
            'body'    => [
                'client_id' => $client,
                'scope'     => 'https://graph.microsoft.com/.default',
                'grant_type'=> 'password',
                'username'  => $user,
                'password'  => $pass,
            ],
        ]);
        if (is_wp_error($token_resp)) {
            wp_send_json_error(['msg' => 'token'], 500);
        }
        $token_data = json_decode(wp_remote_retrieve_body($token_resp), true);
        if (empty($token_data['access_token'])) {
            wp_send_json_error(['msg' => 'token'], 500);
        }
        $access = $token_data['access_token'];

        $events_resp = wp_remote_get('https://graph.microsoft.com/v1.0/me/events?$select=subject,start,end&$top=50', [
            'headers' => ['Authorization' => 'Bearer ' . $access],
        ]);
        if (is_wp_error($events_resp)) {
            wp_send_json_error(['msg' => 'events'], 500);
        }
        $body = json_decode(wp_remote_retrieve_body($events_resp), true);
        $events = [];
        if (!empty($body['value'])) {
            foreach ($body['value'] as $ev) {
                $start = isset($ev['start']['dateTime']) ? strtotime($ev['start']['dateTime']) : 0;
                if ($start) {
                    $events[] = [
                        'date' => date('d/m/Y', $start),
                        'time' => date('H:i', $start),
                        'text' => $ev['subject'] ?? '',
                    ];
                }
            }
        }
        wp_send_json_success($events);
    }

    public function ajax_dismiss_comment() {
        check_ajax_referer('kvt_nonce');

        $id  = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $idx = isset($_POST['index']) ? intval($_POST['index']) : -1;
        if (!$id || $idx < 0) {
            wp_send_json_error(['msg' => 'invalid'], 400);
        }
        $comments = get_post_meta($id, 'kvt_client_comments', true);
        if (!is_array($comments) || !isset($comments[$idx])) {
            wp_send_json_error(['msg' => 'missing'], 404);
        }
        $comments[$idx]['dismissed'] = 1;
        update_post_meta($id, 'kvt_client_comments', $comments);
        wp_send_json_success(['ok' => true]);
    }

    public function ajax_generate_roles() {
        check_ajax_referer('kvt_nonce');
        if (!current_user_can('edit_posts')) wp_send_json_error(['msg' => 'Unauthorized'], 403);
        $key = get_option(self::OPT_OPENAI_KEY, '');
        if (!$key) wp_send_json_error(['msg' => 'Falta la clave'], 400);
        $posts = get_posts([
            'post_type'   => self::CPT,
            'post_status' => 'any',
            'numberposts' => -1,
        ]);
        foreach ($posts as $p) {
            $this->update_profile_from_cv($p->ID, $key);
        }
        wp_send_json_success(['ok' => true]);
    }

    private function mit_load_history($uid) {
        $hist = get_user_meta($uid, 'kvt_mit_history', true);
        if (!is_array($hist)) {
            $hist = ['summary' => '', 'messages' => []];
        }
        return $hist;
    }

    private function mit_save_history($uid, $hist) {
        update_user_meta($uid, 'kvt_mit_history', $hist);
    }

    private function mit_summarize_history(&$hist, $key, $model) {
        if (count($hist['messages']) <= self::MIT_HISTORY_LIMIT) return;
        $excess = array_splice($hist['messages'], 0, count($hist['messages']) - self::MIT_HISTORY_LIMIT);
        $lines = [];
        foreach ($excess as $m) {
            $lines[] = strtoupper($m['role']) . ': ' . $m['content'];
        }
        $prompt = "Resume brevemente la siguiente conversación:\n" . implode("\n", $lines);
        $resp = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $key,
                'Content-Type'  => 'application/json',
            ],
            'body'    => json_encode([
                'model'   => $model,
                'messages'=> [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]),
            'timeout' => self::MIT_TIMEOUT,
        ]);
        if (!is_wp_error($resp)) {
            $data = json_decode(wp_remote_retrieve_body($resp), true);
            $sum  = trim($data['choices'][0]['message']['content'] ?? '');
            if ($sum) {
                $hist['summary'] = trim($hist['summary'] . ' ' . $sum);
            }
        }
    }

    private function mit_gather_context() {
        $cands = get_posts([
            'post_type'   => self::CPT,
            'post_status' => 'any',
            'numberposts' => -1,
        ]);
        $clients = get_terms([
            'taxonomy'   => self::TAX_CLIENT,
            'hide_empty' => false,
            'number'     => 0,
        ]);
        $processes = get_terms([
            'taxonomy'   => self::TAX_PROCESS,
            'hide_empty' => false,
            'number'     => 0,
        ]);
        $emails = array_reverse((array) get_option(self::OPT_EMAIL_LOG, []));

        $notes      = [];
        $cand_lines = [];
        $followups  = [];
        $history    = [];
        foreach ($cands as $c) {
            $country = get_post_meta($c->ID, 'kvt_country', true);
            $role    = $this->meta_get_compat($c->ID, 'kvt_current_role', ['current_role']);
            $status  = $this->meta_get_compat($c->ID, 'kvt_status', ['status']);
            $procs   = wp_get_object_terms($c->ID, self::TAX_PROCESS, ['fields' => 'names']);
            $log     = get_post_meta($c->ID, 'kvt_activity_log', true);
            $days    = '';
            if (is_array($log)) {
                foreach (array_reverse($log) as $entry) {
                    if (($entry['type'] ?? '') === 'status' && !empty($entry['time'])) {
                        $ts = strtotime($entry['time']);
                        if ($ts) $days = floor((current_time('timestamp') - $ts) / DAY_IN_SECONDS);
                        break;
                    }
                }
            }
            $line    = $c->post_title;
            if ($role)   $line .= " ($role)";
            if ($status) {
                $line .= " [$status";
                if ($days !== '') $line .= ' ' . $days . 'd';
                $line .= ']';
            }
            if ($procs) $line .= ' {' . implode(', ', $procs) . '}';
            if ($country) $line .= " - $country";
            $cand_lines[] = $line;
            $n = get_post_meta($c->ID, 'kvt_notes', true);
            if ($n) $notes[] = $n;
            $pn = get_post_meta($c->ID, 'kvt_public_notes', true);
            if ($pn) $notes[] = $pn;
            $desc = trim(wp_strip_all_tags($c->post_content));
            if ($desc) $notes[] = $desc;
            $ccs = get_post_meta($c->ID, 'kvt_client_comments', true);
            if (is_array($ccs)) {
                foreach ($ccs as $cc) {
                    if (!empty($cc['comment']) && empty($cc['dismissed'])) {
                        $notes[] = $c->post_title . ' comentario cliente: ' . sanitize_text_field($cc['comment']);
                    }
                }
            }
            $next = get_post_meta($c->ID, 'kvt_next_action', true);
            if ($next) {
                $ts = strtotime(str_replace('/', '-', $next));
                if ($ts && $ts <= strtotime('+3 days')) {
                    $na_note = get_post_meta($c->ID, 'kvt_next_action_note', true);
                    $fline = $c->post_title . ' - ' . $next;
                    if ($na_note) $fline .= ': ' . $na_note;
                    $followups[] = $fline;
                }
            }
            if (is_array($log)) {
                foreach (array_slice($log, -5) as $entry) {
                    $parts = [];
                    $parts[] = sanitize_text_field($entry['type'] ?? '');
                    if (!empty($entry['status'])) $parts[] = sanitize_text_field($entry['status']);
                    if (!empty($entry['note'])) $parts[] = sanitize_text_field($entry['note']);
                    if (!empty($entry['comment'])) $parts[] = sanitize_text_field($entry['comment']);
                    if (!empty($entry['date'])) $parts[] = sanitize_text_field($entry['date']);
                    if (!empty($entry['time'])) $parts[] = sanitize_text_field($entry['time']);
                    $history[] = $c->post_title . ': ' . implode(' ', array_filter($parts));
                }
            }
        }

        $client_lines   = [];
        $meeting_lines  = [];
        foreach ($clients as $cl) {
            $contact = get_term_meta($cl->term_id, 'contact_name', true);
            $email   = get_term_meta($cl->term_id, 'contact_email', true);
            $phone   = get_term_meta($cl->term_id, 'contact_phone', true);
            $parts   = [];
            if ($contact) $parts[] = $contact;
            if ($email)   $parts[] = $email;
            if ($phone)   $parts[] = $phone;
            $line = $cl->name;
            if ($parts) $line .= ' (' . implode(' - ', $parts) . ')';
            $client_lines[] = $line;
            $desc = term_description($cl, self::TAX_CLIENT);
            if ($desc) $notes[] = $cl->name . ': ' . wp_strip_all_tags($desc);
            $meet = get_term_meta($cl->term_id, 'kvt_client_meetings', true);
            if ($meet) {
                $clean = preg_replace('/\r\n|\r|\n/', '; ', $meet);
                $meeting_lines[] = $cl->name . ': ' . $clean;
                $notes[] = $cl->name . ' reuniones: ' . $clean;
            }
        }

        $process_lines = [];
        foreach ($processes as $pr) {
            $cid  = get_term_meta($pr->term_id, 'kvt_process_client', true);
            $line = $pr->name;
            if ($cid) {
                $cl_obj = get_term_by('id', $cid, self::TAX_CLIENT);
                if ($cl_obj) $line .= ' (' . $cl_obj->name . ')';
            }
            $process_lines[] = $line;
            $desc = term_description($pr, self::TAX_PROCESS);
            if ($desc) $notes[] = $pr->name . ': ' . wp_strip_all_tags($desc);
            $meet = get_term_meta($pr->term_id, 'kvt_process_meetings', true);
            if ($meet) {
                $clean = preg_replace('/\r\n|\r|\n/', '; ', $meet);
                $meeting_lines[] = $pr->name . ': ' . $clean;
                $notes[] = $pr->name . ' reuniones: ' . $clean;
            }
        }

        $email_lines = [];
        $templates   = $this->get_email_templates();
        $template_lines = [];
        foreach ($templates as $tpl) {
            $template_lines[] = sanitize_text_field($tpl['title']);
        }
        foreach (array_slice($emails, 0, 5) as $em) {
            $sub = sanitize_text_field($em['subject'] ?? '');
            $to  = implode(', ', array_map('sanitize_text_field', $em['recipients'] ?? []));
            $line = $sub;
            if ($to) $line .= ' → ' . $to;
            if ($line) $email_lines[] = $line;
        }

        $news_key = get_option(self::OPT_NEWS_KEY, '');
        $news     = [];
        if ($news_key) {
            $news_url = add_query_arg([
                'apikey'   => $news_key,
                'q'        => 'energia renovable',
                'language' => 'es',
                'country'  => 'es,cl',
            ], 'https://newsdata.io/api/1/news');
            $news_resp = wp_remote_get($news_url, ['timeout' => 15]);
            if (!is_wp_error($news_resp)) {
                $news_data = json_decode(wp_remote_retrieve_body($news_resp), true);
                if (!empty($news_data['results'])) {
                    foreach (array_slice($news_data['results'], 0, 3) as $item) {
                        if (!empty($item['title'])) {
                            $news[] = sanitize_text_field($item['title']);
                        }
                    }
                }
            }
        }

        $summary  = 'Candidatos: ' . implode('; ', $cand_lines) . '.';
        $summary .= ' Clientes: ' . implode('; ', $client_lines) . '.';
        $summary .= ' Procesos: ' . implode('; ', $process_lines) . '.';
        if ($followups) {
            $summary .= ' Seguimientos pendientes: ' . implode('; ', $followups) . '.';
        }
        if ($notes) {
            $summary .= ' Notas: ' . implode(' | ', $notes) . '.';
        }
        if ($news) {
            $summary .= ' Noticias del mercado: ' . implode(' | ', $news) . '.';
        }
        if ($email_lines) {
            $summary .= ' Correos recientes: ' . implode('; ', $email_lines) . '.';
        }
        if ($meeting_lines) {
            $summary .= ' Reuniones: ' . implode(' | ', $meeting_lines) . '.';
        }
        if ($history) {
            $summary .= ' Historial: ' . implode('; ', $history) . '.';
        }
        if ($template_lines) {
            $summary .= ' Plantillas: ' . implode('; ', $template_lines) . '.';
        }

        return ['summary' => $summary, 'news' => $news];
    }

    private function mit_strip_fences($text) {
        if (strpos($text, '```') !== false) {
            $text = preg_replace('/^```\w*\n?/', '', $text);
            $text = preg_replace('/\n?```$/', '', $text);
        }
        return trim($text);
    }

    private function mit_create_excel() {
        if (!class_exists('\\PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
            error_log('mit_create_excel: PhpSpreadsheet not available');
            return new \WP_Error('missing_phpspreadsheet', __('La librería PhpSpreadsheet no está instalada.', 'kovacic'));
        }

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', 'Nombre');
        $sheet->setCellValue('B1', 'Email');

        $cands = get_posts([
            'post_type'   => self::CPT,
            'post_status' => 'any',
            'numberposts' => -1,
        ]);
        $row = 2;
        foreach ($cands as $c) {
            $sheet->setCellValue('A' . $row, $c->post_title);
            $email = $this->meta_get_compat($c->ID, 'kvt_email', ['email']);
            $sheet->setCellValue('B' . $row, $email);
            $row++;
        }

        $upload   = wp_upload_dir();
        $filename = wp_unique_filename($upload['path'], 'mit_export.xlsx');
        $filepath = trailingslashit($upload['path']) . $filename;
        $writer   = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        try {
            $writer->save($filepath);
        } catch (\Throwable $e) {
            error_log('mit_create_excel: ' . $e->getMessage());
            return new \WP_Error('excel_write_failed', __('Error al guardar el Excel: ', 'kovacic') . $e->getMessage());
        }

        return trailingslashit($upload['url']) . $filename;
    }

    public function ajax_mit_suggestions() {
        check_ajax_referer('kvt_mit', 'nonce');
        if (!current_user_can('edit_posts')) wp_send_json_error(['msg' => 'Unauthorized'], 403);
        $key = get_option(self::OPT_OPENAI_KEY, '');
        $model = get_option(self::OPT_OPENAI_MODEL, 'gpt-5');
        $uid  = get_current_user_id();
        $hist = $this->mit_load_history($uid);
        if (!$key) {
            wp_send_json_success(['suggestions' => __('Falta la clave de OpenAI', 'kovacic'), 'history' => $hist['messages']]);
        }

        $ctx = $this->mit_gather_context();
        $summary = $ctx['summary'];
        $news    = $ctx['news'];

        $hist['summary'] = $summary;
        $prompt = "Eres MIT, el asistente personal de la empresa, con acceso a todos los datos del negocio y recordando los correos diarios enviados y su contexto. No inventes datos nuevos; utiliza únicamente la información disponible y, si falta algún dato, indícalo. Con los siguientes datos: $summary Proporciona recordatorios de seguimiento con candidatos y clientes, consejos para captar nuevos clientes y candidatos y ejemplos de correos electrónicos breves para contacto o seguimiento. Devuelve la respuesta en HTML usando <h3> para títulos de sección, <ul><li> para listas, <blockquote> para plantillas de correo, <strong> para nombres o roles importantes y separa secciones con <hr>. You can also recommend linkedin posts for engagement, when creating e-mail templates consider these variables, keep in mind these are connected to what is already set to the candidates profile. So if you recommend a new role, do not use {{role}} as it will refer to the candidates actual role. Variables disponibles: {{first_name}}, {{surname}}, {{country}}, {{city}}, {{client}}, {{role}}, {{status}}, {{board}} (enlace al tablero), {{sender}} (remitente). Al final, incluye hasta 5 sugerencias de agenda en una lista HTML <ul id=\"mit_agenda\">; cada <li> debe tener data-date (DD/MM/YYYY) y data-action, contener un <p class=\"strategy\"> con la estrategia o explicación y un <blockquote class=\"template\"> con una plantilla de correo breve. Evita proponer fechas en sábado o domingo o en el pasado.";
        $resp = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $key,
                'Content-Type'  => 'application/json',
            ],
            'body' => json_encode([
                'model'   => $model,
                'messages'=> [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]),
            'timeout' => self::MIT_TIMEOUT,
        ]);
        $text = '';
        $agenda = [];
        if (!is_wp_error($resp)) {
            $data = json_decode(wp_remote_retrieve_body($resp), true);
            $text = trim($data['choices'][0]['message']['content'] ?? '');
        }
        if ($text) {
            $text = $this->mit_strip_fences($text);
            if (preg_match('/<ul id="mit_agenda">.*?<\/ul>/s', $text, $m)) {
                $ul = $m[0];
                $doc = new \DOMDocument();
                \libxml_use_internal_errors(true);
                $doc->loadHTML($ul);
                foreach ($doc->getElementsByTagName('li') as $li) {
                    $date = $li->getAttribute('data-date');
                    $action = $li->getAttribute('data-action');
                    $strategy = '';
                    $template = '';
                    foreach ($li->childNodes as $child) {
                        if ($child->nodeName === 'p') $strategy .= $child->textContent;
                        if ($child->nodeName === 'blockquote') $template .= $doc->saveHTML($child);
                    }
                    if ($date && $action) {
                        $parts = explode('/', $date);
                        if (count($parts) === 3) {
                            $d = \DateTime::createFromFormat('d/m/Y', $date, new \DateTimeZone('Europe/Madrid'));
                            $d->setTime(0,0);
                            $today = new \DateTime('today', new \DateTimeZone('Europe/Madrid'));
                            if ($d < $today) continue;
                        }
                        $agenda[] = [
                            'date'     => $date,
                            'text'     => $action,
                            'strategy' => trim($strategy),
                            'template' => wp_kses_post(trim($template)),
                        ];
                    }
                }
                $text = str_replace($ul, '', $text);
            }
            $plain = preg_replace('/<\/(li|p)>/i', "\n", $text);
            $plain = preg_replace('/<br\s*\/?\>/i', "\n", $plain);
            $plain = wp_strip_all_tags($plain);
            $hist['messages'][] = ['role' => 'assistant', 'content' => $plain, 'html' => $text];
            $this->mit_summarize_history($hist, $key, $model);
        } else {
            $plain = '';
        }
        $this->mit_save_history($uid, $hist);
        if (is_wp_error($resp)) {
            $err = $resp->get_error_message();
            $text = sprintf(__('No se pudo conectar con OpenAI: %s', 'kovacic'), $err);
            $plain = $text;
        }
        wp_send_json_success([
            'suggestions'       => $plain,
            'suggestions_html'  => wp_kses_post($text),
            'agenda'            => $agenda,
            'news'              => $news,
            'history'           => $hist['messages'],
        ]);
    }

    public function ajax_mit_chat() {
        check_ajax_referer('kvt_mit', 'nonce');
        if (!current_user_can('edit_posts')) wp_send_json_error(['msg' => 'Unauthorized'], 403);
        $msg = isset($_POST['message']) ? sanitize_text_field(wp_unslash($_POST['message'])) : '';
        $key = get_option(self::OPT_OPENAI_KEY, '');
        $model = get_option(self::OPT_OPENAI_MODEL, 'gpt-5');
        $uid  = get_current_user_id();
        if (!$key || !$msg) {
            wp_send_json_error(['msg' => __('Falta la clave de OpenAI', 'kovacic')]);
        }

        $hist    = $this->mit_load_history($uid);
        $ctx     = $this->mit_gather_context();
        $summary = $ctx['summary'];

        $identity = 'Eres MIT, el asistente personal de la empresa. Conoces todos los datos del negocio y recuerdas los correos diarios enviados y su contexto. No inventes datos nuevos; utiliza únicamente la información disponible y, si falta algún dato, indícalo.';

        // Ensure system identity and summary are always the first entries
        if (empty($hist['messages']) || ($hist['messages'][0]['role'] ?? '') !== 'system') {
            array_unshift($hist['messages'], ['role' => 'system', 'content' => $identity]);
        } else {
            $hist['messages'][0]['content'] = $identity;
        }
        if (!isset($hist['messages'][1]) || ($hist['messages'][1]['role'] ?? '') !== 'system') {
            array_splice($hist['messages'], 1, 0, [[
                'role'    => 'system',
                'content' => $summary,
            ]]);
        } else {
            $hist['messages'][1]['content'] = $summary;
        }

        $hist['messages'][] = ['role' => 'user', 'content' => $msg];

        $resp = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $key,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode([
                'model'    => $model,
                'messages' => $hist['messages'],
            ]),
            'timeout' => self::MIT_TIMEOUT,
        ]);

        $reply    = '';
        if (!is_wp_error($resp)) {
            $data  = json_decode(wp_remote_retrieve_body($resp), true);
            $reply = trim($data['choices'][0]['message']['content'] ?? '');
        }
        if ($reply) {
            $reply = $this->mit_strip_fences($reply);
            // Detect request for Excel generation based on user message
            if (stripos($msg, 'excel') !== false) {
                $autoload = __DIR__ . '/vendor/autoload.php';
                if (file_exists($autoload)) {
                    require_once $autoload;
                }
                $file_result = $this->mit_create_excel();
                if (is_wp_error($file_result)) {
                    $reply .= "\n\n" . $file_result->get_error_message();
                } elseif ($file_result) {
                    $reply .= "\n\n<a href='" . esc_url($file_result) . "' target='_blank'>" . __('Descargar Excel generado', 'kovacic') . "</a>";
                } else {
                    $reply .= "\n\n" . __('No se pudo generar el archivo Excel.', 'kovacic');
                }
            }

            $hist['messages'][] = ['role' => 'assistant', 'content' => wp_strip_all_tags($reply), 'html' => $reply];
            $this->mit_summarize_history($hist, $key, $model);

            // Preserve system identity and context after summarizing
            if (($hist['messages'][0]['role'] ?? '') !== 'system' || $hist['messages'][0]['content'] !== $identity) {
                array_unshift($hist['messages'], ['role' => 'system', 'content' => $identity]);
            } else {
                $hist['messages'][0]['content'] = $identity;
            }
            if (!isset($hist['messages'][1]) || ($hist['messages'][1]['role'] ?? '') !== 'system') {
                array_splice($hist['messages'], 1, 0, [[
                    'role'    => 'system',
                    'content' => $summary,
                ]]);
            } else {
                $hist['messages'][1]['content'] = $summary;
            }

            $this->mit_save_history($uid, $hist);
            wp_send_json_success([
                'reply'   => wp_kses_post($reply),
                'history' => $hist['messages'],
                'file'    => $file_url,
            ]);
        }

        wp_send_json_error(['msg' => __('Sin respuesta', 'kovacic')]);
    }

    public function mit_chat_widget() {
        return;
    }

    public function ajax_update_status() {
        check_ajax_referer('kvt_nonce');

        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $st = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
        $comment = isset($_POST['comment']) ? sanitize_text_field($_POST['comment']) : '';
        $author  = isset($_POST['author']) ? sanitize_text_field($_POST['author']) : '';
        if(!$author){
            $u = wp_get_current_user();
            if($u && $u->exists()) $author = $u->display_name;
        }

        $statuses = $this->get_statuses();
        if (!$id || !in_array($st, $statuses, true)) {
            wp_send_json_error(['msg'=>'Invalid'], 400);
        }
        update_post_meta($id, 'kvt_status', $st);
        if ($comment || $author) {
            $history = get_post_meta($id, 'kvt_status_history', true);
            if (!is_array($history)) $history = [];
            $history[] = [
                'status'  => $st,
                'author'  => $author,
                'comment' => $comment,
                'time'    => current_time('mysql'),
            ];
            update_post_meta($id, 'kvt_status_history', $history);
        }
        $log = get_post_meta($id, 'kvt_activity_log', true);
        if (!is_array($log)) $log = [];
        $log[] = [
            'type'   => 'status',
            'status' => $st,
            'author' => $author,
            'comment'=> $comment,
            'time'   => current_time('mysql'),
        ];
        update_post_meta($id, 'kvt_activity_log', $log);
        wp_send_json_success(['ok'=>true]);
    }

    public function ajax_add_task() {
        check_ajax_referer('kvt_nonce');
        $id   = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
        $time = isset($_POST['time']) ? sanitize_text_field($_POST['time']) : '';
        $note = isset($_POST['note']) ? sanitize_text_field($_POST['note']) : '';
        $author = isset($_POST['author']) ? sanitize_text_field($_POST['author']) : '';
        if(!$author){
            $u = wp_get_current_user();
            if($u && $u->exists()) $author = $u->display_name;
        }
        if (!$id || !$date) wp_send_json_error(['msg'=>'Invalid'], 400);
        update_post_meta($id, 'kvt_next_action', $date);
        update_post_meta($id, 'next_action', $date);
        update_post_meta($id, 'kvt_next_action_time', $time);
        update_post_meta($id, 'kvt_next_action_note', $note);
        update_post_meta($id, 'next_action_note', $note);
        $log = get_post_meta($id, 'kvt_activity_log', true);
        if(!is_array($log)) $log = [];
        $log[] = [
            'type'  => 'task_add',
            'date'  => $date . ($time ? ' '.$time : ''),
            'note'  => $note,
            'author'=> $author,
            'time'  => current_time('mysql'),
        ];
        update_post_meta($id, 'kvt_activity_log', $log);
        wp_send_json_success(['ok'=>true]);
    }

    public function ajax_complete_task() {
        check_ajax_referer('kvt_nonce');
        $id   = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $comment = isset($_POST['comment']) ? sanitize_text_field($_POST['comment']) : '';
        $author = isset($_POST['author']) ? sanitize_text_field($_POST['author']) : '';
        if(!$author){
            $u = wp_get_current_user();
            if($u && $u->exists()) $author = $u->display_name;
        }
        if(!$id) wp_send_json_error(['msg'=>'Invalid'],400);
        $date = get_post_meta($id, 'kvt_next_action', true);
        $note = get_post_meta($id, 'kvt_next_action_note', true);
        delete_post_meta($id, 'kvt_next_action');
        delete_post_meta($id, 'next_action');
        delete_post_meta($id, 'kvt_next_action_note');
        delete_post_meta($id, 'next_action_note');
        $log = get_post_meta($id, 'kvt_activity_log', true);
        if(!is_array($log)) $log = [];
        $log[] = [
            'type'    => 'task_done',
            'date'    => $date,
            'note'    => $note,
            'comment' => $comment,
            'author'  => $author,
            'time'    => current_time('mysql'),
        ];
        update_post_meta($id, 'kvt_activity_log', $log);
        wp_send_json_success(['ok'=>true]);
    }

    public function ajax_delete_task() {
        check_ajax_referer('kvt_nonce');
        $id   = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $author = isset($_POST['author']) ? sanitize_text_field($_POST['author']) : '';
        if(!$author){
            $u = wp_get_current_user();
            if($u && $u->exists()) $author = $u->display_name;
        }
        if(!$id) wp_send_json_error(['msg'=>'Invalid'],400);
        $date = get_post_meta($id, 'kvt_next_action', true);
        $note = get_post_meta($id, 'kvt_next_action_note', true);
        delete_post_meta($id, 'kvt_next_action');
        delete_post_meta($id, 'next_action');
        delete_post_meta($id, 'kvt_next_action_note');
        delete_post_meta($id, 'next_action_note');
        $log = get_post_meta($id, 'kvt_activity_log', true);
        if(!is_array($log)) $log = [];
        $log[] = [
            'type'   => 'task_deleted',
            'date'   => $date,
            'note'   => $note,
            'author' => $author,
            'time'   => current_time('mysql'),
        ];
        update_post_meta($id, 'kvt_activity_log', $log);
        wp_send_json_success(['ok'=>true]);
    }

    public function ajax_update_notes() {
        check_ajax_referer('kvt_nonce');
        $id     = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $notes  = isset($_POST['notes']) ? wp_kses_post($_POST['notes']) : '';
        $note   = isset($_POST['note']) ? sanitize_text_field($_POST['note']) : '';
        $author = isset($_POST['author']) ? sanitize_text_field($_POST['author']) : '';
        if(!$author){ $u = wp_get_current_user(); if($u && $u->exists()) $author = $u->display_name; }
        if (!$id) wp_send_json_error(['msg'=>'Invalid'], 400);
        update_post_meta($id, 'kvt_notes', $notes);
        update_post_meta($id, 'notes', $notes);
        if($note !== ''){
            $log = get_post_meta($id, 'kvt_activity_log', true);
            if(!is_array($log)) $log = [];
            $log[] = [
                'type'   => 'note',
                'note'   => $note,
                'author' => $author,
                'time'   => current_time('mysql'),
            ];
            update_post_meta($id, 'kvt_activity_log', $log);
        }
        wp_send_json_success(['ok'=>true]);
    }

    public function ajax_update_public_notes() {
        check_ajax_referer('kvt_nonce');
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $notes = isset($_POST['notes']) ? wp_kses_post($_POST['notes']) : '';
        if (!$id) wp_send_json_error(['msg'=>'Invalid'], 400);
        update_post_meta($id, 'kvt_public_notes', $notes);
        update_post_meta($id, 'public_notes', $notes);
        wp_send_json_success(['ok'=>true]);
    }

    public function ajax_delete_notes() {
        check_ajax_referer('kvt_nonce');
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if (!$id) wp_send_json_error(['msg'=>'Invalid'], 400);
        delete_post_meta($id, 'kvt_notes');
        delete_post_meta($id, 'notes');
        wp_send_json_success(['ok'=>true]);
    }

    public function ajax_delete_public_notes() {
        check_ajax_referer('kvt_nonce');
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if (!$id) wp_send_json_error(['msg'=>'Invalid'], 400);
        delete_post_meta($id, 'kvt_public_notes');
        delete_post_meta($id, 'public_notes');
        wp_send_json_success(['ok'=>true]);
    }

    public function ajax_delete_candidate() {
        check_ajax_referer('kvt_nonce');
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if (!$id) wp_send_json_error(['msg'=>'Invalid'], 400);
        $res = wp_trash_post($id);
        if (!$res) wp_send_json_error(['msg'=>'No se pudo mover a la papelera.']);
        wp_send_json_success(['ok'=>true]);
    }

    public function ajax_update_profile() {
        check_ajax_referer('kvt_nonce');

        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if (!$id || get_post_type($id)!==self::CPT) wp_send_json_error(['msg'=>'Invalid'],400);

        $fields = [
            'kvt_first_name' => isset($_POST['first_name']) ? sanitize_text_field($_POST['first_name']) : '',
            'kvt_last_name'  => isset($_POST['last_name'])  ? sanitize_text_field($_POST['last_name'])  : '',
            'kvt_email'      => isset($_POST['email'])      ? sanitize_email($_POST['email'])           : '',
            'kvt_phone'      => isset($_POST['phone'])      ? sanitize_text_field($_POST['phone'])      : '',
            'kvt_country'    => isset($_POST['country'])    ? sanitize_text_field($_POST['country'])    : '',
            'kvt_city'       => isset($_POST['city'])       ? sanitize_text_field($_POST['city'])       : '',
            'kvt_current_role'=> isset($_POST['current_role']) ? sanitize_text_field($_POST['current_role']) : '',
            'kvt_role'       => isset($_POST['role']) ? sanitize_text_field($_POST['role']) : '',
            'kvt_company'    => isset($_POST['company']) ? sanitize_text_field($_POST['company']) : '',
            'kvt_tags'       => isset($_POST['tags'])       ? sanitize_text_field($_POST['tags'])       : '',
            'kvt_cv_url'     => isset($_POST['cv_url'])     ? esc_url_raw($_POST['cv_url'])             : '',
            'kvt_cv_uploaded'=> isset($_POST['cv_uploaded'])? sanitize_text_field($_POST['cv_uploaded']): '',
            'kvt_next_action'=> isset($_POST['next_action'])? sanitize_text_field($_POST['next_action']): '',
            'kvt_next_action_note'=> isset($_POST['next_action_note'])? sanitize_text_field($_POST['next_action_note']): '',
            'kvt_notes'      => isset($_POST['notes'])      ? wp_kses_post($_POST['notes'])             : '',
        ];
        $fields['kvt_first_name'] = $this->normalize_name($fields['kvt_first_name']);
        $fields['kvt_last_name']  = $this->normalize_name($fields['kvt_last_name']);
        if ($fields['kvt_cv_uploaded']) $fields['kvt_cv_uploaded'] = $this->fmt_date_ddmmyyyy($fields['kvt_cv_uploaded']);
        if ($fields['kvt_next_action']) $fields['kvt_next_action'] = $this->fmt_date_ddmmyyyy($fields['kvt_next_action']);

        $changed = [];
        foreach ($fields as $k=>$v) {
            $old = get_post_meta($id, $k, true);
            if ($old !== $v) $changed[] = str_replace('kvt_', '', $k);
            update_post_meta($id, $k, $v);
            $legacy = str_replace('kvt_', '', $k);
            update_post_meta($id, $legacy, $v);
        }

        $title = get_the_title($id);
        if (!$title) {
            $fn = $fields['kvt_first_name']; $ln = $fields['kvt_last_name'];
            $new = trim($fn.' '.$ln);
            if ($new) wp_update_post(['ID'=>$id,'post_title'=>$new]);
        }

        if(!empty($changed)){
            $log = get_post_meta($id, 'kvt_activity_log', true);
            if(!is_array($log)) $log = [];
            $u = wp_get_current_user();
            $author = ($u && $u->exists()) ? $u->display_name : '';
            $log[] = [
                'type'   => 'update',
                'fields' => $changed,
                'author' => $author,
                'time'   => current_time('mysql'),
            ];
            update_post_meta($id, 'kvt_activity_log', $log);
        }

        wp_send_json_success(['ok'=>true]);
    }

    public function ajax_upload_cv() {
        check_ajax_referer('kvt_nonce');

        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if (!$id || get_post_type($id)!==self::CPT) wp_send_json_error(['msg'=>'Invalid'],400);
        if (empty($_FILES['file']['name'])) wp_send_json_error(['msg'=>'Archivo no recibido'],400);

        if (!function_exists('media_handle_upload')) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
        }
        add_filter('upload_mimes', function($mimes){
            $mimes['pdf']  = 'application/pdf';
            $mimes['doc']  = 'application/msword';
            $mimes['docx'] = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
            return $mimes;
        });

        // Remove previous cached text if exists
        $old_txt = get_post_meta($id, 'kvt_cv_text_url', true);
        if ($old_txt) {
            $path = wp_parse_url($old_txt, PHP_URL_PATH);
            if ($path) @unlink(ABSPATH . ltrim($path, '/'));
        }
        delete_post_meta($id, 'kvt_cv_text');
        delete_post_meta($id, 'kvt_cv_text_url');

        $attach_id = media_handle_upload('file', $id);
        if (is_wp_error($attach_id)) wp_send_json_error(['msg'=>$attach_id->get_error_message()],500);

        $url = wp_get_attachment_url($attach_id);
        update_post_meta($id, 'kvt_cv_attachment_id', $attach_id);
        update_post_meta($id, 'kvt_cv_url', esc_url_raw($url));
        update_post_meta($id, 'cv_url', esc_url_raw($url));
        $today = date_i18n('d-m-Y');
        update_post_meta($id, 'kvt_cv_uploaded', $today);
        update_post_meta($id, 'cv_uploaded', $today);

        // Generate text version for AI processing
        $client_text = isset($_POST['cv_text']) ? sanitize_textarea_field(wp_unslash($_POST['cv_text'])) : '';
        $txt_url = '';
        if ($client_text !== '') {
            update_post_meta($id, 'kvt_cv_text', $client_text);
        } else {
            $this->save_cv_text_attachment($id, $attach_id);
            $txt_url = get_post_meta($id, 'kvt_cv_text_url', true);
        }

        // Extract profile details using AI
        $fields = $this->update_profile_from_cv($id);
        wp_send_json_success(['url'=>$url,'date'=>$today,'text_url'=>$txt_url,'fields'=>$fields,'current_role'=>($fields['current_role']??'')]);
    }

    public function ajax_parse_cv() {
        check_ajax_referer('kvt_nonce');

        if (empty($_FILES['file']['name'])) wp_send_json_error(['msg'=>'Archivo no recibido'],400);

        if (!function_exists('wp_handle_upload')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        add_filter('upload_mimes', function($mimes){
            $mimes['pdf']  = 'application/pdf';
            $mimes['doc']  = 'application/msword';
            $mimes['docx'] = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
            return $mimes;
        });

        $uploaded = wp_handle_upload($_FILES['file'], ['test_form'=>false]);
        if (isset($uploaded['error'])) wp_send_json_error(['msg'=>$uploaded['error']],500);

        $text = isset($_POST['cv_text']) ? sanitize_textarea_field(wp_unslash($_POST['cv_text'])) : '';
        if ($text === '' && isset($uploaded['file'])) {
            $text = $this->extract_text_from_file($uploaded['file']);
        }
        if (isset($uploaded['file'])) @unlink($uploaded['file']);

        $key = get_option(self::OPT_OPENAI_KEY, '');
        $fields = ($text && $key) ? $this->openai_extract_profile_fields($key, $text) : [];
        wp_send_json_success(['fields'=>$fields]);
    }

    public function ajax_list_profiles() {
        check_ajax_referer('kvt_nonce');

        $page      = isset($_POST['page']) ? max(1, intval($_POST['page'])) : 1;
        $name      = isset($_POST['name']) ? sanitize_text_field(trim($_POST['name'])) : '';
        $role      = isset($_POST['role']) ? sanitize_text_field(trim($_POST['role'])) : '';
        $location  = isset($_POST['location']) ? sanitize_text_field(trim($_POST['location'])) : '';

        $args = [
            'post_type'      => self::CPT,
            'post_status'    => 'any',
            'posts_per_page' => 10,
            'paged'          => $page,
            'no_found_rows'  => false,
        ];

        $meta_query = ['relation' => 'AND'];

        if ($name !== '') {
            $meta_query[] = [
                'relation' => 'OR',
                ['key'=>'kvt_first_name','value'=>$name,'compare'=>'LIKE'],
                ['key'=>'kvt_last_name','value'=>$name,'compare'=>'LIKE'],
                ['key'=>'first_name','value'=>$name,'compare'=>'LIKE'],
                ['key'=>'last_name','value'=>$name,'compare'=>'LIKE'],
            ];
        }

        if ($role !== '') {
            $role_parts = array_filter(array_map('trim', explode(',', $role)));
            $role_sub   = ['relation' => 'OR'];
            foreach ($role_parts as $rpart) {
                $role_sub[] = ['key'=>'kvt_current_role','value'=>$rpart,'compare'=>'LIKE'];
                $role_sub[] = ['key'=>'current_role','value'=>$rpart,'compare'=>'LIKE'];
            }
            if (count($role_sub) > 1) $meta_query[] = $role_sub;
        }

        if ($location !== '') {
            $meta_query[] = [
                'relation' => 'OR',
                ['key'=>'kvt_country','value'=>$location,'compare'=>'LIKE'],
                ['key'=>'kvt_city','value'=>$location,'compare'=>'LIKE'],
                ['key'=>'country','value'=>$location,'compare'=>'LIKE'],
                ['key'=>'city','value'=>$location,'compare'=>'LIKE'],
            ];
        }

        if (count($meta_query) > 1) {
            $args['meta_query'] = $meta_query;
        }

        $q = new WP_Query($args);
        $items = [];
        foreach ($q->posts as $p) {
            $notes_raw = get_post_meta($p->ID,'kvt_notes',true);
            if ($notes_raw === '') $notes_raw = get_post_meta($p->ID,'notes',true);
            $items[] = [
                'id'   => $p->ID,
                'meta' => [
                    'first_name'  => $this->meta_get_compat($p->ID,'kvt_first_name',['first_name']),
                    'last_name'   => $this->meta_get_compat($p->ID,'kvt_last_name',['last_name']),
                    'email'       => $this->meta_get_compat($p->ID,'kvt_email',['email']),
                    'phone'       => $this->meta_get_compat($p->ID,'kvt_phone',['phone']),
                    'country'     => $this->meta_get_compat($p->ID,'kvt_country',['country']),
                    'city'        => $this->meta_get_compat($p->ID,'kvt_city',['city']),
                    'current_role'=> $this->meta_get_compat($p->ID,'kvt_current_role',['current_role']),
                    'tags'        => $this->meta_get_compat($p->ID,'kvt_tags',['tags']),
                    'cv_url'      => $this->meta_get_compat($p->ID,'kvt_cv_url',['cv_url']),
                    'cv_uploaded' => $this->fmt_date_ddmmyyyy($this->meta_get_compat($p->ID,'kvt_cv_uploaded',['cv_uploaded'])),
                    'notes'       => $notes_raw,
                    'notes_count' => $this->count_notes($notes_raw),
                ],
            ];
        }
        wp_send_json_success(['items'=>$items,'pages'=>$q->max_num_pages]);
    }

    public function ajax_list_clients() {
        check_ajax_referer('kvt_nonce');
        $terms = get_terms(['taxonomy'=>self::TAX_CLIENT,'hide_empty'=>false]);
        $items = [];
        foreach ($terms as $t) {
            $procs = get_terms([
                'taxonomy'=>self::TAX_PROCESS,
                'hide_empty'=>false,
                'meta_query'=>[
                    ['key'=>'kvt_process_client','value'=>$t->term_id]
                ]
            ]);
            $items[] = [
                'id' => $t->term_id,
                'name' => $t->name,
                'contact_name'  => get_term_meta($t->term_id,'contact_name',true),
                'contact_email' => get_term_meta($t->term_id,'contact_email',true),
                'contact_phone' => get_term_meta($t->term_id,'contact_phone',true),
                'description'   => wp_strip_all_tags($t->description),
                'sector'        => get_term_meta($t->term_id,'kvt_client_sector',true),
                'meetings'      => get_term_meta($t->term_id,'kvt_client_meetings',true),
                'processes'     => wp_list_pluck($procs,'name'),
                'edit_url'      => admin_url('term.php?taxonomy=' . self::TAX_CLIENT . '&tag_ID=' . $t->term_id),
            ];
        }
        wp_send_json_success(['items'=>$items]);
    }

    public function ajax_list_processes() {
        check_ajax_referer('kvt_nonce');
        $status_filter = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
        $client_filter = isset($_POST['client']) ? intval($_POST['client']) : 0;
        $terms = get_terms(['taxonomy'=>self::TAX_PROCESS,'hide_empty'=>false]);
        $items = [];
        $statuses = array_values(array_filter(array_map('trim', explode("\n", get_option(self::OPT_STATUSES, '')))));
        foreach ($terms as $t) {
            $client_id = (int) get_term_meta($t->term_id,'kvt_process_client',true);
            $client_name = $client_id ? get_term($client_id)->name : '';
            if ($client_filter && $client_id !== $client_filter) continue;
            $creator_id = (int) get_term_meta($t->term_id,'kvt_process_creator',true);
            $creator = '';
            if ($creator_id) {
                $u = get_user_by('id', $creator_id);
                if ($u) $creator = $u->display_name;
            }
            $created = get_term_meta($t->term_id,'kvt_process_created',true);
            $created_fmt = $created ? date_i18n('d/m/Y', strtotime($created)) : '';
            $status  = get_term_meta($t->term_id,'kvt_process_status',true);
            if (!$status) $status = 'active';
            if ($status_filter && $status !== $status_filter) continue;
            $end     = get_term_meta($t->term_id,'kvt_process_end',true);
            $start_ts = $created ? strtotime($created) : 0;
            $end_ts   = ($status === 'active') ? current_time('timestamp') : ($end ? strtotime($end) : current_time('timestamp'));
            $days_active = $start_ts ? floor(($end_ts - $start_ts)/DAY_IN_SECONDS) : 0;

            // Determine furthest active job stage
            $job_stage = '';
            $posts = get_posts([
                'post_type'   => self::CPT,
                'numberposts' => -1,
                'fields'      => 'ids',
                'tax_query'   => [[
                    'taxonomy' => self::TAX_PROCESS,
                    'terms'    => $t->term_id,
                ]],
            ]);
            $max = -1;
            foreach ($posts as $pid) {
                $st = get_post_meta($pid,'kvt_status',true);
                $idx = array_search($st, $statuses, true);
                if ($idx !== false && strtolower($st) !== 'declined' && $idx > $max) {
                    $max = $idx;
                }
            }
            $candidate_count = count($posts);
            if ($max >= 0 && isset($statuses[$max])) $job_stage = $statuses[$max];

            $items[] = [
                'id' => $t->term_id,
                'name' => $t->name,
                'client_id' => $client_id,
                'client' => $client_name,
                'contact_name'  => get_term_meta($t->term_id,'contact_name',true),
                'contact_email' => get_term_meta($t->term_id,'contact_email',true),
                'description'   => $t->description,
                'meetings'      => get_term_meta($t->term_id,'kvt_process_meetings',true),
                'creator'       => $creator,
                'created'       => $created_fmt,
                'status'        => $status,
                'days'          => $days_active,
                'candidates'    => $candidate_count,
                'job_stage'     => $job_stage,
                'edit_url'      => admin_url('term.php?taxonomy=' . self::TAX_PROCESS . '&tag_ID=' . $t->term_id),
            ];
        }
        wp_send_json_success(['items'=>$items]);
    }

    public function ajax_clone_profile() {
        check_ajax_referer('kvt_nonce');

        $source_id  = isset($_POST['source_id']) ? intval($_POST['source_id']) : 0;
        $process_id = isset($_POST['process_id']) ? intval($_POST['process_id']) : 0;
        $client_id  = isset($_POST['client_id']) ? intval($_POST['client_id']) : 0;

        $title = '';
        $meta  = [];
        if ($source_id) {
            if (get_post_type($source_id) !== self::CPT) {
                wp_send_json_error(['msg'=>'Invalid source'],400);
            }
            $title = get_the_title($source_id);
            $all_meta = get_post_meta($source_id);
            foreach ($all_meta as $k => $vals) {
                $meta[$k] = maybe_unserialize($vals[0]);
            }
        }

        $new_id = wp_insert_post([
            'post_type'   => self::CPT,
            'post_status' => 'publish',
            'post_title'  => $title,
        ]);
        if (!$new_id || is_wp_error($new_id)) {
            wp_send_json_error(['msg'=>'No se pudo crear.'],500);
        }

        foreach ($meta as $k => $v) {
            update_post_meta($new_id, $k, $v);
        }
        if (!isset($meta['kvt_status'])) {
            $statuses = $this->get_statuses();
            if (!empty($statuses)) update_post_meta($new_id,'kvt_status',$statuses[0]);
        }
        if ($client_id) wp_set_object_terms($new_id, [$client_id], self::TAX_CLIENT, false);
        if ($process_id) wp_set_object_terms($new_id, [$process_id], self::TAX_PROCESS, false);

        $title = get_the_title($new_id);
        if (!$title) {
            $fn = get_post_meta($new_id,'kvt_first_name',true);
            $ln = get_post_meta($new_id,'kvt_last_name',true);
            $new = trim($fn.' '.$ln);
            if ($new) wp_update_post(['ID'=>$new_id,'post_title'=>$new]);
        }

        wp_send_json_success(['id'=>$new_id]);
    }

    public function ajax_create_candidate() {
        check_ajax_referer('kvt_nonce');

        $first      = isset($_POST['first_name']) ? sanitize_text_field($_POST['first_name']) : '';
        $last       = isset($_POST['last_name'])  ? sanitize_text_field($_POST['last_name'])  : '';
        $first      = $this->normalize_name($first);
        $last       = $this->normalize_name($last);
        $email      = isset($_POST['email'])      ? sanitize_email($_POST['email'])           : '';
        $phone      = isset($_POST['phone'])      ? sanitize_text_field($_POST['phone'])      : '';
        $country    = isset($_POST['country'])    ? sanitize_text_field($_POST['country'])    : '';
        $city       = isset($_POST['city'])       ? sanitize_text_field($_POST['city'])       : '';
        $role       = isset($_POST['current_role']) ? sanitize_text_field($_POST['current_role']) : '';
        $company    = isset($_POST['company'])    ? sanitize_text_field($_POST['company'])    : '';
        $tags       = isset($_POST['tags'])       ? sanitize_text_field($_POST['tags'])       : '';
        $cv_url     = isset($_POST['cv_url'])     ? esc_url_raw($_POST['cv_url'])             : '';
        $client_id  = isset($_POST['client_id'])  ? intval($_POST['client_id'])               : 0;
        $process_id = isset($_POST['process_id']) ? intval($_POST['process_id'])              : 0;

        $title = trim($first.' '.$last);
        if (!$title) $title = $email;

        $new_id = wp_insert_post([
            'post_type'   => self::CPT,
            'post_status' => 'publish',
            'post_title'  => $title ?: 'Candidate',
        ]);
        if (!$new_id || is_wp_error($new_id)) {
            wp_send_json_error(['msg'=>'No se pudo crear el candidato.'],500);
        }

        $fields = [
            'kvt_first_name' => $first,
            'kvt_last_name'  => $last,
            'kvt_email'      => $email,
            'kvt_phone'      => $phone,
            'kvt_country'    => $country,
            'kvt_city'       => $city,
            'kvt_role'       => $role,
            'kvt_company'    => $company,
            'kvt_tags'       => $tags,
            'kvt_cv_url'     => $cv_url,
        ];
        if ($role || $company) {
            $combined = $role && $company ? $role . ' at ' . $company : ($role ?: $company);
            $fields['kvt_current_role'] = $combined;
        }
        foreach ($fields as $k => $v) {
            update_post_meta($new_id, $k, $v);
            update_post_meta($new_id, str_replace('kvt_','',$k), $v);
        }
        $statuses = $this->get_statuses();
        if (!empty($statuses)) update_post_meta($new_id,'kvt_status',$statuses[0]);
        if ($client_id) wp_set_object_terms($new_id, [$client_id], self::TAX_CLIENT, false);
        if ($process_id) wp_set_object_terms($new_id, [$process_id], self::TAX_PROCESS, false);

        $log = [];
        $u = wp_get_current_user();
        $author = ($u && $u->exists()) ? $u->display_name : '';
        $log[] = [
            'type'   => 'created',
            'author' => $author,
            'time'   => current_time('mysql'),
        ];
        if ($process_id) {
            $pterm = get_term($process_id, self::TAX_PROCESS);
            $pname = ($pterm && !is_wp_error($pterm)) ? $pterm->name : '';
            $log[] = [
                'type'   => 'assign',
                'process'=> $pname,
                'author' => $author,
                'time'   => current_time('mysql'),
            ];
        }
        update_post_meta($new_id, 'kvt_activity_log', $log);

        wp_send_json_success(['id'=>$new_id]);
    }

    public function ajax_bulk_upload_cvs() {
        check_ajax_referer('kvt_nonce');

        if (empty($_FILES['files']['name']) || !is_array($_FILES['files']['name'])) {
            wp_send_json_error(['msg'=>'Archivos no recibidos'],400);
        }

        if (!function_exists('wp_handle_upload')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
        }
        add_filter('upload_mimes', function($m){
            $m['pdf']  = 'application/pdf';
            $m['doc']  = 'application/msword';
            $m['docx'] = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
            return $m;
        });

        $key = get_option(self::OPT_OPENAI_KEY, '');
        if (!$key) {
            wp_send_json_error(['msg'=>'Falta la clave'], 400);
        }

        $created  = [];
        $files    = $_FILES['files'];
        $statuses = $this->get_statuses();
        $u = wp_get_current_user();
        $author = ($u && $u->exists()) ? $u->display_name : '';

        foreach ($files['name'] as $i => $name) {
            if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;

            $file = [
                'name'     => $files['name'][$i],
                'type'     => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error'    => $files['error'][$i],
                'size'     => $files['size'][$i],
            ];

            $title = sanitize_text_field(pathinfo($name, PATHINFO_FILENAME));
            $cid = wp_insert_post([
                'post_type'   => self::CPT,
                'post_status' => 'publish',
                'post_title'  => $title ?: 'Candidate',
            ]);
            if (!$cid || is_wp_error($cid)) continue;

            $uploaded = wp_handle_upload($file, ['test_form'=>false]);
            if (isset($uploaded['error'])) { wp_delete_post($cid, true); continue; }

            $attachment = [
                'post_mime_type' => $uploaded['type'],
                'post_title'     => sanitize_file_name($name),
                'post_content'   => '',
                'post_status'    => 'inherit',
            ];
            $attach_id = wp_insert_attachment($attachment, $uploaded['file'], $cid);
            if (!is_wp_error($attach_id)) {
                $attach_data = wp_generate_attachment_metadata($attach_id, $uploaded['file']);
                wp_update_attachment_metadata($attach_id, $attach_data);
                $url = $uploaded['url'];
                update_post_meta($cid, 'kvt_cv_attachment_id', $attach_id);
                update_post_meta($cid, 'kvt_cv_url', esc_url_raw($url));
                update_post_meta($cid, 'cv_url', esc_url_raw($url));
                $today = date_i18n('d-m-Y');
                update_post_meta($cid, 'kvt_cv_uploaded', $today);
                update_post_meta($cid, 'cv_uploaded', $today);
                $this->save_cv_text_attachment($cid, $attach_id);
                $fields = $this->update_profile_from_cv($cid, $key);
            } else {
                $fields = [];
            }

            if (!empty($statuses)) update_post_meta($cid, 'kvt_status', $statuses[0]);

            $first = get_post_meta($cid, 'kvt_first_name', true);
            $last  = get_post_meta($cid, 'kvt_last_name', true);
            $new_title = trim($first.' '.$last);
            if ($new_title !== '') {
                wp_update_post(['ID'=>$cid,'post_title'=>$new_title]);
            }

            $log = [
                [
                    'type'   => 'created',
                    'author' => $author,
                    'time'   => current_time('mysql'),
                ]
            ];
            update_post_meta($cid, 'kvt_activity_log', $log);

            $created[] = ['id'=>$cid, 'fields'=>$fields];
        }

        if (empty($created)) wp_send_json_error(['msg'=>'No se pudieron procesar los CVs'],500);

        wp_send_json_success(['candidates'=>$created, 'count'=>count($created)]);
    }

      public function ajax_create_client() {
          check_ajax_referer('kvt_nonce');

         $name  = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
         $cname = isset($_POST['contact_name'])  ? sanitize_text_field($_POST['contact_name'])  : '';
         $cemail= isset($_POST['contact_email']) ? sanitize_email($_POST['contact_email'])      : '';
         $cphone= isset($_POST['contact_phone']) ? sanitize_text_field($_POST['contact_phone']) : '';
         $desc  = isset($_POST['description']) ? sanitize_textarea_field($_POST['description']) : '';
         $sector= isset($_POST['sector']) ? sanitize_text_field($_POST['sector']) : '';
         $meet  = isset($_POST['meetings']) ? sanitize_textarea_field($_POST['meetings']) : '';

          if ($name === '') wp_send_json_error(['msg'=>'Nombre requerido'],400);

          $term = wp_insert_term($name, self::TAX_CLIENT, ['description'=>$desc]);
          if (is_wp_error($term)) wp_send_json_error(['msg'=>$term->get_error_message()],500);
          $tid = (int) $term['term_id'];
         update_term_meta($tid, 'contact_name', $cname);
         update_term_meta($tid, 'contact_email', $cemail);
         update_term_meta($tid, 'contact_phone', $cphone);
         if ($sector !== '') update_term_meta($tid, 'kvt_client_sector', $sector);
         if ($meet !== '') update_term_meta($tid, 'kvt_client_meetings', $meet);

          wp_send_json_success(['id'=>$tid]);
      }

      public function ajax_parse_signature() {
          check_ajax_referer('kvt_nonce');

          $text = isset($_POST['signature_text']) ? sanitize_textarea_field($_POST['signature_text']) : '';
          $img_b64 = '';
          if (!empty($_FILES['signature_image']['tmp_name'])) {
              $img = file_get_contents($_FILES['signature_image']['tmp_name']);
              if ($img !== false) {
                  $img_b64 = 'data:image/png;base64,' . base64_encode($img);
              }
          }
          if ($text === '' && $img_b64 === '') {
              wp_send_json_error(['msg' => 'Firma requerida'], 400);
          }
          $key = get_option(self::OPT_OPENAI_KEY, '');
          if (!$key) {
              wp_send_json_error(['msg' => 'Falta la clave de OpenAI'], 400);
          }
          $content = [];
          if ($text !== '') $content[] = ['type' => 'text', 'text' => $text];
          if ($img_b64 !== '') $content[] = ['type' => 'image_url', 'image_url' => ['url' => $img_b64]];
          $payload = [
              'model' => 'gpt-4o-mini',
              'messages' => [
                  [
                      'role' => 'system',
                      'content' => 'Extrae datos de contacto de una firma de email. Devuelve JSON con las claves company, contact, email, phone, description.'
                  ],
                  [
                      'role' => 'user',
                      'content' => $content
                  ]
              ],
              'response_format' => [
                  'type' => 'json_schema',
                  'json_schema' => [
                      'name' => 'contact',
                      'schema' => [
                          'type' => 'object',
                          'properties' => [
                              'company' => ['type' => 'string'],
                              'contact' => ['type' => 'string'],
                              'email' => ['type' => 'string'],
                              'phone' => ['type' => 'string'],
                              'description' => ['type' => 'string'],
                          ],
                          'additionalProperties' => false
                      ]
                  ]
              ],
              'max_tokens' => 300
          ];
          $resp = wp_remote_post('https://api.openai.com/v1/chat/completions', [
              'headers' => [
                  'Authorization' => 'Bearer ' . $key,
                  'Content-Type'  => 'application/json',
              ],
              'body' => wp_json_encode($payload),
              'timeout' => 45,
          ]);
          if (is_wp_error($resp)) {
              wp_send_json_error(['msg' => 'No se pudo conectar'], 500);
          }
          $code = wp_remote_retrieve_response_code($resp);
          $body = wp_remote_retrieve_body($resp);
          $data = json_decode($body, true);
          if ($code !== 200 || empty($data['choices'][0]['message']['content'])) {
              wp_send_json_error(['msg' => 'Respuesta inválida'], 500);
          }
          $parsed = json_decode($data['choices'][0]['message']['content'], true);
          if (!is_array($parsed)) {
              wp_send_json_error(['msg' => 'No se pudo extraer'], 500);
          }
          wp_send_json_success([
              'company' => $parsed['company'] ?? '',
              'contact' => $parsed['contact'] ?? '',
              'email'   => $parsed['email'] ?? '',
              'phone'   => $parsed['phone'] ?? '',
              'description' => $parsed['description'] ?? '',
          ]);
      }

      public function ajax_create_process() {
          check_ajax_referer('kvt_nonce');

        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        $client_id = isset($_POST['client_id']) ? intval($_POST['client_id']) : 0;
        $contact_name  = isset($_POST['contact_name']) ? sanitize_text_field($_POST['contact_name']) : '';
        $contact_email = isset($_POST['contact_email']) ? sanitize_email($_POST['contact_email']) : '';
        $desc = isset($_POST['description']) ? sanitize_textarea_field($_POST['description']) : '';
        $country = isset($_POST['country']) ? sanitize_text_field(wp_unslash($_POST['country'])) : '';
        $meet  = isset($_POST['meetings']) ? sanitize_textarea_field($_POST['meetings']) : '';
        if ($name === '') wp_send_json_error(['msg'=>'Nombre requerido'],400);

        $term = wp_insert_term($name, self::TAX_PROCESS, ['description'=>$desc]);
        if (is_wp_error($term)) wp_send_json_error(['msg'=>$term->get_error_message()],500);
        $tid = (int) $term['term_id'];
        if ($client_id) update_term_meta($tid, 'kvt_process_client', $client_id);
        if ($contact_name)  update_term_meta($tid, 'contact_name', $contact_name);
        if ($contact_email) update_term_meta($tid, 'contact_email', $contact_email);
        if ($meet !== '') update_term_meta($tid, 'kvt_process_meetings', $meet);
        update_term_meta($tid, 'kvt_process_creator', get_current_user_id());
        update_term_meta($tid, 'kvt_process_created', current_time('Y-m-d'));
        update_term_meta($tid, 'kvt_process_status', 'active');

        wp_send_json_success(['id'=>$tid]);
    }

    public function ajax_update_client() {
        check_ajax_referer('kvt_nonce');

        $id    = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $name  = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        $cname = isset($_POST['contact_name'])  ? sanitize_text_field($_POST['contact_name'])  : '';
        $cemail= isset($_POST['contact_email']) ? sanitize_email($_POST['contact_email'])      : '';
        $cphone= isset($_POST['contact_phone']) ? sanitize_text_field($_POST['contact_phone']) : '';
        $desc  = isset($_POST['description']) ? sanitize_textarea_field($_POST['description']) : '';
        $sector= isset($_POST['sector']) ? sanitize_text_field($_POST['sector']) : '';
        $meet  = isset($_POST['meetings']) ? sanitize_textarea_field($_POST['meetings']) : '';

        if (!$id) wp_send_json_error(['msg'=>'ID inválido'],400);

        $term = wp_update_term($id, self::TAX_CLIENT, ['name'=>$name, 'description'=>$desc]);
        if (is_wp_error($term)) wp_send_json_error(['msg'=>$term->get_error_message()],500);

        update_term_meta($id, 'contact_name', $cname);
        update_term_meta($id, 'contact_email', $cemail);
        update_term_meta($id, 'contact_phone', $cphone);
        update_term_meta($id, 'kvt_client_sector', $sector);
        update_term_meta($id, 'kvt_client_meetings', $meet);

        wp_send_json_success(['id'=>$id]);
    }

    public function ajax_update_process() {
        check_ajax_referer('kvt_nonce');

        $id    = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $name  = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        $client_id = isset($_POST['client_id']) ? intval($_POST['client_id']) : 0;
        $contact_name  = isset($_POST['contact_name']) ? sanitize_text_field($_POST['contact_name']) : '';
        $contact_email = isset($_POST['contact_email']) ? sanitize_email($_POST['contact_email']) : '';
        $desc = isset($_POST['description']) ? sanitize_textarea_field($_POST['description']) : '';
        $meet  = isset($_POST['meetings']) ? sanitize_textarea_field($_POST['meetings']) : '';

        if (!$id) wp_send_json_error(['msg'=>'ID inválido'],400);

        $term = wp_update_term($id, self::TAX_PROCESS, ['name'=>$name, 'description'=>$desc]);
        if (is_wp_error($term)) wp_send_json_error(['msg'=>$term->get_error_message()],500);

        if ($client_id) update_term_meta($id, 'kvt_process_client', $client_id); else delete_term_meta($id, 'kvt_process_client');
        update_term_meta($id, 'contact_name', $contact_name);
        update_term_meta($id, 'contact_email', $contact_email);
        update_term_meta($id, 'kvt_process_meetings', $meet);

        wp_send_json_success(['id'=>$id]);
    }

    public function ajax_update_process_status() {
        check_ajax_referer('kvt_nonce');

        $id     = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
        if (!$id || $status === '') wp_send_json_error(['msg'=>'Datos inválidos'],400);
        update_term_meta($id, 'kvt_process_status', $status);
        if ($status === 'active') {
            delete_term_meta($id, 'kvt_process_end');
        } else {
            update_term_meta($id, 'kvt_process_end', current_time('Y-m-d'));
        }
        wp_send_json_success(['id'=>$id]);
    }

    public function ajax_ai_search() {
        check_ajax_referer('kvt_nonce');

        // Heavy search across many CVs can exceed default PHP limits.
        // Allow the process to run longer and use more memory so the request
        // can finish instead of timing out and leaving the UI hanging.
        ignore_user_abort(true);
        @set_time_limit(300);
        if (function_exists('wp_raise_memory_limit')) {
            wp_raise_memory_limit('admin');
        }

        $desc = isset($_POST['description']) ? sanitize_textarea_field($_POST['description']) : '';
        $country = isset($_POST['country']) ? sanitize_text_field(wp_unslash($_POST['country'])) : '';
        if (!$desc) wp_send_json_error(['msg' => 'Descripción vacía'], 400);

        $key = get_option(self::OPT_OPENAI_KEY, '');
        if (!$key) wp_send_json_error(['msg' => 'Falta la clave'], 400);

        // Fetch only candidate IDs to reduce memory footprint during the scan
        $args = [
            'post_type'      => self::CPT,
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ];
        if ($country) {
            $args['meta_query'] = [
                [
                    'key'   => 'kvt_country',
                    'value' => $country,
                ]
            ];
        }
        $candidate_ids = get_posts($args);
        $items = [];
        foreach ($candidate_ids as $cid) {
            $cv_text = $this->get_candidate_cv_text($cid);
            if (!$cv_text) continue;
            $res = $this->openai_match_summary($key, $desc, $cv_text);
            if ($res) {
                $meta = [
                    'first_name'  => $this->meta_get_compat($cid,'kvt_first_name',['first_name']),
                    'last_name'   => $this->meta_get_compat($cid,'kvt_last_name',['last_name']),
                    'email'       => $this->meta_get_compat($cid,'kvt_email',['email']),
                    'phone'       => $this->meta_get_compat($cid,'kvt_phone',['phone']),
                    'country'     => $this->meta_get_compat($cid,'kvt_country',['country']),
                    'city'        => $this->meta_get_compat($cid,'kvt_city',['city']),
                    'cv_url'      => $this->meta_get_compat($cid,'kvt_cv_url',['cv_url']),
                    'cv_uploaded' => $this->fmt_date_ddmmyyyy($this->meta_get_compat($cid,'kvt_cv_uploaded',['cv_uploaded'])),
                    'tags'        => $this->meta_get_compat($cid,'kvt_tags',['tags']),
                ];
                $items[] = [
                    'id'      => $cid,
                    'meta'    => $meta,
                    'summary' => $res['summary'],
                    'score'   => $res['score'],
                ];
            }
        }

        usort($items, function($a, $b){ return $b['score'] <=> $a['score']; });
        // Keep only candidates with a score of 7 or higher (scale 0-10)
        $items = array_values(array_filter($items, function($it){ return $it['score'] >= 7; }));

        wp_send_json_success(['items' => $items]);
    }

    public function ajax_keyword_search() {
        check_ajax_referer('kvt_nonce');

        $query = isset($_POST['query']) ? sanitize_text_field(wp_unslash($_POST['query'])) : '';
        if (!$query) wp_send_json_error(['msg' => 'Consulta vacía'], 400);
        $query = strtolower($query);

        $required = [];
        $optional = [];

        if (strpos($query, ' o ') !== false && strpos($query, ' y ') !== false) {
            $first_or = strpos($query, ' o ');
            $before_or = substr($query, 0, $first_or);
            $last_and = strrpos($before_or, ' y ');
            if ($last_and !== false) {
                $required_part = substr($query, 0, $last_and);
                $required = array_filter(array_map('trim', preg_split('/\s+y\s+/', $required_part)));
                $optional_part = substr($query, $last_and + 3);
                $optional = array_filter(array_map('trim', preg_split('/\s+o\s+/', $optional_part)));
            } else {
                $optional = array_filter(array_map('trim', preg_split('/\s+o\s+/', $query)));
            }
        } elseif (strpos($query, ' o ') !== false) {
            $optional = array_filter(array_map('trim', preg_split('/\s+o\s+/', $query)));
        } else {
            $required = array_filter(array_map('trim', preg_split('/\s+y\s+/', $query)));
        }

        $keywords = array_unique(array_merge($required, $optional));

        $country = isset($_POST['country']) ? sanitize_text_field(wp_unslash($_POST['country'])) : '';
        $args = ['post_type' => self::CPT, 'posts_per_page' => -1];
        if ($country) {
            $args['meta_query'] = [
                [
                    'key'   => 'kvt_country',
                    'value' => $country,
                ]
            ];
        }
        $candidates = get_posts($args);
        $items = [];
        foreach ($candidates as $c) {
            $cv_text = strtolower($this->get_candidate_cv_text($c->ID));
            if (!$cv_text) continue;

            $ok = true;
            foreach ($required as $tok) {
                if ($tok === '' || strpos($cv_text, $tok) === false) { $ok = false; break; }
            }
            if (!$ok) continue;

            if ($optional) {
                $match_any = false;
                foreach ($optional as $tok) {
                    if ($tok !== '' && strpos($cv_text, $tok) !== false) { $match_any = true; break; }
                }
                if (!$match_any) continue;
            }

            $meta = [
                'first_name'  => $this->meta_get_compat($c->ID,'kvt_first_name',['first_name']),
                'last_name'   => $this->meta_get_compat($c->ID,'kvt_last_name',['last_name']),
                'email'       => $this->meta_get_compat($c->ID,'kvt_email',['email']),
                'phone'       => $this->meta_get_compat($c->ID,'kvt_phone',['phone']),
                'country'     => $this->meta_get_compat($c->ID,'kvt_country',['country']),
                'city'        => $this->meta_get_compat($c->ID,'kvt_city',['city']),
                'cv_url'      => $this->meta_get_compat($c->ID,'kvt_cv_url',['cv_url']),
                'cv_uploaded' => $this->fmt_date_ddmmyyyy($this->meta_get_compat($c->ID,'kvt_cv_uploaded',['cv_uploaded'])),
                'tags'        => $this->meta_get_compat($c->ID,'kvt_tags',['tags']),
            ];
            $matches = [];
            foreach ($keywords as $kw) {
                if (strpos($cv_text, $kw) !== false) $matches[] = $kw;
            }
            $items[] = [
                'id'      => $c->ID,
                'meta'    => $meta,
                'matches' => $matches,
            ];
        }

        wp_send_json_success(['items' => $items]);
    }

    public function ajax_generate_share_link() {
        check_ajax_referer('kvt_nonce');

        $client  = isset($_POST['client'])  ? intval($_POST['client'])  : 0;
        $process = isset($_POST['process']) ? intval($_POST['process']) : 0;
        $page    = isset($_POST['page'])    ? sanitize_text_field(wp_unslash($_POST['page'])) : '';
        $fields  = isset($_POST['fields'])  ? array_map('sanitize_text_field', (array) $_POST['fields']) : [];
        $steps   = isset($_POST['steps'])   ? array_map('sanitize_text_field', (array) $_POST['steps']) : [];
        $allow_comments = !empty($_POST['comments']);
        $slug   = isset($_POST['slug'])     ? sanitize_text_field(wp_unslash($_POST['slug'])) : '';
        $candidate = isset($_POST['candidate']) ? intval($_POST['candidate']) : 0;

        if (!$client || !$process) {
            wp_send_json_error(['msg' => 'missing'], 400);
        }

        $option_key = $candidate ? 'kvt_candidate_links' : 'kvt_client_links';
        $links = get_option($option_key, []);
        if ($slug && isset($links[$slug])) {
            $links[$slug] = [
                'client'  => $client,
                'process' => $process,
                'fields'  => $fields,
                'steps'   => $steps,
                'page'    => $page,
                'comments'=> $allow_comments ? 1 : 0,
            ];
            if ($candidate) $links[$slug]['candidate'] = $candidate;
        } else {
            $client_term  = get_term($client, self::TAX_CLIENT);
            $process_term = get_term($process, self::TAX_PROCESS);
            $cslug = $client_term ? sanitize_title($client_term->name) : 'cliente';
            $pslug = $process_term ? sanitize_title($process_term->name) : 'proceso';
            $cand_slug = '';
            if ($candidate) {
                $cand_post = get_post($candidate);
                $cand_slug = $cand_post ? '-' . sanitize_title($cand_post->post_title) : '';
            }
            $rand  = wp_rand(10000, 99999);
            $slug  = $cslug . '-' . $pslug . $cand_slug . '-' . $rand;
            $links[$slug] = [
                'client'  => $client,
                'process' => $process,
                'fields'  => $fields,
                'steps'   => $steps,
                'page'    => $page,
                'comments'=> $allow_comments ? 1 : 0,
            ];
            if ($candidate) $links[$slug]['candidate'] = $candidate;
        }
        update_option($option_key, $links, false);

        wp_send_json_success(['slug' => $slug]);
    }

    public function ajax_delete_board() {
        check_ajax_referer('kvt_nonce');
        $type = isset($_POST['type']) ? sanitize_text_field(wp_unslash($_POST['type'])) : '';
        $slug = isset($_POST['slug']) ? sanitize_text_field(wp_unslash($_POST['slug'])) : '';
        if (!$type || !$slug) {
            wp_send_json_error();
        }
        $option = $type === 'candidate' ? 'kvt_candidate_links' : 'kvt_client_links';
        $links  = get_option($option, []);
        if (isset($links[$slug])) {
            unset($links[$slug]);
            update_option($option, $links, false);
            wp_send_json_success();
        }
        wp_send_json_error();
    }

    public function ajax_client_comment() {
        check_ajax_referer('kvt_nonce');

        $id   = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $slug = isset($_POST['slug']) ? sanitize_text_field(wp_unslash($_POST['slug'])) : '';
        $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        $comment = isset($_POST['comment']) ? sanitize_textarea_field(wp_unslash($_POST['comment'])) : '';
        if (!$id || !$slug || $name === '' || $comment === '') {
            wp_send_json_error(['msg'=>'missing'],400);
        }
        $links = get_option('kvt_client_links', []);
        if (!isset($links[$slug])) {
            wp_send_json_error(['msg'=>'invalid'],403);
        }
        $existing = get_post_meta($id, 'kvt_client_comments', true);
        if (!is_array($existing)) $existing = [];
        $existing[] = [
            'name'    => $name,
            'comment' => $comment,
            'date'    => current_time('mysql'),
            'slug'    => $slug,
            'source'  => 'client',
        ];
        update_post_meta($id, 'kvt_client_comments', $existing);
        wp_send_json_success(['name'=>$name,'comment'=>$comment]);
    }

    public function maybe_redirect_share_link() {
        if (isset($_GET['kvt_board'])) return;
        $req  = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
        if ($req === '') return;
        $parts = explode('/', $req);
        $slug  = end($parts);
        if (!preg_match('/^[a-z0-9-]+(?:-[a-z0-9-]+)*-\d{5}$/i', $slug)) return;
        $links = get_option('kvt_client_links', []);
        if (isset($links[$slug])) {
            $target = home_url('/base/?kvt_board=' . $slug);
            wp_redirect($target);
            exit;
        }
        $cand_links = get_option('kvt_candidate_links', []);
        if (isset($cand_links[$slug])) {
            $target = home_url('/base/?kvt_board=' . $slug);
            wp_redirect($target);
            exit;
        }
    }

    private function get_candidate_cv_text($post_id) {
        // Use cached text if available (e.g. from client-side extraction)
        $cached = get_post_meta($post_id, 'kvt_cv_text', true);
        if (is_string($cached) && trim($cached) !== '') {
            return $cached;
        }

        $cached_url = get_post_meta($post_id, 'kvt_cv_text_url', true);
        if ($cached_url) {
            $path = wp_parse_url($cached_url, PHP_URL_PATH);
            if ($path) {
                $full = ABSPATH . ltrim($path, '/');
                if (file_exists($full)) {
                    $text = $this->extract_text_from_file($full);
                    if ($text) {
                        update_post_meta($post_id, 'kvt_cv_text', $text);
                        return $text;
                    }
                }
            }
        }

        $cv_url = $this->meta_get_compat($post_id, 'kvt_cv_url', ['cv_url']);
        if (!$cv_url) return '';

        $response = wp_remote_get($cv_url);
        if (is_wp_error($response)) return '';
        $body = wp_remote_retrieve_body($response);
        $file = wp_tempnam($cv_url);
        if (!$file) return '';
        file_put_contents($file, $body);

        $text = $this->extract_text_from_file($file);
        @unlink($file);

        if ($text) update_post_meta($post_id, 'kvt_cv_text', $text);
        return $text;
    }

    private function extract_text_from_file($file) {
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $text = '';
        if ($ext === 'pdf') {
            if (function_exists('shell_exec')) {
                $text = shell_exec('pdftotext ' . escapeshellarg($file) . ' -');
                if (!trim($text)) {
                    $img_base = $file . '-ocr';
                    @shell_exec('pdftoppm ' . escapeshellarg($file) . ' ' . escapeshellarg($img_base));
                    $i = 1;
                    while (file_exists($img_base . '-' . $i . '.ppm')) {
                        $ocr = shell_exec('tesseract ' . escapeshellarg($img_base . '-' . $i . '.ppm') . ' stdout');
                        if ($ocr) $text .= $ocr . "\n";
                        @unlink($img_base . '-' . $i . '.ppm');
                        $i++;
                    }
                }
            }
        } elseif ($ext === 'docx') {
            if (class_exists('ZipArchive')) {
                $zip = new \ZipArchive();
                if ($zip->open($file) === true) {
                    $xml = $zip->getFromName('word/document.xml');
                    if ($xml) $text = strip_tags($xml);
                    $zip->close();
                }
            }
        } else {
            $text = @file_get_contents($file);
        }
        return wp_strip_all_tags($text);
    }

    private function save_cv_text_attachment($post_id, $attach_id) {
        $path = get_attached_file($attach_id);
        if (!$path) return;
        $text = $this->extract_text_from_file($path);
        if (!$text) return;
        update_post_meta($post_id, 'kvt_cv_text', $text);

        $info = pathinfo($path);
        if (strtolower($info['extension'] ?? '') !== 'pdf') return;
        // Create a .docx file with plain text for reference
        $docx_path = $info['dirname'] . '/' . $info['filename'] . '.docx';
        @unlink($docx_path);
        if (class_exists('ZipArchive')) {
            $zip = new \ZipArchive();
            if ($zip->open($docx_path, \ZipArchive::CREATE) === true) {
                $zip->addFromString('[Content_Types].xml',
                    '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                    .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
                    .'<Default Extension="xml" ContentType="application/xml"/>'
                    .'<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
                    .'</Types>');
                $zip->addFromString('_rels/.rels',
                    '<?xml version="1.0" encoding="UTF-8"?>'
                    .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                    .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
                    .'</Relationships>');
                $body = '';
                foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
                    $body .= '<w:p><w:r><w:t>'.htmlspecialchars($line, ENT_XML1).'</w:t></w:r></w:p>';
                }
                $doc = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                    .'<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'
                    .$body.'</w:body></w:document>';
                $zip->addFromString('word/document.xml', $doc);
                $zip->close();

                $upload = wp_upload_dir();
                $docx_url = str_replace($upload['basedir'], $upload['baseurl'], $docx_path);
                update_post_meta($post_id, 'kvt_cv_text_url', esc_url_raw($docx_url));
            }
        }
    }

    private function openai_extract_current_role($key, $cv_text) {
        $req = [
            'model' => 'gpt-4o-mini',
            'messages' => [
                ['role' => 'system', 'content' => 'Eres un asistente que extrae del CV el puesto y la empresa actuales. Devuelve JSON con las claves "role" y "company". Si no se encuentra, devuelve campos vacíos.'],
                ['role' => 'user', 'content' => "CV:\n$cv_text"],
            ],
            'max_tokens' => 100,
            'response_format' => ['type' => 'json_object'],
        ];
        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $key,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode($req),
            'timeout' => 60,
        ]);
        if (is_wp_error($response)) return '';
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!isset($body['choices'][0]['message']['content'])) return '';
        $data = json_decode($body['choices'][0]['message']['content'], true);
        if (!is_array($data)) return '';
        $role = isset($data['role']) ? trim($data['role']) : '';
        $company = isset($data['company']) ? trim($data['company']) : '';
        if ($role && $company) return $role . ' at ' . $company;
        if ($role) return $role;
        if ($company) return $company;
        return '';
    }

    private function update_current_role_from_cv($post_id, $key = null) {
        if (!$key) $key = get_option(self::OPT_OPENAI_KEY, '');
        if (!$key) return '';
        $cv_text = $this->get_candidate_cv_text($post_id);
        if (!$cv_text) return '';
        $role = $this->openai_extract_current_role($key, $cv_text);
        if ($role) {
            update_post_meta($post_id, 'kvt_current_role', $role);
            update_post_meta($post_id, 'current_role', $role);
        }
        return $role;
    }

    private function openai_extract_profile_fields($key, $cv_text) {
        $req = [
            'model' => 'gpt-4o-mini',
            'messages' => [
                ['role' => 'system', 'content' => 'Eres un asistente que extrae del CV nombre, apellidos, email, teléfono, país actual, ciudad actual, puesto y empresa actuales. Devuelve JSON con las claves "first_name","last_name","email","phone","country","city","role","company" y "current_role" (puesto + empresa). Si falta algún dato devuelve campo vacío.'],
                ['role' => 'user', 'content' => "CV:\n$cv_text"],
            ],
            'max_tokens' => 300,
            'response_format' => ['type' => 'json_object'],
        ];
        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $key,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode($req),
            'timeout' => 60,
        ]);
        if (is_wp_error($response)) return [];
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!isset($body['choices'][0]['message']['content'])) return [];
        $data = json_decode($body['choices'][0]['message']['content'], true);
        return is_array($data) ? $data : [];
    }

    private function update_profile_from_cv($post_id, $key = null) {
        if (!$key) $key = get_option(self::OPT_OPENAI_KEY, '');
        if (!$key) return [];
        $cv_text = $this->get_candidate_cv_text($post_id);
        if (!$cv_text) return [];
        $data = $this->openai_extract_profile_fields($key, $cv_text);
        if (!is_array($data)) return [];

        $updated = [];
        $map = [
            'first_name' => 'kvt_first_name',
            'last_name'  => 'kvt_last_name',
            'email'      => 'kvt_email',
            'phone'      => 'kvt_phone',
            'country'    => 'kvt_country',
            'city'       => 'kvt_city',
        ];
        foreach ($map as $field => $meta) {
            $val = isset($data[$field]) ? trim($data[$field]) : '';
            if ($val === '') continue;
            if (in_array($field, ['first_name','last_name'], true)) {
                $val = $this->normalize_name($val);
            }
            if (trim($this->meta_get_compat($post_id, $meta, [substr($meta,4)])) === '') {
                update_post_meta($post_id, $meta, $val);
                update_post_meta($post_id, substr($meta,4), $val);
                $updated[$field] = $val;
            }
        }
        $role     = isset($data['role']) ? trim($data['role']) : '';
        $company  = isset($data['company']) ? trim($data['company']) : '';
        $current  = isset($data['current_role']) ? trim($data['current_role']) : '';
        $role_combined = $current;
        if ($role_combined === '') {
            if ($role && $company) $role_combined = $role . ' at ' . $company;
            elseif ($role) $role_combined = $role;
            elseif ($company) $role_combined = $company;
        }
        if ($role && trim($this->meta_get_compat($post_id, 'kvt_role', ['role'])) === '') {
            update_post_meta($post_id, 'kvt_role', $role);
            update_post_meta($post_id, 'role', $role);
            $updated['role'] = $role;
        }
        if ($company && trim($this->meta_get_compat($post_id, 'kvt_company', ['company'])) === '') {
            update_post_meta($post_id, 'kvt_company', $company);
            update_post_meta($post_id, 'company', $company);
            $updated['company'] = $company;
        }
        if ($role_combined && trim($this->meta_get_compat($post_id, 'kvt_current_role', ['current_role'])) === '') {
            update_post_meta($post_id, 'kvt_current_role', $role_combined);
            update_post_meta($post_id, 'current_role', $role_combined);
            $updated['current_role'] = $role_combined;
        }
        return $updated;
    }

    private function openai_match_summary($key, $desc, $cv_text) {
        $req = [
            'model' => 'gpt-4o-mini',
            'messages' => [
                ['role' => 'system', 'content' => 'Eres un asistente de reclutamiento. Devuelve JSON con "score" (0-10) y "summary" (breve explicación en español) indicando por qué el candidato encaja. Si la descripción menciona una ubicación del trabajo, es importante que el candidato esté en el mismo país, a menos que se indique lo contrario.'],
                ['role' => 'user', 'content' => "Descripción del trabajo:\n$desc\nCV del candidato:\n$cv_text"],
            ],
            'max_tokens' => 150,
            'response_format' => ['type' => 'json_object'],
        ];
        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $key,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode($req),
            'timeout' => 60,
        ]);
        if (is_wp_error($response)) return null;
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!isset($body['choices'][0]['message']['content'])) return null;
        $data = json_decode($body['choices'][0]['message']['content'], true);
        if (!is_array($data) || !isset($data['score'])) return null;
        return [
            'score'   => floatval($data['score']),
            'summary' => isset($data['summary']) ? trim($data['summary']) : '',
        ];
    }

      public function ajax_assign_candidate() {
          check_ajax_referer('kvt_nonce');

          $id        = isset($_POST['candidate_id']) ? intval($_POST['candidate_id']) : 0;
          $client_id = isset($_POST['client_id']) ? intval($_POST['client_id']) : 0;
          $process_id= isset($_POST['process_id']) ? intval($_POST['process_id']) : 0;

          if (!$id || get_post_type($id) !== self::CPT) {
              wp_send_json_error(['msg'=>'Invalid candidate'],400);
          }
          if ($process_id && has_term($process_id, self::TAX_PROCESS, $id)) {
              wp_send_json_error(['msg'=>'Ya asignado'],400);
          }
          if ($client_id) wp_set_object_terms($id, [$client_id], self::TAX_CLIENT, true);
          if ($process_id) wp_set_object_terms($id, [$process_id], self::TAX_PROCESS, true);

          $log = get_post_meta($id, 'kvt_activity_log', true);
          if(!is_array($log)) $log = [];
          if ($process_id) {
              $pterm = get_term($process_id, self::TAX_PROCESS);
              $pname = ($pterm && !is_wp_error($pterm)) ? $pterm->name : '';
              $u = wp_get_current_user();
              $author = ($u && $u->exists()) ? $u->display_name : '';
              $log[] = [
                  'type'   => 'assign',
                  'process'=> $pname,
                  'author' => $author,
                  'time'   => current_time('mysql'),
              ];
              update_post_meta($id, 'kvt_activity_log', $log);
          }

          wp_send_json_success(['id'=>$id]);
      }

        public function ajax_unassign_candidate() {
            check_ajax_referer('kvt_nonce');

          $id        = isset($_POST['id']) ? intval($_POST['id']) : 0;
          $client_id = isset($_POST['client_id']) ? intval($_POST['client_id']) : 0;
          $process_id= isset($_POST['process_id']) ? intval($_POST['process_id']) : 0;

          if (!$id || get_post_type($id) !== self::CPT) {
              wp_send_json_error(['msg'=>'Invalid candidate'],400);
          }
          if ($client_id) wp_remove_object_terms($id, [$client_id], self::TAX_CLIENT);
          if ($process_id) wp_remove_object_terms($id, [$process_id], self::TAX_PROCESS);

          $log = get_post_meta($id, 'kvt_activity_log', true);
          if(!is_array($log)) $log = [];
          if ($process_id) {
              $pterm = get_term($process_id, self::TAX_PROCESS);
              $pname = ($pterm && !is_wp_error($pterm)) ? $pterm->name : '';
              $u = wp_get_current_user();
              $author = ($u && $u->exists()) ? $u->display_name : '';
              $log[] = [
                  'type'   => 'unassign',
                  'process'=> $pname,
                  'author' => $author,
                  'time'   => current_time('mysql'),
              ];
              update_post_meta($id, 'kvt_activity_log', $log);
          }

            wp_send_json_success(['id'=>$id]);
        }

        public function ajax_generate_email() {
            check_ajax_referer('kvt_nonce');
            if (!current_user_can('edit_posts')) wp_send_json_error(['error' => 'Unauthorized'], 403);

            $prompt = isset($_POST['prompt']) ? wp_unslash($_POST['prompt']) : '';
            if (!$prompt) wp_send_json_error(['error' => 'Missing prompt'], 400);

            $api_key = get_option(self::OPT_OPENAI_KEY, '');
            $model   = 'gpt-4o-mini';

            $fallback = [
                'subject'   => '{{first_name}}, nota rápida para el proceso {{role}} en {{city}}',
                'body_html' => 'Hola {{first_name}},<br><br>' . esc_html($prompt) . '<br><br>Saludos,<br>{{sender}}',
            ];

            if (!$api_key) {
                wp_send_json_success(['subject_template' => $fallback['subject'], 'body_template' => $fallback['body_html']]);
            }

            $sys = "Eres un redactor de emails de Kovacic Executive Talent Research. Devuelve un JSON con las claves 'subject' y 'body_html'. "
                 . "Debes usar SIEMPRE estos placeholders cuando corresponda y NO inventar otros: "
                 . "Nombre = {{first_name}}, Apellido = {{surname}}, País = {{country}}, Ciudad = {{city}}, Cliente = {{client}}, Proceso = {{role}}, Estado = {{status}}, Tablero = {{board}}, El remitente = {{sender}}. "
                 . "El cuerpo debe ser HTML y usar saltos de línea <br> (NO uses etiquetas <p>). Y Nunca uses '—'.";

            $req = [
                'model' => $model,
                'temperature' => 0.7,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => $sys],
                    ['role' => 'user', 'content' => $prompt],
                ],
            ];

            $resp = wp_remote_post('https://api.openai.com/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type'  => 'application/json',
                ],
                'body' => wp_json_encode($req),
                'timeout' => 30,
            ]);

            if (is_wp_error($resp)) {
                wp_send_json_success(['subject_template' => $fallback['subject'], 'body_template' => $fallback['body_html']]);
            }

            $code = wp_remote_retrieve_response_code($resp);
            $body = wp_remote_retrieve_body($resp);
            $json = json_decode($body, true);

            if ($code !== 200 || empty($json['choices'][0]['message']['content'])) {
                wp_send_json_success(['subject_template' => $fallback['subject'], 'body_template' => $fallback['body_html']]);
            }

            $content = json_decode($json['choices'][0]['message']['content'], true);
            $subject = isset($content['subject']) ? (string) $content['subject'] : $fallback['subject'];
            $html    = isset($content['body_html']) ? (string) $content['body_html'] : $fallback['body_html'];

            wp_send_json_success(['subject_template' => $subject, 'body_template' => $html]);
        }

        public function apply_smtp_settings($phpmailer) {
            $host = get_option(self::OPT_SMTP_HOST, '');
            if (!$host) return;
            $phpmailer->isSMTP();
            $phpmailer->Host = $host;
            $port = intval(get_option(self::OPT_SMTP_PORT));
            if ($port) $phpmailer->Port = $port;
            $secure = get_option(self::OPT_SMTP_SECURE, '');
            if ($secure && $secure !== 'none') $phpmailer->SMTPSecure = $secure;
            $user = get_option(self::OPT_SMTP_USER, '');
            $pass = get_option(self::OPT_SMTP_PASS, '');
            if ($user || $pass) {
                $phpmailer->SMTPAuth = true;
                $phpmailer->Username = $user;
                $phpmailer->Password = $pass;
            }
        }

        private function get_email_templates() {
            $posts = get_posts([
                'post_type'      => self::CPT_EMAIL_TEMPLATE,
                'post_status'    => 'publish',
                'numberposts'    => -1,
                'orderby'        => 'title',
                'order'          => 'ASC',
            ]);

            if (empty($posts)) {
                $legacy = get_option('kvt_email_templates', []);
                if (!is_array($legacy)) {
                    $decoded = json_decode($legacy, true);
                    $legacy = is_array($decoded) ? $decoded : [];
                }
                foreach ($legacy as $tpl) {
                    $id = wp_insert_post([
                        'post_type'   => self::CPT_EMAIL_TEMPLATE,
                        'post_title'  => $tpl['title'] ?? '',
                        'post_status' => 'publish',
                    ], true);
                    if (!is_wp_error($id)) {
                        update_post_meta($id, '_kvt_subject', $tpl['subject'] ?? '');
                        update_post_meta($id, '_kvt_body', $tpl['body'] ?? '');
                    }
                }
                if ($legacy) {
                    delete_option('kvt_email_templates');
                    $posts = get_posts([
                        'post_type'      => self::CPT_EMAIL_TEMPLATE,
                        'post_status'    => 'publish',
                        'numberposts'    => -1,
                        'orderby'        => 'title',
                        'order'          => 'ASC',
                    ]);
                }
            }

            $templates = [];
            foreach ($posts as $p) {
                $templates[] = [
                    'id'      => $p->ID,
                    'title'   => $p->post_title,
                    'subject' => get_post_meta($p->ID, '_kvt_subject', true),
                    'body'    => get_post_meta($p->ID, '_kvt_body', true),
                ];
            }
            return $templates;
        }

        public function ajax_save_template() {
            check_ajax_referer('kvt_nonce');
            if (!current_user_can('edit_posts')) wp_send_json_error(['msg' => 'Unauthorized'], 403);
            $title   = sanitize_text_field($_POST['title'] ?? '');
            $subject = wp_kses_post($_POST['subject'] ?? '');
            $body    = wp_kses_post($_POST['body'] ?? '');
            if (!$title) wp_send_json_error(['msg' => 'Missing title'], 400);
            $id = intval($_POST['id'] ?? 0);
            if ($id) {
                wp_update_post([
                    'ID'         => $id,
                    'post_title' => $title,
                ]);
            } else {
                $id = wp_insert_post([
                    'post_type'   => self::CPT_EMAIL_TEMPLATE,
                    'post_title'  => $title,
                    'post_status' => 'publish',
                ], true);
                if (is_wp_error($id)) wp_send_json_error(['msg' => 'Error saving'], 500);
            }
            update_post_meta($id, '_kvt_subject', $subject);
            update_post_meta($id, '_kvt_body', $body);
            $templates = $this->get_email_templates();
            wp_send_json_success(['templates' => $templates]);
        }

        public function ajax_delete_template() {
            check_ajax_referer('kvt_nonce');
            if (!current_user_can('edit_posts')) wp_send_json_error(['msg' => 'Unauthorized'], 403);
            $id = intval($_POST['id'] ?? 0);
            if ($id) wp_delete_post($id, true);
            $templates = $this->get_email_templates();
            wp_send_json_success(['templates' => $templates]);
        }

        public function ajax_refresh_all() {
            check_ajax_referer('kvt_nonce');
            if (!current_user_can('edit_posts')) wp_send_json_error(['msg' => 'Unauthorized'], 403);
            $ids = get_posts(['post_type'=>self::CPT,'post_status'=>'any','fields'=>'ids','posts_per_page'=>-1]);
            update_option(self::OPT_REFRESH_QUEUE, array_map('intval', $ids));
            if (!wp_next_scheduled('kvt_refresh_worker')) {
                wp_schedule_single_event(time()+5, 'kvt_refresh_worker');
            }
            wp_send_json_success(['count'=>count($ids)]);
        }

        public function cron_refresh_worker() {
            $queue = get_option(self::OPT_REFRESH_QUEUE, []);
            if (empty($queue)) return;
            $key = get_option(self::OPT_OPENAI_KEY, '');
            if (!$key) return;
            $id = array_shift($queue);
            update_option(self::OPT_REFRESH_QUEUE, $queue);
            if ($id) {
                $this->update_profile_from_cv($id, $key);
                sleep(2);
            }
            if (!empty($queue)) {
                wp_schedule_single_event(time()+5, 'kvt_refresh_worker');
            }
        }

        public function ajax_get_email_log() {
            check_ajax_referer('kvt_nonce');
            if (!current_user_can('edit_posts')) wp_send_json_error(['msg' => 'Unauthorized'], 403);

            $log = array_reverse((array) get_option(self::OPT_EMAIL_LOG, []));
            wp_send_json_success(['log' => $log]);
        }

        public function ajax_send_email() {
            check_ajax_referer('kvt_nonce');
            if (!current_user_can('edit_posts')) wp_send_json_error(['msg' => 'Unauthorized'], 403);

            $payload_json = isset($_POST['payload']) ? wp_unslash($_POST['payload']) : '';
            if (!$payload_json) wp_send_json_error(['error' => 'Missing payload'], 400);
            $payload = json_decode($payload_json, true);
            if (!is_array($payload)) wp_send_json_error(['error' => 'Invalid JSON'], 400);
            $subject_tpl   = (string)($payload['subject_template'] ?? '');
            $body_tpl      = (string)($payload['body_template'] ?? '');
            $recipients    = (array)($payload['recipients'] ?? []);
            $from_email    = sanitize_email($payload['from_email'] ?? '');
            $from_name     = sanitize_text_field($payload['from_name'] ?? '');
            $use_signature = !empty($payload['use_signature']);

            $batch = compact('subject_tpl','body_tpl','recipients','from_email','from_name','use_signature');
            wp_schedule_single_event(time(), 'kvt_send_email_batch', [$batch]);

            $log = get_option(self::OPT_EMAIL_LOG, []);
            $log[] = [
                'time' => current_time('mysql'),
                'subject' => $subject_tpl,
                'recipients' => array_map(function($r){ return $r['email'] ?? ''; }, $recipients)
            ];
            if (count($log) > 100) $log = array_slice($log, -100);
            update_option(self::OPT_EMAIL_LOG, $log);

            wp_send_json_success(['sent' => count($recipients), 'errors' => [], 'log' => array_reverse($log)]);
        }

        public function cron_send_email_batch($batch) {
            if (!is_array($batch)) return;

            $subject_tpl   = (string)($batch['subject_tpl'] ?? '');
            $body_tpl      = (string)($batch['body_tpl'] ?? '');
            $recipients    = (array)($batch['recipients'] ?? []);
            $from_email    = sanitize_email($batch['from_email'] ?? '');
            $from_name     = sanitize_text_field($batch['from_name'] ?? '');
            $use_signature = !empty($batch['use_signature']);

            if (!$from_email) $from_email = get_option(self::OPT_FROM_EMAIL, '');
            if (!$from_email) $from_email = get_option('admin_email');
            if (!$from_name)  $from_name  = get_option(self::OPT_FROM_NAME, '');
            if (!$from_name)  $from_name  = get_bloginfo('name');

            $from_cb = null;
            $from_name_cb = null;
            if ($from_email) {
                $from_cb = function() use ($from_email){ return $from_email; };
                add_filter('wp_mail_from', $from_cb, 99);
            }
            if ($from_name) {
                $from_name_cb = function() use ($from_name){ return $from_name; };
                add_filter('wp_mail_from_name', $from_name_cb, 99);
            }

            $signature = (string) get_option(self::OPT_SMTP_SIGNATURE, '');

            foreach ($recipients as $r) {
                $email      = isset($r['email']) ? sanitize_email($r['email']) : '';
                $first_name = isset($r['first_name']) ? sanitize_text_field($r['first_name']) : '';
                $surname    = isset($r['surname']) ? sanitize_text_field($r['surname']) : '';
                $country    = isset($r['country']) ? sanitize_text_field($r['country']) : '';
                $city       = isset($r['city']) ? sanitize_text_field($r['city']) : '';
                $role       = isset($r['role']) ? sanitize_text_field($r['role']) : '';
                $status     = isset($r['status']) ? sanitize_text_field($r['status']) : '';
                $client     = isset($r['client']) ? sanitize_text_field($r['client']) : '';
                $board      = isset($r['board']) ? esc_url_raw($r['board']) : '';
                if (!$email) continue;

                $data = compact('first_name','surname','country','city','role','board','status','client');
                $data['sender'] = $from_name ?: $from_email ?: get_bloginfo('name');
                $subject = $this->render_template($subject_tpl, $data);
                $body_raw = $this->render_template($body_tpl, $data);
                $body = $this->normalize_br_html($body_raw);
                if ($signature && $use_signature) {
                    $body .= '<br><br>' . $this->normalize_br_html($signature);
                }

                $headers = ['Content-Type: text/html; charset=UTF-8'];
                if ($from_email) $headers[] = 'Reply-To: '.$from_name.' <'.$from_email.'>';

                wp_mail($email, $subject, $body, $headers);
                usleep(250000);
            }

            if ($from_cb) remove_filter('wp_mail_from', $from_cb, 99);
            if ($from_name_cb) remove_filter('wp_mail_from_name', $from_name_cb, 99);
        }

        private function normalize_br_html($html) {
            $out = (string)$html;
            $out = preg_replace('~</p>\s*<p>~i', '<br><br>', $out);
            $out = preg_replace('~</?p[^>]*>~i', '', $out);
            $out = preg_replace("/\r\n|\r/", "\n", $out);
            $out = preg_replace("/\n{2,}/", "<br><br>", $out);
            $out = str_replace("\n", "<br>", $out);
            return $out;
        }

        private function render_template($tpl, $data) {
            return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', function($m) use ($data) {
                $k = $m[1];
                return isset($data[$k]) ? esc_html($data[$k]) : $m[0];
            }, $tpl);
        }

      /* Export */
      public function handle_export() {
        if (!is_user_logged_in() || !current_user_can('edit_posts')) wp_die('Unauthorized');
        check_admin_referer('kvt_export','kvt_export_nonce');

        $format     = isset($_POST['format']) ? sanitize_text_field($_POST['format']) : 'csv';
        $client_id  = isset($_POST['filter_client'])  ? intval($_POST['filter_client'])  : 0;
        $process_id = isset($_POST['filter_process']) ? intval($_POST['filter_process']) : 0;
        $search     = isset($_POST['filter_search'])  ? sanitize_text_field($_POST['filter_search']) : '';

        $tax_query = [];
        if ($process_id) {
            $tax_query[] = ['taxonomy'=>self::TAX_PROCESS,'field'=>'term_id','terms'=>[$process_id]];
            if ($client_id) $tax_query[] = ['taxonomy'=>self::TAX_CLIENT,'field'=>'term_id','terms'=>[$client_id]];
        } else {
            if ($client_id) {
                $proc_terms = get_terms(['taxonomy'=>self::TAX_PROCESS,'hide_empty'=>false]);
                $proc_ids = [];
                foreach ($proc_terms as $t) {
                    $cid = (int) get_term_meta($t->term_id, 'kvt_process_client', true);
                    if ($cid === $client_id) $proc_ids[] = $t->term_id;
                }
                if (!empty($proc_ids)) {
                    $tax_query = [
                        'relation' => 'OR',
                        ['taxonomy'=>self::TAX_CLIENT, 'field'=>'term_id','terms'=>[$client_id]],
                        ['taxonomy'=>self::TAX_PROCESS,'field'=>'term_id','terms'=>$proc_ids],
                    ];
                } else {
                    $tax_query[] = ['taxonomy'=>self::TAX_CLIENT,'field'=>'term_id','terms'=>[$client_id]];
                }
            }
        }

        $args = [
            'post_type'      => self::CPT,
            'post_status'    => 'any',
            'posts_per_page' => -1,
            's'              => $search,
        ];
        if (!empty($tax_query)) $args['tax_query'] = $tax_query;

        $q = new WP_Query($args);

        // Fixed order export
        $headers = ['email','first_name','surname','country','city','current_role','proceso','cliente','phone','cv_url','next_action','next_action_note'];
        $filename = 'pipeline_export_' . date('Ymd_His');

        if ($format === 'xls') {
            header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
            header('Content-Disposition: attachment; filename="'.$filename.'.xls"');
        } else {
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="'.$filename.'.csv"');
        }
        header('Pragma: no-cache'); header('Expires: 0');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, $headers);

        foreach ($q->posts as $p) {
            $email   = $this->meta_get_compat($p->ID,'kvt_email',['email']);
            $fname   = $this->meta_get_compat($p->ID,'kvt_first_name',['first_name']);
            $lname   = $this->meta_get_compat($p->ID,'kvt_last_name',['last_name']);
            $country = $this->meta_get_compat($p->ID,'kvt_country',['country']);
            $city    = $this->meta_get_compat($p->ID,'kvt_city',['city']);
            $current_role = $this->meta_get_compat($p->ID,'kvt_current_role',['current_role']);
            $proc    = $this->get_term_name($p->ID, self::TAX_PROCESS);
            $client  = $this->get_term_name($p->ID, self::TAX_CLIENT);
            $phone   = $this->meta_get_compat($p->ID,'kvt_phone',['phone']);
            $cv      = $this->meta_get_compat($p->ID,'kvt_cv_url',['cv_url']);
            $next    = $this->fmt_date_ddmmyyyy($this->meta_get_compat($p->ID,'kvt_next_action',['next_action']));
            $note    = $this->meta_get_compat($p->ID,'kvt_next_action_note',['next_action_note']);
            fputcsv($out, [$email,$fname,$lname,$country,$city,$current_role,$proc,$client,$phone,$cv,$next,$note]);
        }
        fclose($out);
        exit;
    }
}

register_activation_hook(__FILE__, ['Kovacic_Pipeline_Visualizer', 'activate']);
new Kovacic_Pipeline_Visualizer();
