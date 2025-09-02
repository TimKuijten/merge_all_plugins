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

    public function __construct() {
        add_action('init',                       [$this, 'register_types']);
        add_action('admin_init',                 [$this, 'register_settings']);
        add_action('admin_menu',                 [$this, 'admin_menu']);
        add_action('admin_enqueue_scripts',      [$this, 'admin_assets']);

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
        add_action('wp_ajax_kvt_create_candidate',     [$this, 'ajax_create_candidate']);
        add_action('wp_ajax_nopriv_kvt_create_candidate',[$this, 'ajax_create_candidate']);
        add_action('wp_ajax_kvt_create_client',        [$this, 'ajax_create_client']);
        add_action('wp_ajax_nopriv_kvt_create_client', [$this, 'ajax_create_client']);
        add_action('wp_ajax_kvt_create_process',       [$this, 'ajax_create_process']);
        add_action('wp_ajax_nopriv_kvt_create_process',[$this, 'ajax_create_process']);
        add_action('wp_ajax_kvt_update_client',        [$this, 'ajax_update_client']);
        add_action('wp_ajax_nopriv_kvt_update_client', [$this, 'ajax_update_client']);
        add_action('wp_ajax_kvt_update_process',       [$this, 'ajax_update_process']);
        add_action('wp_ajax_nopriv_kvt_update_process',[$this, 'ajax_update_process']);
        add_action('wp_ajax_kvt_assign_candidate',     [$this, 'ajax_assign_candidate']);
        add_action('wp_ajax_nopriv_kvt_assign_candidate',[$this, 'ajax_assign_candidate']);
        add_action('wp_ajax_kvt_unassign_candidate',   [$this, 'ajax_unassign_candidate']);
        add_action('wp_ajax_nopriv_kvt_unassign_candidate',[$this, 'ajax_unassign_candidate']);
        add_action('wp_ajax_kvt_ai_search',            [$this, 'ajax_ai_search']);
        add_action('wp_ajax_nopriv_kvt_ai_search',     [$this, 'ajax_ai_search']);
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

        // Export
        add_action('admin_post_kvt_export',          [$this, 'handle_export']);

        // Follow-up reminders
        add_action('wp',                            [$this, 'schedule_followup_cron']);
        add_action('kvt_daily_followup',            [$this, 'cron_check_followups']);
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
current_role|Current role
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
    }
    public function admin_menu() {
        global $admin_page_hooks;
        if (!isset($admin_page_hooks['kovacic'])) {
            add_menu_page('Kovacic', 'Kovacic', 'manage_options', 'kovacic', '__return_null', 'dashicons-businessman', 3);
        }
        add_submenu_page('kovacic', __('ATS', 'kovacic'), __('ATS', 'kovacic'), 'manage_options', 'kvt-tracker', [$this, 'tracker_page']);
        add_submenu_page('kovacic', __('Ajustes', 'kovacic'), __('Ajustes', 'kovacic'), 'manage_options', 'kvt-settings', [$this, 'settings_page']);
    }

    public function tracker_page() {
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
              <button class="btn btn--ghost" id="k-add-filter"><?php esc_html_e('Crear nuevo filtro', 'kovacic'); ?></button>
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
              <div>
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
              </div>
              <aside class="k-sidebar" id="k-sidebar">
                <div class="k-sidehead"><?php esc_html_e('Actividad', 'kovacic'); ?></div>
                <div class="k-activity" id="k-activity-feed"></div>
                <div class="k-sideactions">
                  <button class="btn btn--primary" id="k-log-call"><?php esc_html_e('Registrar llamada', 'kovacic'); ?></button>
                  <button class="btn" id="k-new-event"><?php esc_html_e('Nuevo evento', 'kovacic'); ?></button>
                  <button class="btn" id="k-new-task"><?php esc_html_e('Nueva tarea', 'kovacic'); ?></button>
                </div>
              </aside>
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
.kcvf .k-layout{display:grid;grid-template-columns:1fr 300px;gap:calc(var(--gap)*2)}
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
  .kcvf .k-activity-toggle{display:block;margin-bottom:calc(var(--gap)*2)}
  .kcvf .k-sidebar{order:-1;display:none}
  .kcvf .k-sidebar.is-open{display:block}
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
    items.forEach(item=>{
      const tr=document.createElement('tr');
      const cb=document.createElement('td');
      cb.className='checkbox';
      cb.innerHTML='<input type="checkbox" class="k-rowcheck" value="'+item.id+'">';
      const name=document.createElement('td');
      name.innerHTML='<a href="#" class="k-candidate" data-id="'+item.id+'">'+item.meta.first_name+' '+item.meta.last_name+'</a>';
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
        $statuses = get_option(self::OPT_STATUSES, "");
        $columns  = get_option(self::OPT_COLUMNS, "");
        $openai   = get_option(self::OPT_OPENAI_KEY, "");
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
                        <th scope="row"><label for="<?php echo self::OPT_COLUMNS; ?>">Columnas de datos (tabla/exportación)</label></th>
                        <td>
                            <textarea name="<?php echo self::OPT_COLUMNS; ?>" id="<?php echo self::OPT_COLUMNS; ?>" rows="10" class="large-text" placeholder="meta_key|Etiqueta visible"><?php echo esc_textarea($columns); ?></textarea>
                            <p class="description">
                                Formato: <code>meta_key|Etiqueta</code> (una por línea). Por defecto: <code>first_name, last_name, email, phone, country, city, cv_url, cv_uploaded</code>
                            </p>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Guardar ajustes'); ?>
            </form>
        </div>
        <?php
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
        <?php
    }
    public function process_edit_fields($term) {
        $clients = get_terms(['taxonomy'=>self::TAX_CLIENT,'hide_empty'=>false]);
        $current = get_term_meta($term->term_id, 'kvt_process_client', true);
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
        <?php
    }
    public function client_edit_fields($term) {
        $cname  = get_term_meta($term->term_id, 'contact_name', true);
        $cemail = get_term_meta($term->term_id, 'contact_email', true);
        ?>
        <tr class="form-field">
            <th scope="row"><label for="kvt_client_contact_name">Persona de contacto</label></th>
            <td><input type="text" name="kvt_client_contact_name" id="kvt_client_contact_name" value="<?php echo esc_attr($cname); ?>"></td>
        </tr>
        <tr class="form-field">
            <th scope="row"><label for="kvt_client_contact_email">Email de contacto</label></th>
            <td><input type="email" name="kvt_client_contact_email" id="kvt_client_contact_email" value="<?php echo esc_attr($cemail); ?>"></td>
        </tr>
        <?php
    }
    public function save_client_term($term_id, $tt_id) {
        if (isset($_POST['kvt_client_contact_name'])) {
            update_term_meta($term_id, 'contact_name', sanitize_text_field($_POST['kvt_client_contact_name']));
        }
        if (isset($_POST['kvt_client_contact_email'])) {
            update_term_meta($term_id, 'contact_email', sanitize_email($_POST['kvt_client_contact_email']));
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

    private function meta_get_compat($post_id, $key, $fallbacks = []) {
        $v = get_post_meta($post_id, $key, true);
        if ($v !== '' && $v !== null) return $v;
        foreach ($fallbacks as $fb) {
            $vv = get_post_meta($post_id, $fb, true);
            if ($vv !== '' && $vv !== null) return $vv;
        }
        return '';
    }
    private function fmt_date_ddmmyyyy($val){
        $val = trim((string)$val);
        if ($val === '') return '';
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $val)) {
            $ts = strtotime(substr($val,0,10));
            return $ts ? date('d-m-Y',$ts) : $val;
        }
        if (preg_match('/^\d{2}-\d{2}-\d{4}$/', $val)) return $val;
        $ts = strtotime($val);
        return $ts ? date('d-m-Y',$ts) : $val;
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
        $public_notes = $this->meta_get_compat($post->ID, 'kvt_public_notes', ['public_notes']);
        ?>
        <table class="form-table">
            <tr><th><label>Nombre</label></th><td><input type="text" name="kvt_first_name" value="<?php echo esc_attr($first); ?>" class="regular-text"></td></tr>
            <tr><th><label>Apellidos</label></th><td><input type="text" name="kvt_last_name" value="<?php echo esc_attr($last); ?>" class="regular-text"></td></tr>
            <tr><th><label>Email</label></th><td><input type="email" name="kvt_email" value="<?php echo esc_attr($email); ?>" class="regular-text"></td></tr>
            <tr><th><label>Teléfono</label></th><td><input type="text" name="kvt_phone" value="<?php echo esc_attr($phone); ?>" class="regular-text"></td></tr>
            <tr><th><label>País</label></th><td><input type="text" name="kvt_country" value="<?php echo esc_attr($country); ?>" class="regular-text"></td></tr>
            <tr><th><label>Ciudad</label></th><td><input type="text" name="kvt_city" value="<?php echo esc_attr($city); ?>" class="regular-text"></td></tr>
            <tr><th><label>Current role</label></th><td><input type="text" name="kvt_current_role" value="<?php echo esc_attr($current_role); ?>" class="regular-text"></td></tr>

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
                    <p class="description">Al subir un CV, guardamos el enlace en “CV (URL)” y la fecha (DD-MM-YYYY) si está vacía.</p>
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

            <tr><th><label>Fecha de subida</label></th><td><input type="text" name="kvt_cv_uploaded" value="<?php echo esc_attr($cv_date); ?>" class="regular-text kvt-date" placeholder="DD-MM-YYYY"></td></tr>
            <tr><th><label>Próxima acción</label></th><td><input type="text" name="kvt_next_action" value="<?php echo esc_attr($next_action); ?>" class="regular-text kvt-date" placeholder="DD-MM-YYYY"></td></tr>
            <tr><th><label>Comentario próxima acción</label></th><td><input type="text" name="kvt_next_action_note" value="<?php echo esc_attr($next_note); ?>" class="regular-text"></td></tr>

            <tr><th><label>Notas</label></th>
                <td><textarea name="kvt_notes" rows="6" class="large-text" placeholder="Notas internas"><?php echo esc_textarea($notes); ?></textarea></td>
            </tr>
            <tr><th><label>Notas públicas</label></th>
                <td><textarea name="kvt_public_notes" rows="6" class="large-text" placeholder="Notas públicas"><?php echo esc_textarea($public_notes); ?></textarea></td>
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
            'kvt_public_notes' => ['public_notes'],
        ];
        foreach ($fields as $k => $fallbacks) {
            if ($k === 'kvt_cv_url' && $uploaded_url) continue;
            if ($k === 'kvt_cv_uploaded' && $uploaded_dt) continue;
            if (isset($_POST[$k])) {
                $val = ($k==='kvt_notes' || $k==='kvt_public_notes') ? wp_kses_post($_POST[$k])
                      : (($k==='kvt_email') ? sanitize_email($_POST[$k]) : sanitize_text_field($_POST[$k]));
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
                ['key'=>'current_role','label'=>'Current role'],
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
            if ($max >= 0 && isset($statuses[$max])) $job_stage = $statuses[$max];

            $out[] = [
                'id'          => $t->term_id,
                'name'        => $t->name,
                'client_id'   => $cid ?: 0,
                'client'      => $cid ? get_term($cid)->name : '',
                'description' => wp_strip_all_tags($t->description),
                'contact_name'  => get_term_meta($t->term_id, 'contact_name', true),
                'contact_email' => get_term_meta($t->term_id, 'contact_email', true),
                'creator'       => get_the_author_meta('display_name', (int) get_term_meta($t->term_id, 'kvt_process_creator', true)),
                'created'       => get_term_meta($t->term_id, 'kvt_process_created', true),
                'job_stage'     => $job_stage,
            ];
        }
        return $out;
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
        $links  = get_option('kvt_client_links', []);
        $is_client_board = $slug && isset($links[$slug]);
        if (!$is_client_board && (!is_user_logged_in() || !current_user_can('edit_posts'))) {
            return '<div class="kvt-wrapper"><p>Debes iniciar sesión para ver el pipeline.</p></div>';
        }
        $clients   = get_terms(['taxonomy'=>self::TAX_CLIENT, 'hide_empty'=>false]);
        $processes = get_terms(['taxonomy'=>self::TAX_PROCESS,'hide_empty'=>false]);
        $proc_map  = $this->get_process_map();
        $client_map = array_map(function($t){
            return [
                'id'            => $t->term_id,
                'name'          => $t->name,
                'contact_name'  => get_term_meta($t->term_id, 'contact_name', true),
                'contact_email' => get_term_meta($t->term_id, 'contact_email', true),
                'contact_phone' => get_term_meta($t->term_id, 'contact_phone', true),
                'description'   => wp_strip_all_tags($t->description),
            ];
        }, $clients);
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
            <?php if ($is_client_board): ?>
            <img src="https://kovacictalent.com/wp-content/uploads/2025/08/Logo_Kovacic.png" alt="Kovacic Talent" class="kvt-logo">
            <?php endif; ?>
            <span class="dashicons dashicons-editor-help kvt-help" title="Haz clic para ver cómo funciona el tablero"></span>
            <div class="kvt-header">
                <h2 class="kvt-board-title">Tablero ATS</h2>
                <nav class="kvt-nav" aria-label="Navegación principal">
                    <a href="#" class="active" data-view="detalles">Detalles</a>
                    <a href="#" data-view="ats">ATS</a>
                    <a href="#" data-view="calendario">Calendario</a>
                    <span class="kvt-nav-spacer"></span>
                    <a href="#" id="kvt_add_profile">Base</a>
                    <a href="#" id="kvt_toggle_table">Tabla</a>
                    <a href="#" id="kvt_mandar_correos">Correos</a>
                    <a href="#" id="kvt_share_board">Tablero Cliente</a>
                    <a href="#" id="kvt_open_processes">Procesos</a>
                    <a href="#" id="kvt_nav_export">Exportar</a>
                    <a href="#">Nuevo filtro</a>
                </nav>
            </div>
            <div id="kvt_filters_bar" class="kvt-filters" style="display:none;">
                <label>Cliente
                    <select id="kvt_client">
                        <option value="">— Todos —</option>
                        <?php foreach ($clients as $c): ?>
                          <option value="<?php echo esc_attr($c->term_id); ?>"><?php echo esc_html($c->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Proceso
                    <select id="kvt_process">
                        <option value="">— Todos —</option>
                        <?php foreach ($processes as $t): ?>
                          <option value="<?php echo esc_attr($t->term_id); ?>"><?php echo esc_html($t->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <span id="kvt_client_link" class="kvt-client-link"></span>
            </div>

            <?php if (!$is_client_board): ?>
            <div id="kvt_selected_info" style="display:none;">
              <p id="kvt_selected_client"></p>
              <p id="kvt_selected_process"></p>
              <p id="kvt_selected_board"></p>
            </div>
            <?php endif; ?>

            <div class="kvt-main">
                <div id="kvt_table_wrap" class="kvt-table-wrap" style="display:none;">
                    <div id="kvt_stage_overview" class="kvt-stage-overview"></div>
                    <div id="kvt_ats_bar" class="kvt-ats-bar">
                        <input type="text" id="kvt_search" placeholder="Buscar candidato, empresa, ciudad...">
                        <select id="kvt_stage_filter"><option value="">Todas las etapas</option></select>
                        <form id="kvt_export_form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" target="_blank" style="display:inline;">
                            <input type="hidden" name="action" value="kvt_export">
                            <input type="hidden" name="kvt_export_nonce" value="<?php echo esc_attr(wp_create_nonce('kvt_export')); ?>">
                            <input type="hidden" name="filter_client"  id="kvt_export_client"  value="">
                            <input type="hidden" name="filter_process" id="kvt_export_process" value="">
                            <input type="hidden" name="format"         id="kvt_export_format"   value="xls">
                            <button class="kvt-btn" type="button" id="kvt_export_xls">Exportar Excel</button>
                        </form>
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
                            <label>Rol
                              <input type="text" id="kvt_board_role" placeholder="Rol">
                            </label>
                            <label>Ubicación
                              <input type="text" id="kvt_board_location" placeholder="País o ciudad">
                            </label>
                            <button type="button" class="kvt-btn" id="kvt_board_assign" style="display:none;">Asignar seleccionados</button>
                            <form id="kvt_board_export_all_form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" target="_blank">
                              <input type="hidden" name="action" value="kvt_export">
                              <input type="hidden" name="kvt_export_nonce" value="<?php echo esc_attr(wp_create_nonce('kvt_export')); ?>">
                              <input type="hidden" name="filter_client" value="">
                              <input type="hidden" name="filter_process" value="">
                              <input type="hidden" name="format" id="kvt_board_export_all_format" value="xls">
                              <button type="button" class="kvt-btn" id="kvt_board_export_all_xls">Exportar Excel</button>
                            </form>
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
                        <div id="kvt_board_clients_list" class="kvt-list"></div>
                      </div>
                      <div id="kvt_board_tab_processes" class="kvt-tab-panel">
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
                <div id="kvt_calendar" class="kvt-calendar" style="display:none;"></div>
                <div id="kvt_activity" class="kvt-activity">
                    <div class="kvt-activity-tabs">
                        <button type="button" class="kvt-activity-tab active" data-target="tasks">Actividad</button>
                        <button type="button" class="kvt-activity-tab" data-target="log">Activity</button>
                        <button type="button" class="kvt-activity-tab" data-target="mail">Correos</button>
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
                    <div id="kvt_activity_mail" class="kvt-activity-content" style="display:none;">
                        <iframe id="kvt_correo_iframe" style="width:100%;border:0;min-height:600px;"></iframe>
                    </div>
                </div>
            </div>

            <div id="kvt_board_wrap" class="kvt-board-wrap">
                <button class="kvt-btn" type="button" id="kvt_board_toggle">Mostrar Kanban</button>
                <div id="kvt_board" class="kvt-board" aria-live="polite" style="display:none;margin-top:12px;"></div>
            </div>
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
                <select id="kvt_task_candidate"></select>
                <input type="date" id="kvt_task_date">
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
                <button type="button" class="kvt-tab" data-target="ai">AI Search</button>
              </div>
              <div class="kvt-new" id="kvt_new_container">
                <button type="button" class="kvt-btn" id="kvt_new_btn">Nuevo</button>
                <div class="kvt-new-menu" id="kvt_new_menu">
                  <button type="button" data-action="candidate">Nuevo candidato</button>
                  <button type="button" data-action="client">Nuevo cliente</button>
                  <button type="button" data-action="process">Nuevo proceso</button>
                </div>
              </div>
              <div id="kvt_tab_candidates" class="kvt-tab-panel active kvt-base">
                <div class="kvt-head">
                  <h3 class="kvt-title">Base de candidatos</h3>
                  <div class="kvt-stats"><span>Total: <?php echo intval($total_candidates); ?></span><span>Últimos 7 días: <?php echo intval($recent_candidates); ?></span></div>
                  <div class="kvt-toolbar">
                    <label>Nombre
                      <input type="text" id="kvt_modal_name" placeholder="Nombre">
                    </label>
                    <label>Rol
                      <input type="text" id="kvt_modal_role" placeholder="Rol">
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
                <div id="kvt_processes_list" class="kvt-list"></div>
              </div>
              <div id="kvt_tab_ai" class="kvt-tab-panel">
                <div class="kvt-modal-controls">
                  <textarea id="kvt_ai_input" rows="6" style="width:100%;" placeholder="Pega descripción del trabajo"></textarea>
                  <button type="button" class="kvt-btn" id="kvt_ai_search">Buscar</button>
                </div>
                <div id="kvt_ai_results" class="kvt-modal-list"></div>
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
                <input type="text" id="kvt_new_tags" placeholder="Tags">
                <input type="url" id="kvt_new_cv_url" placeholder="CV (URL)">
                <input type="file" id="kvt_new_cv_file" accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document">
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
              <div class="kvt-modal-controls">
                <input type="text" id="kvt_client_name" placeholder="Empresa">
                <input type="text" id="kvt_client_contact" placeholder="Persona de contacto">
                <input type="email" id="kvt_client_email" placeholder="Email">
                <input type="text" id="kvt_client_phone" placeholder="Teléfono">
                <textarea id="kvt_client_desc" placeholder="Descripción"></textarea>
                <button type="button" class="kvt-btn" id="kvt_client_submit">Crear</button>
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
        $css = "
        .kvt-wrapper{max-width:1200px;margin:0 auto;padding:16px;background:#fff;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.06);position:relative}
        .kvt-toolbar{display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:12px}
        .kvt-filters{display:flex;gap:12px;flex-wrap:wrap;margin:12px 0}
        .kvt-filters label{display:inline-flex;gap:6px;align-items:center;font-weight:600}
        .kvt-filters input,.kvt-filters select{padding:8px 10px;border:1px solid #e5e7eb;border-radius:8px}
        .kvt-client-link{margin-left:12px;display:inline-flex;align-items:center;gap:6px;font-weight:600}
        .kvt-logo{display:block;margin:0 auto 12px;max-width:300px}
        .kvt-help{position:absolute;top:16px;right:16px;font-size:24px;color:#0A212E;cursor:pointer}
        .kvt-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;border-bottom:1px solid #e5e7eb;padding-bottom:8px}
        .kvt-board-title{font-size:20px;font-weight:700;margin:0}
        .kvt-nav{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
        .kvt-nav-spacer{flex:1}
        .kvt-nav a{padding:6px 10px;border-radius:8px;color:#6b7280;font-weight:600}
        .kvt-nav a.active{background:#0A212E;color:#fff}
        .kvt-nav a:hover{background:#f1f5f9;color:#0A212E}
        .kvt-btn{background:#0A212E;color:#fff;border:none;border-radius:10px;padding:10px 14px;cursor:pointer;font-weight:600;text-decoration:none}
        .kvt-btn:hover{opacity:.95}
          .kvt-secondary{background:#475569}
          .kvt-new{position:relative;display:inline-block}
          .kvt-new-menu{position:absolute;right:0;top:100%;background:#fff;border:1px solid #e5e7eb;border-radius:8px;box-shadow:0 5px 15px rgba(0,0,0,.1);display:none;flex-direction:column;z-index:1000}
          .kvt-new-menu button{background:none;color:#0A212E;border:none;padding:8px 12px;text-align:left;cursor:pointer}
          .kvt-new-menu button:hover{background:#f1f5f9}
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
        .kvt-card .kvt-comment{margin-top:8px}
        .kvt-card .kvt-comment input{width:100%;padding:8px;border:1px solid #e5e7eb;border-radius:8px;margin-bottom:6px}
        .kvt-card .kvt-comment textarea{width:100%;min-height:60px;padding:8px;border:1px solid #e5e7eb;border-radius:8px}
        .kvt-card .kvt-comment .row{display:flex;gap:8px;margin-top:6px}
        .kvt-card .kvt-comment .row button{padding:8px 10px;border-radius:8px;border:1px solid #e5e7eb;background:#0A212E;color:#fff;cursor:pointer}
        .kvt-empty{padding:16px;color:#475569;font-style:italic}
        .kvt-delete{background:none !important;border:none !important;color:#b91c1c !important;font-size:18px;line-height:1;cursor:pointer;padding:0}
        .kvt-delete:hover{color:#7f1d1d !important}
        .kvt-delete.dashicons{vertical-align:middle}
        .kvt-main{display:flex;gap:16px}
        .kvt-table-wrap{margin-top:16px;overflow:auto;border:1px solid #e5e7eb;border-radius:12px}
        #kvt_table_wrap{flex:0 0 70%}
        .kvt-calendar{flex:0 0 70%;border:1px solid #e5e7eb;border-radius:12px;padding:8px;margin-top:16px}
        .kvt-cal-head{display:grid;grid-template-columns:repeat(7,1fr);text-align:center;font-weight:600}
        .kvt-cal-grid{display:grid;grid-template-columns:repeat(7,1fr);text-align:center}
        .kvt-cal-cell{min-height:80px;border:1px solid #e5e7eb;padding:4px;position:relative}
        .kvt-cal-day{font-size:12px;color:#6b7280;position:absolute;top:4px;right:4px}
        .kvt-cal-event{display:block;margin-top:16px;font-size:12px;text-align:left}
        .kvt-cal-cell.has-event{background:#f1f5f9}
        #kvt_table{width:100%;border-collapse:separate;border-spacing:0;table-layout:fixed}
        #kvt_table thead th{position:sticky;top:0;background:#f8fafc;color:#0A212E;padding:10px;border-bottom:1px solid #e5e7eb;text-align:left;font-weight:600}
        #kvt_table td{padding:8px;border-bottom:1px solid #e5e7eb;overflow-wrap:anywhere;word-break:break-word}
        #kvt_table tbody tr:hover{background:#f1f5f9}
        .kvt-ats-bar{display:flex;gap:8px;align-items:center;padding:8px}
        .kvt-activity{margin-top:16px;flex:1;border:1px solid #e5e7eb;border-radius:12px;padding:8px;overflow:auto}
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
        $link_cfg = [];
        if ($slug && isset($all_links[$slug])) {
            $link_cfg = $all_links[$slug];
        }
        $is_client_board = !empty($link_cfg);
        if ((is_user_logged_in() && current_user_can('edit_posts')) || $is_client_board) {
            // PDF.js and Tesseract.js for client-side text extraction
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
            wp_register_script('kvt-app', '', ['pdfjs','tesseract'], null, true);
            wp_enqueue_script('kvt-app');

            // Inline constants BEFORE app
        $statuses = $this->get_statuses();
        $sel_steps = $is_client_board ? array_map('sanitize_text_field', (array) ($link_cfg['steps'] ?? [])) : [];
        if ($is_client_board && $sel_steps) {
            $statuses = array_values(array_intersect($statuses, $sel_steps));
        }
        $columns  = $this->get_columns();
        $fields   = $is_client_board ? array_map('sanitize_text_field', (array) ($link_cfg['fields'] ?? [])) : [];
        wp_add_inline_script('kvt-app', 'const KVT_STATUSES='.wp_json_encode($statuses).';', 'before');
        wp_add_inline_script('kvt-app', 'const KVT_COLUMNS='.wp_json_encode($columns).';',  'before');
        wp_add_inline_script('kvt-app', 'const KVT_AJAX="'.esc_js(admin_url('admin-ajax.php')).'";', 'before');
        wp_add_inline_script('kvt-app', 'const KVT_HOME="'.esc_js(home_url('/view-board/')).'";', 'before');
        wp_add_inline_script('kvt-app', 'const KVT_NONCE="'.esc_js(wp_create_nonce('kvt_nonce')).'";', 'before');
        wp_add_inline_script('kvt-app', 'const KVT_CLIENT_VIEW='.($is_client_board?'true':'false').';', 'before');
        wp_add_inline_script('kvt-app', 'const KVT_ALLOWED_FIELDS='.wp_json_encode($fields).';', 'before');
        wp_add_inline_script('kvt-app', 'const KVT_ALLOWED_STEPS='.wp_json_encode($sel_steps).';', 'before');
        wp_add_inline_script('kvt-app', 'const KVT_ALLOW_COMMENTS='.(!empty($link_cfg['comments'])?'true':'false').';', 'before');
        wp_add_inline_script('kvt-app', 'const KVT_CLIENT_SLUG="'.esc_js($slug).'";', 'before');
        wp_add_inline_script('kvt-app', 'const KVT_IS_ADMIN='.((is_user_logged_in() && current_user_can('edit_posts'))?'true':'false').';', 'before');
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
        if ($is_client_board) {
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
  const urlParams = new URLSearchParams(location.search);
  const CLIENT_VIEW = typeof KVT_CLIENT_VIEW !== 'undefined' && KVT_CLIENT_VIEW;
  const ALLOWED_FIELDS = Array.isArray(KVT_ALLOWED_FIELDS) ? KVT_ALLOWED_FIELDS : [];
  const CLIENT_ID = typeof KVT_CLIENT_ID !== 'undefined' ? String(KVT_CLIENT_ID) : '';
  const PROCESS_ID = typeof KVT_PROCESS_ID !== 'undefined' ? String(KVT_PROCESS_ID) : '';
  const ALLOWED_STEPS = Array.isArray(KVT_ALLOWED_STEPS) ? KVT_ALLOWED_STEPS : [];
  const ALLOW_COMMENTS = typeof KVT_ALLOW_COMMENTS !== 'undefined' && KVT_ALLOW_COMMENTS;
  const CLIENT_SLUG = typeof KVT_CLIENT_SLUG !== 'undefined' ? KVT_CLIENT_SLUG : '';
  const IS_ADMIN = typeof KVT_IS_ADMIN !== 'undefined' && KVT_IS_ADMIN;
  const CLIENT_LINKS = (typeof KVT_CLIENT_LINKS === 'object' && KVT_CLIENT_LINKS) ? KVT_CLIENT_LINKS : {};

  const helpBtn = el('.kvt-help');
  const helpModal = el('#kvt_help_modal');
  const helpClose = el('#kvt_help_close');
  if (helpBtn && helpModal) {
    helpBtn.addEventListener('click', () => { helpModal.style.display = 'flex'; });
    if (helpClose) helpClose.addEventListener('click', () => { helpModal.style.display = 'none'; });
  }

  const viewLinks = els('.kvt-nav a[data-view]');
  const exportLink = el('#kvt_nav_export');
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
  exportLink && exportLink.addEventListener('click',e=>{
    e.preventDefault();
    const trg = el('#kvt_export_xls');
    if(trg) trg.click();
  });

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
  const boardToggle = el('#kvt_board_toggle');

  const tableWrap = el('#kvt_table_wrap');
  const tHead = el('#kvt_table_head');
  const tBody = el('#kvt_table_body');
  const searchInput = el('#kvt_search');
  const stageSelect = el('#kvt_stage_filter');
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
  const activityDue = el('#kvt_tasks_due');
  const activityUpcoming = el('#kvt_tasks_upcoming');
  const activityNotify = el('#kvt_notifications');
  const activityLog = el('#kvt_activity_log_list');
  const activityTabs = document.querySelectorAll('.kvt-activity-tab');
  const activityViews = document.querySelectorAll('.kvt-activity-content');
  const correoFrame = el('#kvt_correo_iframe');
  const overview = el('#kvt_stage_overview');
  const atsBar   = el('#kvt_ats_bar');
  const btnTaskOpen = el('#kvt_task_open');
  const taskModalWrap = el('#kvt_task_modal');
  const taskClose = el('#kvt_task_close');
  const taskForm = el('#kvt_task_form');
  const taskCandidate = el('#kvt_task_candidate');
  const taskDate = el('#kvt_task_date');
  const taskNote = el('#kvt_task_note');
  const stageModal = el('#kvt_stage_modal');
  const stageClose = el('#kvt_stage_close');
  const stageForm = el('#kvt_stage_form');
  const stageComment = el('#kvt_stage_comment');
  let stageId = '';
  let stageNext = '';

  const filtersBar = el('#kvt_filters_bar');
  const calendarWrap = el('#kvt_calendar');

  const selClient  = el('#kvt_client');
  const selProcess = el('#kvt_process');
  const btnToggle  = el('#kvt_toggle_table');
  const btnXLS     = el('#kvt_export_xls');
  const btnAdd     = el('#kvt_add_profile');
  const btnNew     = el('#kvt_new_btn');
  const newMenu    = el('#kvt_new_menu');
  const btnAllXLS  = el('#kvt_export_all_xls');
  const exportAllForm   = el('#kvt_export_all_form');
  const exportAllFormat = el('#kvt_export_all_format');
  const btnMail    = el('#kvt_mandar_correos');
  const btnShare   = el('#kvt_share_board');
  const btnProcesses = el('#kvt_open_processes');
  const shareModal = el('#kvt_share_modal');
  const shareClose = el('#kvt_share_close');
  const shareFieldsWrap = el('#kvt_share_fields');
    const shareStepsWrap  = el('#kvt_share_steps');
    const shareFieldsAll  = el('#kvt_share_fields_all');
    const shareStepsAll   = el('#kvt_share_steps_all');
    const shareGenerate   = el('#kvt_share_generate');
    const shareComments   = el('#kvt_share_comments');
    const selInfo        = el('#kvt_selected_info');
    const selClientInfo  = el('#kvt_selected_client');
    const selProcessInfo = el('#kvt_selected_process');
    const selBoardInfo   = el('#kvt_selected_board');
    const clientLink     = el('#kvt_client_link');
  const tablePager = el('#kvt_table_pager');
  const tablePrev  = el('#kvt_table_prev');
  const tableNext  = el('#kvt_table_next');
  const tablePage  = el('#kvt_table_pageinfo');
  let currentPage = 1;
  let totalPages = 1;
  let allRows = [];
  let calendarEvents = [];

  function showView(view){
    if(!filtersBar || !tableWrap || !calendarWrap) return;
    if(view==='ats'){
      filtersBar.style.display='flex';
      tableWrap.style.display='block';
      calendarWrap.style.display='none';
      refresh();
    } else if(view==='calendario'){
      filtersBar.style.display='none';
      tableWrap.style.display='none';
      calendarWrap.style.display='block';
      renderCalendar();
    } else {
      filtersBar.style.display='none';
      tableWrap.style.display='none';
      calendarWrap.style.display='none';
    }
  }

  if(stageSelect){
    stageSelect.innerHTML = '<option value="">Todas las etapas</option>' + KVT_STATUSES.map(s=>'<option value="'+escAttr(s)+'">'+esc(s)+'</option>').join('');
  }
  const infoModal = el('#kvt_info_modal');
  const infoClose = el('#kvt_info_close');
  const infoBody  = el('#kvt_info_body');

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
  const clientsList = el('#kvt_clients_list', modal);
  const processesList = el('#kvt_processes_list', modal);
  const boardTabs = els('#kvt_board_tabs .kvt-tab');
  const boardTabCandidates = el('#kvt_board_tab_candidates');
  const boardTabClients = el('#kvt_board_tab_clients');
  const boardTabProcesses = el('#kvt_board_tab_processes');
  const boardClientsList = el('#kvt_board_clients_list');
  const boardProcessesList = el('#kvt_board_processes_list');
  const aiInput = el('#kvt_ai_input', modal);
  const aiBtn = el('#kvt_ai_search', modal);
  const aiResults = el('#kvt_ai_results', modal);

  if (CLIENT_VIEW) {
    if (selClient) { selClient.value = CLIENT_ID; selClient.disabled = true; }
    if (selProcess) { selProcess.value = PROCESS_ID; selProcess.disabled = true; }
    const actions = el('.kvt-actions');
    if (actions) actions.style.display = 'none';
    if (selInfo) selInfo.style.display = 'none';
    if (clientLink) clientLink.style.display = 'none';
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
      if(!fieldsList.some(f=>f.key==='public_notes')) fieldsList.push({key:'public_notes',label:'Notas públicas'});
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

  function openModal(){
    modal.style.display = 'flex';
    if(modalName) modalName.value = '';
    if(modalRole) modalRole.value = '';
    if(modalLoc) modalLoc.value = '';
    switchTab('candidates');
  }
  function closeModal(){ modal.style.display = 'none'; }
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
    return fetch(KVT_AJAX, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:body.toString() }).then(r=>r.json());
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
      if (!CLIENT_VIEW) card.setAttribute('draggable','true');
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
      if (!CLIENT_VIEW || ALLOWED_FIELDS.includes('notes')) {
        sub.textContent = lastNoteSnippet(c.meta.notes);
      } else if (ALLOWED_FIELDS.includes('public_notes')) {
        sub.textContent = lastNoteSnippet(c.meta.public_notes);
      }
      const tagsWrap = document.createElement('div'); tagsWrap.className = 'kvt-tags';
      if ((!CLIENT_VIEW || ALLOWED_FIELDS.includes('tags')) && c.meta && c.meta.tags){
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
        myComment = CLIENT_VIEW ? clientComments.find(cc => cc.slug === CLIENT_SLUG) : clientComments[clientComments.length-1];
      }
      let follow;
      let commentLine;
      if (c.meta.next_action && (!CLIENT_VIEW || ALLOWED_FIELDS.includes('next_action'))){
        follow = document.createElement('p');
        follow.className = 'kvt-followup';
        const ico = document.createElement('span');
        ico.className = 'dashicons dashicons-clock';
        follow.appendChild(ico);
        const noteTxt = c.meta.next_action_note && (!CLIENT_VIEW || ALLOWED_FIELDS.includes('next_action_note')) ? ' — ' + c.meta.next_action_note : '';
        follow.appendChild(document.createTextNode(' Próxima acción: ' + c.meta.next_action + noteTxt));
        const parts = c.meta.next_action.split('-');
        if(parts.length===3){
          const dt = new Date(parts[2], parts[1]-1, parts[0]);
          const today = new Date(); today.setHours(0,0,0,0);
          if(dt <= today) card.classList.add('kvt-overdue');
        }
      }
      if (myComment){
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
    if (!CLIENT_VIEW) {
      btnDel = document.createElement('button');
      btnDel.type='button'; btnDel.className='kvt-delete dashicons dashicons-trash'; btnDel.setAttribute('title','Eliminar candidato');
      expand.appendChild(btn); expand.appendChild(btnDel);
    } else {
      expand.appendChild(btn);
      if (ALLOW_COMMENTS) {
        const cBtn = document.createElement('button');
        cBtn.type='button';
        cBtn.textContent = myComment ? 'Editar comentario' : 'Comentar';
        expand.appendChild(cBtn);
        cBtn.addEventListener('click', ()=>{
          let form = card.querySelector('.kvt-comment');
          if(form){ form.remove(); return; }
          form = document.createElement('div');
          form.className = 'kvt-comment';
          form.innerHTML = '<input type="text" class="kvt-comment-name" placeholder="Tu nombre" value="'+escAttr(myComment?myComment.name:'')+'">'
            +'<textarea class="kvt-comment-text" placeholder="Comentario">'+esc(myComment?myComment.comment:'')+'</textarea>'
            +'<div class="row"><button type="button" class="kvt-save-comment">Guardar</button></div>';
          card.appendChild(form);
          const saveBtn = form.querySelector('.kvt-save-comment');
          saveBtn.addEventListener('click', ()=>{
            const name = form.querySelector('.kvt-comment-name').value.trim();
            const msg  = form.querySelector('.kvt-comment-text').value.trim();
            if(!name || !msg){ alert('Completa todos los campos'); return; }
            const p = new URLSearchParams();
            p.set('action','kvt_client_comment');
            p.set('_ajax_nonce', KVT_NONCE);
            p.set('id', c.id);
            p.set('slug', CLIENT_SLUG);
            p.set('name', name);
            p.set('comment', msg);
            fetch(KVT_AJAX,{method:'POST',body:p}).then(r=>r.json()).then(j=>{
              if(j.success){
                myComment = {name, comment:msg, slug:CLIENT_SLUG};
                if(Array.isArray(c.meta.client_comments)){
                  const idx = c.meta.client_comments.findIndex(cc=>cc.slug===CLIENT_SLUG);
                  if(idx>=0) c.meta.client_comments[idx]=myComment; else c.meta.client_comments.push(myComment);
                } else {
                  c.meta.client_comments=[myComment];
                }
                const txt = ' Comentario: ' + ((!CLIENT_VIEW && name)? name + ': ' : '') + msg;
                if(commentLine){
                  commentLine.innerHTML='';
                  const ic = document.createElement('span'); ic.className='dashicons dashicons-warning';
                  commentLine.appendChild(ic);
                  commentLine.appendChild(document.createTextNode(txt));
                } else {
                  commentLine = document.createElement('p');
                  commentLine.className='kvt-followup';
                  const ic = document.createElement('span'); ic.className='dashicons dashicons-warning';
                  commentLine.appendChild(ic);
                  commentLine.appendChild(document.createTextNode(txt));
                  card.insertBefore(commentLine, sub);
                }
                cBtn.textContent='Editar comentario';
                form.remove();
                alert('Comentario guardado');
              } else {
                alert('Error');
              }
            });
          });
        });
      }
    }

    const panel = document.createElement('div'); panel.className='kvt-panel';
    panel.innerHTML = buildProfileHTML(c);

    btn.addEventListener('click', ()=>{
      const visible = panel.style.display === 'block';
      panel.style.display = visible ? 'none' : 'block';
      btn.textContent = visible ? 'Ver perfil' : 'Ocultar perfil';
    });

    if (!CLIENT_VIEW) {
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
    card.appendChild(expand); card.appendChild(panel);

    if (!CLIENT_VIEW) {
      // Enable handlers after elements are in the DOM
      enableProfileEditHandlers(card, String(c.id));
      enableCvUploadHandlers(card, String(c.id));
    }
    return card;
  }

  function buildProfileHTML(c){
    const m = c.meta||{};
    if (CLIENT_VIEW) {
      const fields = ALLOWED_FIELDS.length ? ALLOWED_FIELDS : KVT_COLUMNS.map(col=>col.key);
      const html = fields.map(key=>{
        const col = KVT_COLUMNS.find(co=>co.key===key);
        const label = col ? col.label : key;
        return '<dt>'+esc(label)+'</dt><dd>'+esc(m[key]||'')+'</dd>';
      }).join('');
      return '<dl>'+html+'</dl>';
    }
    const input = (val,type='text',ph='',cls='')=>'<input class="kvt-input'+(cls?' '+cls:'')+'" type="'+type+'" value="'+esc(val||'')+'" placeholder="'+esc(ph||'')+'">';
    const kvInp = (label, html)=>'<dt>'+esc(label)+'</dt><dd>'+html+'</dd>';

    const dl =
      kvInp('Nombre',       input((m.first_name||''))) +
      kvInp('Apellidos',    input((m.last_name||''))) +
      kvInp('Email',        input((m.email||''), 'email')) +
      kvInp('Teléfono',     input((m.phone||''))) +
      kvInp('País',         input((m.country||''))) +
      kvInp('Ciudad',       input((m.city||''))) +
      kvInp('Current role', input((m.current_role||''))) +
      kvInp('Tags',         input((m.tags||''))) +
      kvInp('CV (URL)',     input((m.cv_url||''), 'url', 'https://...')) +
      kvInp('Subir CV',     '<input class=\"kvt-input kvt-cv-file\" type=\"file\" accept=\".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document\">'+
                            '<button type=\"button\" class=\"kvt-upload-cv\" style=\"margin-top:6px\">Subir y guardar</button>') +
      kvInp('Fecha subida', input((m.cv_uploaded||''), 'text', 'DD-MM-YYYY', 'kvt-date')) +
      kvInp('Próxima acción', input((m.next_action||''), 'text', 'DD-MM-YYYY', 'kvt-date')) +
      kvInp('Comentario próxima acción', input((m.next_action_note||'')));

    const notesVal = m.notes || '';
    const notes =
      '<div class="kvt-notes">'+
        '<label><strong>Notas</strong></label>'+
        '<textarea class="kvt-notes-text">'+esc(notesVal)+'</textarea>'+
      '</div>';
    const pubNotesVal = m.public_notes || '';
    const publicNotes =
      '<div class="kvt-public-notes">'+
        '<label><strong>Notas públicas</strong></label>'+
        '<textarea class="kvt-public-notes-text">'+esc(pubNotesVal)+'</textarea>'+
      '</div>';

    const log = Array.isArray(m.activity_log) ? m.activity_log : [];
    const logItems = log.map(it=>{
      const when = esc(it.time||'');
      const who  = esc(it.author||'');
      let text='';
      if(it.type==='status'){
        text = 'Estado → '+esc(it.status||'');
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
      }
      return '<li>'+when+' — '+who+': '+text+'</li>';
    }).join('');
    const logSection = '<div class="kvt-profile-activity"><h3>Actividad</h3>'+(logItems?('<ul>'+logItems+'</ul>'):'<p>No hay actividad</p>')+'</div>';

    const saveBtn = '<button type="button" class="kvt-save-profile">Guardar perfil</button>';

    return logSection+'<dl>'+dl+'</dl>'+notes+publicNotes+saveBtn;
  }

  function enableProfileEditHandlers(card, id){
    const inputs = card.querySelectorAll('dl .kvt-input');
    const txtNotes = card.querySelector('.kvt-notes-text');
    const txtPubNotes = card.querySelector('.kvt-public-notes-text');
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
      const vals = Array.from(inputs).map(i=>i.value || '');
      const payload = {
        first_name: vals[0] || '',
        last_name:  vals[1] || '',
        email:      vals[2] || '',
        phone:      vals[3] || '',
        country:    vals[4] || '',
        city:       vals[5] || '',
        current_role: vals[6] || '',
        tags:       vals[7] || '',
        cv_url:     vals[8] || '',
        cv_uploaded:vals[10] || '',
        next_action:vals[11] || '',
        next_action_note:vals[12] || '',
        notes:      txtNotes ? txtNotes.value : '',
        public_notes: txtPubNotes ? txtPubNotes.value : '',
      };
      fetch(KVT_AJAX, {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:new URLSearchParams({action:'kvt_update_profile', _ajax_nonce:KVT_NONCE, id, ...payload}).toString()})
        .then(r=>r.json()).then(j=>{
          if(!j.success) return alert(j.data && j.data.msg ? j.data.msg : 'No se pudo guardar el perfil.');
          const title = card.querySelector('.kvt-title');
          if (title) title.textContent = (payload.first_name+' '+payload.last_name).trim() || title.textContent;
          const sub = card.querySelector('.kvt-sub');
          if (sub){
            if (CLIENT_VIEW && ALLOWED_FIELDS.includes('public_notes') && !ALLOWED_FIELDS.includes('notes')) {
              sub.textContent = payload.public_notes ? lastNoteSnippet(payload.public_notes) : '';
            } else {
              sub.textContent = payload.notes ? lastNoteSnippet(payload.notes) : '';
            }
          }
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
          const follow = card.querySelector('.kvt-followup');
          if (payload.next_action){
            const txt = 'Próxima acción: ' + payload.next_action + (payload.next_action_note ? ' — ' + payload.next_action_note : '');
            if (follow){
              follow.innerHTML = '';
              const ico = document.createElement('span');
              ico.className = 'dashicons dashicons-clock';
              follow.appendChild(ico);
              follow.appendChild(document.createTextNode(' ' + txt));
            } else {
              const tagsWrap = card.querySelector('.kvt-tags');
              const f = document.createElement('p');
              f.className = 'kvt-followup';
              const ico = document.createElement('span');
              ico.className = 'dashicons dashicons-clock';
              f.appendChild(ico);
              f.appendChild(document.createTextNode(' ' + txt));
              if (tagsWrap) tagsWrap.after(f); else card.prepend(f);
            }
            const parts = payload.next_action.split('-');
            card.classList.remove('kvt-overdue');
            if(parts.length===3){
              const dt = new Date(parts[2], parts[1]-1, parts[0]);
              const today = new Date(); today.setHours(0,0,0,0);
              if(dt <= today) card.classList.add('kvt-overdue');
            }
          } else if (follow){
            follow.remove();
            card.classList.remove('kvt-overdue');
          }
          alert('Perfil guardado.');
        });
    });
  }

  function enableCvUploadHandlers(card, id){
    const fileInput = card.querySelector('.kvt-cv-file');
    const urlInput  = card.querySelector('dl .kvt-input[type="url"]');
    const dateInput = card.querySelectorAll('dl .kvt-input')[10];
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
        const inputs = card.querySelectorAll('dl .kvt-input');
        if(inputs[0] && j.data.fields.first_name) inputs[0].value = j.data.fields.first_name;
        if(inputs[1] && j.data.fields.last_name) inputs[1].value = j.data.fields.last_name;
        if(inputs[2] && j.data.fields.email) inputs[2].value = j.data.fields.email;
        if(inputs[3] && j.data.fields.phone) inputs[3].value = j.data.fields.phone;
        if(inputs[4] && j.data.fields.country) inputs[4].value = j.data.fields.country;
        if(inputs[5] && j.data.fields.city) inputs[5].value = j.data.fields.city;
        if(inputs[6] && j.data.fields.current_role) inputs[6].value = j.data.fields.current_role;
      }
      alert('CV subido y guardado.');
      refresh();
    });
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
                         '<p class="kvt-followup"><span class="dashicons dashicons-clock"></span> '+esc(c.date)+(c.note?' — '+esc(c.note):'')+'</p>';
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
                         '<p class="kvt-followup"><span class="dashicons dashicons-clock"></span> '+esc(c.date)+(c.note?' — '+esc(c.note):'')+'</p>';
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

    if (!CLIENT_VIEW) enableDnD();
    allRows = Array.isArray(data) ? data : [];
    filterTable();
  }

  function renderTable(rows){
    if(!tHead || !tBody) return;
    tHead.innerHTML = '<th>Candidato/a</th><th>Etapas</th>';
    tBody.innerHTML = rows.map(r=>{
        const nameTxt = esc(((r.meta.first_name||'')+' '+(r.meta.last_name||'')).trim());
        const icons=[];
        const comments=Array.isArray(r.meta.client_comments)?r.meta.client_comments:[];
        if(comments.length && (!CLIENT_VIEW || ALLOW_COMMENTS)){
          const cm=comments[comments.length-1];
          icons.push('<span class="kvt-name-icon kvt-alert" title="'+escAttr(cm.comment)+'">!</span>');
        }
        if(r.meta.next_action && (!CLIENT_VIEW || ALLOWED_FIELDS.includes('next_action'))){
          const parts=r.meta.next_action.split('-');
          let overdue=false;
          if(parts.length===3){
            const d=new Date(parts[2],parts[1]-1,parts[0]);
            const today=new Date();today.setHours(0,0,0,0);
            overdue=d<=today;
          }
          const note=r.meta.next_action_note? ' — '+r.meta.next_action_note:'';
          icons.push('<span class="kvt-name-icon dashicons dashicons-clock'+(overdue?' overdue':'')+'" title="'+escAttr(r.meta.next_action+note)+'"></span>');
        }
        const noteSrc = (!CLIENT_VIEW || ALLOWED_FIELDS.includes('notes')) ? r.meta.notes : (ALLOWED_FIELDS.includes('public_notes') ? r.meta.public_notes : '');
        const snip = lastNoteSnippet(noteSrc);
        if(snip){ icons.push('<span class="kvt-name-icon dashicons dashicons-format-chat" title="'+escAttr(snip)+'"></span>'); }
        const name = '<a href="#" class="kvt-row-view" data-id="'+escAttr(r.id)+'">'+nameTxt+'</a>'+icons.join('');
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
        return '<tr><td>'+name+'</td><td class="kvt-stage-cell">'+parts+'</td></tr>';
      }).join('');
  }

  function renderActivity(rows){
    if(!activityDue || !activityUpcoming || !activityNotify) return;
    const today = new Date(); today.setHours(0,0,0,0);
    const due=[]; const upcoming=[]; const notifs=[]; const logs=[];
    calendarEvents = [];
    rows.forEach(r=>{
      const nameTxt = esc(((r.meta.first_name||'')+' '+(r.meta.last_name||'')).trim());
      if(r.meta.next_action){
        const parts = r.meta.next_action.split('-');
        if(parts.length===3){
          const d = new Date(parts[2], parts[1]-1, parts[0]);
          const note = esc(r.meta.next_action_note||'');
          const item = '<li data-id="'+escAttr(r.id)+'"><a href="#" class="kvt-row-view" data-id="'+escAttr(r.id)+'">'+nameTxt+'</a> - '+esc(r.meta.next_action)+(note?' — '+note:'')+' <span class="kvt-task-done dashicons dashicons-yes" title="Marcar como hecha"></span><span class="kvt-task-delete dashicons dashicons-no" title="Eliminar"></span></li>';
          (d <= today ? due : upcoming).push(item);
          const ds = parts.join('-');
          calendarEvents.push({date: ds, text: nameTxt});
        }
      }
      if(Array.isArray(r.meta.client_comments)){
        r.meta.client_comments.forEach((cc,idx)=>{
          if(!cc.dismissed){
            const item = '<li data-id="'+escAttr(r.id)+'" data-index="'+idx+'"><a href="#" class="kvt-row-view" data-id="'+escAttr(r.id)+'">'+nameTxt+'</a> — '+esc(cc.comment)+' <span class="kvt-comment-dismiss dashicons dashicons-no" title="Descartar"></span></li>';
            notifs.push(item);
          }
        });
      }
      if(Array.isArray(r.meta.activity_log)){
        r.meta.activity_log.forEach(l=>{
          let msg='';
          switch(l.type){
            case 'status':
              msg='Estado a '+esc(l.status)+(l.comment?' — '+esc(l.comment):'');
              break;
            case 'task_add':
              msg='Tarea '+esc(l.date)+(l.note?' — '+esc(l.note):'');
              break;
            case 'task_done':
              msg='Tarea completada '+esc(l.date)+(l.comment?' — '+esc(l.comment):'');
              break;
            case 'task_deleted':
              msg='Tarea eliminada '+esc(l.date)+(l.note?' — '+esc(l.note):'');
              break;
            default:
              msg=esc(l.type||'');
          }
          const author = esc(l.author||'');
          const time = esc(l.time||'');
          logs.push({time,text: nameTxt+' — '+msg+(author?' ('+author+')':'')});
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
  }

  function renderActivityDashboard(data){
    if(!activityDue || !activityUpcoming || !activityNotify) return;
    calendarEvents = [];
    const due = (data.overdue||[]).map(c=>{
      const note = c.note ? ' — '+esc(c.note) : '';
      calendarEvents.push({date:c.date, text:c.candidate});
      return '<li data-id="'+escAttr(c.candidate_id)+'"><a href="#" class="kvt-row-view" data-id="'+escAttr(c.candidate_id)+'">'+esc(c.candidate)+'</a> - '+esc(c.date)+note+' <span class="kvt-task-done dashicons dashicons-yes" title="Marcar como hecha"></span><span class="kvt-task-delete dashicons dashicons-no" title="Eliminar"></span></li>';
    });
    const upcoming = (data.upcoming||[]).map(c=>{
      const note = c.note ? ' — '+esc(c.note) : '';
      calendarEvents.push({date:c.date, text:c.candidate});
      return '<li data-id="'+escAttr(c.candidate_id)+'"><a href="#" class="kvt-row-view" data-id="'+escAttr(c.candidate_id)+'">'+esc(c.candidate)+'</a> - '+esc(c.date)+note+' <span class="kvt-task-done dashicons dashicons-yes" title="Marcar como hecha"></span><span class="kvt-task-delete dashicons dashicons-no" title="Eliminar"></span></li>';
    });
    const notifs = (data.comments||[]).map(c=>{
      return '<li data-id="'+escAttr(c.candidate_id)+'" data-index="'+escAttr(c.index)+'"><a href="#" class="kvt-row-view" data-id="'+escAttr(c.candidate_id)+'">'+esc(c.candidate)+'</a> — '+esc(c.comment)+' <span class="kvt-comment-dismiss dashicons dashicons-no" title="Descartar"></span></li>';
    });
    const logs = (data.logs||[]).sort((a,b)=>a.time<b.time?1:-1);
    activityDue.innerHTML = due.join('') || '<li>No hay tareas pendientes</li>';
    activityUpcoming.innerHTML = upcoming.join('') || '<li>No hay tareas próximas</li>';
    activityNotify.innerHTML = notifs.join('') || '<li>No hay notificaciones</li>';
    if(activityLog) activityLog.innerHTML = logs.length ? logs.map(l=>'<li>'+esc(l.time)+' - '+esc(l.text)+'</li>').join('') : '<li>No hay actividad</li>';
  }

  function renderCalendar(){
    if(!calendarWrap) return;
    const now = new Date();
    const month = now.getMonth();
    const year = now.getFullYear();
    const first = new Date(year, month, 1);
    const last = new Date(year, month+1, 0);
    const dayNames = ['Dom','Lun','Mar','Mié','Jue','Vie','Sáb'];
    let html = '<div class="kvt-cal-head">'+dayNames.map(d=>'<div>'+d+'</div>').join('')+'</div><div class="kvt-cal-grid">';
    for(let i=0;i<first.getDay();i++) html += '<div class="kvt-cal-cell"></div>';
    for(let d=1; d<=last.getDate(); d++){
      const ds = (d<10?'0'+d:d)+'-'+(month+1<10?'0'+(month+1):(month+1))+'-'+year;
      const ev = calendarEvents.filter(e=>e.date===ds);
      let cls = 'kvt-cal-cell';
      if(ev.length) cls += ' has-event';
      html += '<div class="'+cls+'"><span class="kvt-cal-day">'+d+'</span>';
      ev.forEach(e=>{ html += '<span class="kvt-cal-event">'+esc(e.text)+'</span>'; });
      html += '</div>';
    }
    const fill = (first.getDay()+last.getDate())%7;
    if(fill!==0){ for(let i=0;i<7-fill;i++) html += '<div class="kvt-cal-cell"></div>'; }
    html += '</div>';
    calendarWrap.innerHTML = html;
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
      const parts = p.created.split('-');
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

  function populateTaskCandidates(){
    if(!taskCandidate) return;
    taskCandidate.innerHTML = '<option value="">Selecciona</option>' + allRows.map(r=>{
      const name = esc(((r.meta.first_name||'')+' '+(r.meta.last_name||'')).trim());
      return '<option value="'+escAttr(r.id)+'">'+name+'</option>';
    }).join('');
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
    const cid = selClient && selClient.value ? selClient.value : '';
    const pid = selProcess && selProcess.value ? selProcess.value : '';
    if(clientLink) clientLink.innerHTML = '';
    if(!selInfo){
      if(cid && pid){
        const key = cid+'|'+pid;
        const slug = CLIENT_LINKS[key];
        if(slug){
          const url = KVT_HOME + slug;
          const boardDet = '<strong>Vista cliente:</strong> <a href="'+escAttr(url)+'" target="_blank">Ver tablero</a>';
          if(clientLink) clientLink.innerHTML = boardDet;
        }
      }
      return;
    }
    if(!cid && !pid){ selInfo.style.display='none'; if(clientLink) clientLink.innerHTML=''; return; }
    selInfo.style.display='block';
    let clientDet='';
    if(cid && Array.isArray(window.KVT_CLIENT_MAP)){
      const c = window.KVT_CLIENT_MAP.find(x=>String(x.id)===cid);
      if(c){
        clientDet = '<strong>Cliente:</strong> '+esc(c.name||'');
        if(c.contact_name || c.contact_email || c.contact_phone){
          clientDet += '<br><em>Contacto:</em> '+esc(c.contact_name||'');
          if(c.contact_email) clientDet += ', '+esc(c.contact_email);
          if(c.contact_phone) clientDet += ', '+esc(c.contact_phone);
        }
        if(c.description) clientDet += '<br><em>Descripción:</em> '+esc(c.description);
        const procNames = Array.isArray(window.KVT_PROCESS_MAP)?window.KVT_PROCESS_MAP.filter(p=>String(p.client_id)===cid).map(p=>p.name):[];
        if(procNames.length) clientDet += '<br><em>Procesos:</em> '+esc(procNames.join(', '));
      }
      clientDet += ' <button type="button" class="kvt-edit-client-inline" data-id="'+cid+'">Editar</button>';
    }
    selClientInfo.innerHTML = clientDet;
    let procDet='';
    if(pid && Array.isArray(window.KVT_PROCESS_MAP)){
      const p = window.KVT_PROCESS_MAP.find(x=>String(x.id)===pid);
      if(p){
        const cl = getClientById(p.client_id);
        const clientName = cl ? cl.name||'' : (p.client||'');
        const title = (clientName?esc(clientName)+' + ':'')+esc(p.name||'');
        procDet = '<strong>'+title+'</strong>';
        if(p.creator) procDet += '<br><em>Registrado por:</em> '+esc(p.creator);
        const contact = p.contact_name || (cl?cl.contact_name:'');
        if(contact) procDet += '<br><em>Persona de contacto:</em> '+esc(contact);
        if(p.job_stage) procDet += '<br><em>Etapa:</em> '+esc(p.job_stage);
        if(p.created){
          const cd = new Date(p.created);
          if(!isNaN(cd)) procDet += '<br><em>Días abierto:</em> '+Math.floor((Date.now()-cd.getTime())/86400000);
        }
        if(p.description) procDet += '<br><em>Descripción:</em> '+esc(p.description);
        procDet += ' <button type="button" class="kvt-edit-process-inline" data-id="'+pid+'">Editar</button>';
      }
    }
    selProcessInfo.innerHTML = procDet;
    let boardDet='';
    if(cid && pid){
      const key = cid+'|'+pid;
      const slug = CLIENT_LINKS[key];
      if(slug){
        const url = KVT_HOME + slug;
        boardDet = '<strong>Vista cliente:</strong> <a href="'+escAttr(url)+'" target="_blank">Ver tablero</a>';
      }
    }
    if(selBoardInfo) selBoardInfo.innerHTML = boardDet;
    if(clientLink) clientLink.innerHTML = boardDet;
  }

  const exportForm = el('#kvt_export_form');
  btnXLS && btnXLS.addEventListener('click', ()=>{ el('#kvt_export_format').value='xls'; syncExportHidden(); exportForm.submit(); });
  btnAllXLS && btnAllXLS.addEventListener('click', ()=>{ exportAllFormat.value='xls'; exportAllForm && exportAllForm.submit(); });
  boardExportXls && boardExportXls.addEventListener('click', ()=>{ boardExportFormat.value='xls'; boardExportAllForm && boardExportAllForm.submit(); });
  infoClose && infoClose.addEventListener('click', ()=>{ infoModal.style.display='none'; });
  infoModal && infoModal.addEventListener('click', e=>{ if(e.target===infoModal) infoModal.style.display='none'; });
  aiBtn && aiBtn.addEventListener('click', ()=>{
    const desc = (aiInput.value||'').trim();
    if(!desc) return;
    aiResults.innerHTML = '<div class="kvt-loading">Buscando...</div>';
    aiBtn.disabled = true;
    const params = new URLSearchParams();
    params.set('action','kvt_ai_search');
    params.set('_ajax_nonce', KVT_NONCE);
    params.set('description', desc);
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
            '<div class="kvt-mini-panel">'+buildProfileHTML({meta:it.meta})+'</div>'+
          '</div>';
        }).join('');
        els('.kvt-mini-view', aiResults).forEach(b=>{
          b.addEventListener('click', ()=>{
            const card = b.closest('.kvt-card-mini');
            const panel = card.querySelector('.kvt-mini-panel');
            const show = panel.style.display==='block';
            panel.style.display = show?'none':'block';
            b.textContent = show?'Ver perfil':'Ocultar';
          });
        });
      }).catch(()=>{ aiBtn.disabled=false; aiResults.innerHTML=''; });
  });

  btnToggle && btnToggle.addEventListener('click', e=>{
    e.preventDefault();
    tableWrap.style.display = (tableWrap.style.display==='none' || !tableWrap.style.display) ? 'block' : 'none';
  });

  tBody && tBody.addEventListener('click', e=>{
    const step = e.target.closest('.kvt-stage-step');
    if(step){
      stageId = step.dataset.id;
      stageNext = step.dataset.status;
      if(stageModal) stageModal.style.display='flex';
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
  boardToggle && boardToggle.addEventListener('click', ()=>{
    const hidden = board.style.display === 'none';
    board.style.display = hidden ? 'flex' : 'none';
    boardToggle.textContent = hidden ? 'Ocultar Kanban' : 'Mostrar Kanban';
  });

  const loadCorreos = ()=>{
    if(!correoFrame) return;
    const pid = selProcess ? selProcess.value : '';
    correoFrame.src = KVT_BULKREADER_URL + (pid ? '&process='+encodeURIComponent(pid) : '');
  };

  selProcess && selProcess.addEventListener('change', ()=>{ loadCorreos(); });
  loadCorreos();

  activityTabs.forEach(tab=>{
    tab.addEventListener('click', ()=>{
      activityTabs.forEach(t=>t.classList.remove('active'));
      activityViews.forEach(v=>v.style.display='none');
      tab.classList.add('active');
      const pane = el('#kvt_activity_'+tab.dataset.target);
      if(pane) pane.style.display='block';
      if(tab.dataset.target==='mail') loadCorreos();
    });
  });

  taskForm && taskForm.addEventListener('submit', e=>{
    e.preventDefault();
    const id = taskCandidate.value;
    const date = taskDate.value;
    const note = taskNote.value;
    if(!id || !date) return;
    const params = new URLSearchParams();
    params.set('action','kvt_add_task');
    params.set('_ajax_nonce', KVT_NONCE);
    params.set('id', id);
    params.set('date', date);
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
      const cand = allRows.find(r=>String(r.id)===id);
      if(cand){
        infoBody.innerHTML = buildProfileHTML(cand);
        infoModal.style.display='flex';
      }
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
  btnTaskOpen && btnTaskOpen.addEventListener('click', e=>{ e.preventDefault(); if(taskModalWrap) taskModalWrap.style.display='flex'; });
  taskClose && taskClose.addEventListener('click', ()=>{ if(taskModalWrap) taskModalWrap.style.display='none'; });
  taskModalWrap && taskModalWrap.addEventListener('click', e=>{ if(e.target===taskModalWrap) taskModalWrap.style.display='none'; });
  btnMail && btnMail.addEventListener('click', e=>{
    e.preventDefault();
    window.open('https://kovacictalent.com/wp-admin/admin.php?page=kt-abm','_blank','noopener');
  });
  btnShare && btnShare.addEventListener('click', e=>{
    e.preventDefault();
    if (!selClient || !selClient.value || !selProcess || !selProcess.value) {
      alert('Selecciona un cliente y un proceso.');
      return;
    }
    buildShareOptions();
    if(shareModal) shareModal.style.display='flex';
  });
  btnProcesses && btnProcesses.addEventListener('click', e=>{
    e.preventDefault();
    openModal();
    switchTab('processes');
  });
  tablePrev && tablePrev.addEventListener('click', ()=>{ if(currentPage>1){ currentPage--; refresh(); } });
  tableNext && tableNext.addEventListener('click', ()=>{ if(currentPage<totalPages){ currentPage++; refresh(); } });
  shareClose && shareClose.addEventListener('click', ()=>{ shareModal.style.display='none'; });
  shareModal && shareModal.addEventListener('click', e=>{ if(e.target===shareModal) shareModal.style.display='none'; });
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
    shareGenerate && shareGenerate.addEventListener('click', ()=>{
      const fields = els('input[type="checkbox"]', shareFieldsWrap).filter(cb=>cb.checked).map(cb=>cb.value);
      const steps  = els('input[type="checkbox"]', shareStepsWrap).filter(cb=>cb.checked).map(cb=>cb.value);
      const params = new URLSearchParams();
      params.set('action','kvt_generate_share_link');
      params.set('_ajax_nonce', KVT_NONCE);
      params.set('client', selClient.value);
      params.set('process', selProcess.value);
      params.set('page', '');
      fields.forEach(f=>params.append('fields[]', f));
      steps.forEach(s=>params.append('steps[]', s));
      if(shareComments && shareComments.checked) params.set('comments','1');
      if(CLIENT_VIEW && CLIENT_SLUG) params.set('slug', CLIENT_SLUG);
      fetch(KVT_AJAX,{method:'POST',body:params}).then(r=>r.json()).then(j=>{
        if(j.success && j.data && j.data.slug){
          const slug = j.data.slug;
          if(!CLIENT_VIEW){
            const url = KVT_HOME + slug;
            CLIENT_LINKS[selClient.value+'|'+selProcess.value] = slug;
            prompt('Enlace para compartir', url);
            shareModal.style.display='none';
            updateSelectedInfo();
          } else {
            shareModal.style.display='none';
            location.reload();
          }
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
          const check = filterActive?'<div class="kvt-check"><input type="checkbox" class="kvt-select" value="'+it.id+'" aria-label="Seleccionar"></div>':'';
          const addBtn = allowAdd?'<button type="button" class="kvt-btn kvt-mini-add" data-id="'+it.id+'">Añadir</button>':'';
          const editBtn = '<button type="button" class="kvt-btn kvt-mini-view kvt-mini-edit" data-id="'+it.id+'" data-label="Editar perfil">Editar perfil</button>';
          return '<div class="kvt-card-mini" data-id="'+it.id+'">'+
            '<div class="kvt-row'+(filterActive?' with-check':'')+'">'+
              check+
              '<div>'+firstLineWithCv+'<br>'+infoLine+'</div>'+
              '<div class="kvt-meta"><button type="button" class="kvt-delete kvt-mini-delete" data-id="'+it.id+'" aria-label="Eliminar"></button>'+editBtn+addBtn+'</div>'+
            '</div>'+
            '<div class="kvt-mini-panel">'+buildProfileHTML({meta:it.meta})+'</div>'+
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
        if(ctx.assign) ctx.assign.style.display = (filterActive && allowAdd) ? 'inline-flex' : 'none';
        els('.kvt-mini-view', ctx.list).forEach(b=>{
          b.addEventListener('click', e=>{
            e.preventDefault();
            const card = b.closest('.kvt-card-mini');
            const panel = card.querySelector('.kvt-mini-panel');
            const show = panel.style.display==='block';
            els('.kvt-mini-panel', ctx.list).forEach(p=>p.style.display='none');
            panel.style.display = show?'none':'block';
            if(!b.classList.contains('kvt-name')){
              const label = b.dataset.label || 'Ver perfil';
              b.textContent = show ? label : 'Ocultar';
            }
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
        items.forEach(it=>{
          const card = ctx.list.querySelector('.kvt-card-mini[data-id="'+it.id+'"]');
          if(card){
            enableProfileEditHandlers(card, String(it.id));
            enableCvUploadHandlers(card, String(it.id));
          }
        });
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
          if(c.description) subs.push(esc(c.description));
          if(c.processes && c.processes.length) subs.push(esc(c.processes.join(', ')));
          const subHtml = subs.length?'<br><span class="kvt-sub">'+subs.join(' / ')+'</span>':'';
          return '<div class="kvt-row">'+
            '<div><span class="kvt-name">'+esc(c.name)+'</span>'+subHtml+'</div>'+
            '<div class="kvt-meta"><button type="button" class="kvt-btn kvt-edit-client" data-id="'+escAttr(c.id)+'" data-name="'+escAttr(c.name||'')+'" data-contact-name="'+escAttr(c.contact_name||'')+'" data-contact-email="'+escAttr(c.contact_email||'')+'" data-contact-phone="'+escAttr(c.contact_phone||'')+'" data-desc="'+escAttr(c.description||'')+'">Editar</button></div>'+
          '</div>';
        }).join('');
        targets.forEach(t=>t.innerHTML = html);
      });
  }

  function listProcesses(target){
    const params = new URLSearchParams();
    params.set('action','kvt_list_processes');
    params.set('_ajax_nonce', KVT_NONCE);
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
              window.KVT_PROCESS_MAP[idx].job_stage = p.job_stage;
              if(typeof p.client!=='undefined') window.KVT_PROCESS_MAP[idx].client = p.client;
              if(typeof p.client_id!=='undefined') window.KVT_PROCESS_MAP[idx].client_id = p.client_id;
            } else {
              window.KVT_PROCESS_MAP.push(p);
            }
          });
        }
        const targets = target ? [target] : [processesList, boardProcessesList].filter(Boolean);
        const html = j.data.items.map(p=>{
          const subs=[];
          if(p.client) subs.push(esc(p.client));
          if(p.contact_name) subs.push(esc(p.contact_name)+(p.contact_email?' ('+esc(p.contact_email)+')':''));
          if(p.description) subs.push(esc(p.description));
          const subHtml = subs.length?'<br><span class="kvt-sub">'+subs.join(' / ')+'</span>':'';
          return '<div class="kvt-row">'+
            '<div><span class="kvt-name">'+esc(p.name)+'</span>'+subHtml+'</div>'+
            '<div class="kvt-meta"><button type="button" class="kvt-btn kvt-edit-process" data-id="'+escAttr(p.id)+'" data-name="'+escAttr(p.name||'')+'" data-client-id="'+escAttr(p.client_id||'')+'" data-contact-name="'+escAttr(p.contact_name||'')+'" data-contact-email="'+escAttr(p.contact_email||'')+'" data-desc="'+escAttr(p.description||'')+'">Editar</button></div>'+
          '</div>';
        }).join('');
        targets.forEach(t=>t.innerHTML = html);
      });
  }

  btnAdd && btnAdd.addEventListener('click', e=>{ e.preventDefault(); openModal(); });
  // Create candidate modal
    const cmodal = el('#kvt_create_modal');
  const cclose = el('#kvt_create_close');
  const cfirst   = el('#kvt_new_first');
  const clast    = el('#kvt_new_last');
  const cemail   = el('#kvt_new_email');
  const cphone   = el('#kvt_new_phone');
  const ccountry = el('#kvt_new_country');
  const ccity    = el('#kvt_new_city');
  const ctags    = el('#kvt_new_tags');
  const ccvurl   = el('#kvt_new_cv_url');
  const ccvfile  = el('#kvt_new_cv_file');
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
    if (ctags)    ctags.value='';
    if (ccvurl)   ccvurl.value='';
    if (ccvfile)  ccvfile.value='';
    cmodal.style.display = 'flex';
  }
  function closeCModal(){ cmodal.style.display='none'; }
  cclose && cclose.addEventListener('click', closeCModal);
  cmodal && cmodal.addEventListener('click', (e)=>{ if(e.target===cmodal) closeCModal(); });
  ccli && ccli.addEventListener('change', ()=>{
    if (!window.KVT_PROCESS_MAP || !Array.isArray(window.KVT_PROCESS_MAP)) return;
    const cid = parseInt(ccli.value||'0',10);
    cproc.innerHTML = '<option value=\"\">— Proceso —</option>';
    window.KVT_PROCESS_MAP.forEach(p=>{ if(!cid || p.client_id===cid){ const o=document.createElement('option'); o.value=String(p.id); o.textContent=p.name; cproc.appendChild(o);} });
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
    const cldesc  = el('#kvt_client_desc');
    const clsubmit= el('#kvt_client_submit');
    function openClModal(){ clmodal.dataset.edit=''; clname.value=''; clcont.value=''; clemail.value=''; clphone.value=''; cldesc.value=''; clsubmit.textContent='Crear'; clmodal.style.display='flex'; }
    function openEditClModal(c){ clmodal.dataset.edit=c.id; clname.value=c.name||''; clcont.value=c.contact_name||''; clemail.value=c.contact_email||''; clphone.value=c.contact_phone||''; cldesc.value=c.description||''; clsubmit.textContent='Guardar'; clmodal.style.display='flex'; }
    function closeClModal(){ clmodal.style.display='none'; clmodal.dataset.edit=''; clsubmit.textContent='Crear'; if(cldesc) cldesc.value=''; }
    clclose && clclose.addEventListener('click', closeClModal);
    clmodal && clmodal.addEventListener('click', e=>{ if(e.target===clmodal) closeClModal(); });
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
      params.set('description', cldesc.value||'');
      fetch(KVT_AJAX,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:params.toString()})
        .then(r=>r.json()).then(j=>{
          if(!j.success) return alert(j.data && j.data.msg ? j.data.msg : 'No se pudo guardar.');
          if(editing){
            alert('Cliente actualizado (#'+editing+').');
            const cid=parseInt(editing,10); const obj=getClientById(cid); if(obj){ obj.name=clname.value||''; obj.contact_name=clcont.value||''; obj.contact_email=clemail.value||''; obj.contact_phone=clphone.value||''; obj.description=cldesc.value||''; }
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
    function openPModal(){
      pmodal.dataset.edit='';
      pname.value='';
      pcli.value='';
      if(pcontact) pcontact.value='';
      if(pemail) pemail.value='';
      if(pdesc) pdesc.value='';
      psubmit.textContent='Crear';
      pmodal.style.display='flex';
    }
    function openEditPModal(p){
      pmodal.dataset.edit=p.id;
      pname.value=p.name||'';
      pcli.value=p.client_id?String(p.client_id):'';
      if(pcontact) pcontact.value=p.contact_name||'';
      if(pemail) pemail.value=p.contact_email||'';
      if(pdesc) pdesc.value=p.description||'';
      psubmit.textContent='Guardar';
      pmodal.style.display='flex';
    }
    function closePModal(){ pmodal.style.display='none'; pmodal.dataset.edit=''; psubmit.textContent='Crear'; }
    pclose && pclose.addEventListener('click', closePModal);
    pmodal && pmodal.addEventListener('click', e=>{ if(e.target===pmodal) closePModal(); });
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
      fetch(KVT_AJAX,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:params.toString()})
        .then(r=>r.json()).then(j=>{
          if(!j.success) return alert(j.data && j.data.msg ? j.data.msg : 'No se pudo guardar.');
          if(editing){
            alert('Proceso actualizado (#'+editing+').');
            const pid=parseInt(editing,10); const obj=getProcessById(pid); if(obj){ obj.name=pname.value||''; obj.client_id=parseInt(pcli.value||'0',10); obj.contact_name=pcontact?pcontact.value:''; obj.contact_email=pemail?pemail.value:''; obj.description=pdesc?pdesc.value:''; }
            const opt = selProcess ? selProcess.querySelector('option[value="'+pid+'"]') : null; if(opt) opt.textContent = pname.value||'';
            closePModal(); listProcesses(); updateSelectedInfo();
          } else {
            alert('Proceso creado (#'+j.data.id+').');
            closePModal(); location.reload();
          }
        });
    });

    const handleClientClick = e=>{
      const btn = e.target.closest('.kvt-edit-client');
      if(!btn) return;
      let data = getClientById(btn.dataset.id);
      if(!data){
        data = {
          id: parseInt(btn.dataset.id,10),
          name: btn.dataset.name || '',
          contact_name: btn.dataset.contactName || '',
          contact_email: btn.dataset.contactEmail || '',
          contact_phone: btn.dataset.contactPhone || '',
          description: btn.dataset.desc || ''
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
          description: btn.dataset.desc || ''
        };
      }
      openEditPModal(data);
    };
    clientsList && clientsList.addEventListener('click', handleClientClick);
    boardClientsList && boardClientsList.addEventListener('click', handleClientClick);
    processesList && processesList.addEventListener('click', handleProcessClick);
    boardProcessesList && boardProcessesList.addEventListener('click', handleProcessClick);
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
    infoBody && infoBody.addEventListener('click', handleInlineEdit);

    // Nuevo menu actions
    btnNew && btnNew.addEventListener('click', ()=>{ newMenu.style.display = newMenu.style.display==='flex' ? 'none' : 'flex'; });
    document.addEventListener('click', e=>{ if(!btnNew.contains(e.target) && !newMenu.contains(e.target)) newMenu.style.display='none'; });
    els('#kvt_new_menu button').forEach(b=>{
      b.addEventListener('click', e=>{
        e.preventDefault();
        e.stopPropagation();
        const act = b.dataset.action;
        newMenu.style.display='none';
        setTimeout(()=>{
          if(act==='candidate') openCModal();
          if(act==='client') openClModal();
          if(act==='process') openPModal();
        },0);
      });
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

        $client_id  = isset($_POST['client'])  ? intval($_POST['client'])  : 0;
        $process_id = isset($_POST['process']) ? intval($_POST['process']) : 0;
        $search     = isset($_POST['search'])  ? trim(sanitize_text_field($_POST['search'])) : '';
        $page       = isset($_POST['page'])    ? max(1, intval($_POST['page'])) : 1;

        $base_mode = !$client_id && !$process_id;

        $tax_query = [];
        if (!$base_mode) {
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
        }

        $per_page = $base_mode ? 10 : 999;
        $args = [
            'post_type'      => self::CPT,
            'post_status'    => 'any',
            'posts_per_page' => $per_page,
            'paged'          => $page,
            'no_found_rows'  => $base_mode ? false : true,
        ];
        if (!empty($tax_query)) $args['tax_query'] = $tax_query;

        if ($search !== '') {
            $args['meta_query'] = [
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

        $q = new WP_Query($args);
        $data = [];
        foreach ($q->posts as $p) {
            $notes_raw = get_post_meta($p->ID,'kvt_notes',true);
            if ($notes_raw === '') $notes_raw = get_post_meta($p->ID,'notes',true);
            $public_notes_raw = get_post_meta($p->ID,'kvt_public_notes',true);
            if ($public_notes_raw === '') $public_notes_raw = get_post_meta($p->ID,'public_notes',true);
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
                'public_notes'       => $public_notes_raw,
                'public_notes_count' => $this->count_notes($public_notes_raw),
                'tags'        => $this->meta_get_compat($p->ID,'kvt_tags',['tags']),
                'client_comments' => get_post_meta($p->ID,'kvt_client_comments',true),
                'activity_log' => get_post_meta($p->ID,'kvt_activity_log',true),
            ];
            $data[] = [
                'id'     => $p->ID,
                'title'  => get_the_title($p),
                'status' => get_post_meta($p->ID,'kvt_status',true),
                'meta'   => $meta,
            ];
        }
        $pages = $base_mode ? $q->max_num_pages : 1;
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
                            'comment'      => $cc['comment'],
                            'index'        => $idx,
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
        $note = isset($_POST['note']) ? sanitize_text_field($_POST['note']) : '';
        $author = isset($_POST['author']) ? sanitize_text_field($_POST['author']) : '';
        if(!$author){
            $u = wp_get_current_user();
            if($u && $u->exists()) $author = $u->display_name;
        }
        if (!$id || !$date) wp_send_json_error(['msg'=>'Invalid'], 400);
        update_post_meta($id, 'kvt_next_action', $date);
        update_post_meta($id, 'next_action', $date);
        update_post_meta($id, 'kvt_next_action_note', $note);
        update_post_meta($id, 'next_action_note', $note);
        $log = get_post_meta($id, 'kvt_activity_log', true);
        if(!is_array($log)) $log = [];
        $log[] = [
            'type'  => 'task_add',
            'date'  => $date,
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
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $notes = isset($_POST['notes']) ? wp_kses_post($_POST['notes']) : '';
        if (!$id) wp_send_json_error(['msg'=>'Invalid'], 400);
        update_post_meta($id, 'kvt_notes', $notes);
        update_post_meta($id, 'notes', $notes);
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
            'kvt_tags'       => isset($_POST['tags'])       ? sanitize_text_field($_POST['tags'])       : '',
            'kvt_cv_url'     => isset($_POST['cv_url'])     ? esc_url_raw($_POST['cv_url'])             : '',
            'kvt_cv_uploaded'=> isset($_POST['cv_uploaded'])? sanitize_text_field($_POST['cv_uploaded']): '',
            'kvt_next_action'=> isset($_POST['next_action'])? sanitize_text_field($_POST['next_action']): '',
            'kvt_next_action_note'=> isset($_POST['next_action_note'])? sanitize_text_field($_POST['next_action_note']): '',
            'kvt_notes'      => isset($_POST['notes'])      ? wp_kses_post($_POST['notes'])             : '',
            'kvt_public_notes' => isset($_POST['public_notes']) ? wp_kses_post($_POST['public_notes']) : '',
        ];
        if ($fields['kvt_cv_uploaded']) $fields['kvt_cv_uploaded'] = $this->fmt_date_ddmmyyyy($fields['kvt_cv_uploaded']);
        if ($fields['kvt_next_action']) $fields['kvt_next_action'] = $this->fmt_date_ddmmyyyy($fields['kvt_next_action']);

        foreach ($fields as $k=>$v) {
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
            $meta_query[] = [
                'relation' => 'OR',
                ['key'=>'kvt_current_role','value'=>$role,'compare'=>'LIKE'],
                ['key'=>'current_role','value'=>$role,'compare'=>'LIKE'],
            ];
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
            $public_notes_raw = get_post_meta($p->ID,'kvt_public_notes',true);
            if ($public_notes_raw === '') $public_notes_raw = get_post_meta($p->ID,'public_notes',true);
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
                    'public_notes'       => $public_notes_raw,
                    'public_notes_count' => $this->count_notes($public_notes_raw),
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
                'processes'     => wp_list_pluck($procs,'name'),
                'edit_url'      => admin_url('term.php?taxonomy=' . self::TAX_CLIENT . '&tag_ID=' . $t->term_id),
            ];
        }
        wp_send_json_success(['items'=>$items]);
    }

    public function ajax_list_processes() {
        check_ajax_referer('kvt_nonce');
        $terms = get_terms(['taxonomy'=>self::TAX_PROCESS,'hide_empty'=>false]);
        $items = [];
        $statuses = array_values(array_filter(array_map('trim', explode("\n", get_option(self::OPT_STATUSES, '')))));
        foreach ($terms as $t) {
            $client_id = (int) get_term_meta($t->term_id,'kvt_process_client',true);
            $client_name = $client_id ? get_term($client_id)->name : '';
            $creator_id = (int) get_term_meta($t->term_id,'kvt_process_creator',true);
            $creator = '';
            if ($creator_id) {
                $u = get_user_by('id', $creator_id);
                if ($u) $creator = $u->display_name;
            }
            $created = get_term_meta($t->term_id,'kvt_process_created',true);

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
            if ($max >= 0 && isset($statuses[$max])) $job_stage = $statuses[$max];

            $items[] = [
                'id' => $t->term_id,
                'name' => $t->name,
                'client_id' => $client_id,
                'client' => $client_name,
                'contact_name'  => get_term_meta($t->term_id,'contact_name',true),
                'contact_email' => get_term_meta($t->term_id,'contact_email',true),
                'description'   => $t->description,
                'creator'       => $creator,
                'created'       => $created,
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
        $email      = isset($_POST['email'])      ? sanitize_email($_POST['email'])           : '';
        $phone      = isset($_POST['phone'])      ? sanitize_text_field($_POST['phone'])      : '';
        $country    = isset($_POST['country'])    ? sanitize_text_field($_POST['country'])    : '';
        $city       = isset($_POST['city'])       ? sanitize_text_field($_POST['city'])       : '';
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
            'kvt_tags'       => $tags,
            'kvt_cv_url'     => $cv_url,
        ];
        foreach ($fields as $k => $v) {
            update_post_meta($new_id, $k, $v);
            update_post_meta($new_id, str_replace('kvt_','',$k), $v);
        }
        $statuses = $this->get_statuses();
        if (!empty($statuses)) update_post_meta($new_id,'kvt_status',$statuses[0]);
        if ($client_id) wp_set_object_terms($new_id, [$client_id], self::TAX_CLIENT, false);
        if ($process_id) wp_set_object_terms($new_id, [$process_id], self::TAX_PROCESS, false);

      wp_send_json_success(['id'=>$new_id]);
      }

      public function ajax_create_client() {
          check_ajax_referer('kvt_nonce');

          $name  = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
          $cname = isset($_POST['contact_name'])  ? sanitize_text_field($_POST['contact_name'])  : '';
          $cemail= isset($_POST['contact_email']) ? sanitize_email($_POST['contact_email'])      : '';
          $cphone= isset($_POST['contact_phone']) ? sanitize_text_field($_POST['contact_phone']) : '';
          $desc  = isset($_POST['description']) ? sanitize_textarea_field($_POST['description']) : '';

          if ($name === '') wp_send_json_error(['msg'=>'Nombre requerido'],400);

          $term = wp_insert_term($name, self::TAX_CLIENT, ['description'=>$desc]);
          if (is_wp_error($term)) wp_send_json_error(['msg'=>$term->get_error_message()],500);
          $tid = (int) $term['term_id'];
          update_term_meta($tid, 'contact_name', $cname);
          update_term_meta($tid, 'contact_email', $cemail);
          update_term_meta($tid, 'contact_phone', $cphone);

          wp_send_json_success(['id'=>$tid]);
      }

      public function ajax_create_process() {
          check_ajax_referer('kvt_nonce');

        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        $client_id = isset($_POST['client_id']) ? intval($_POST['client_id']) : 0;
        $contact_name  = isset($_POST['contact_name']) ? sanitize_text_field($_POST['contact_name']) : '';
        $contact_email = isset($_POST['contact_email']) ? sanitize_email($_POST['contact_email']) : '';
        $desc = isset($_POST['description']) ? sanitize_textarea_field($_POST['description']) : '';
        if ($name === '') wp_send_json_error(['msg'=>'Nombre requerido'],400);

        $term = wp_insert_term($name, self::TAX_PROCESS, ['description'=>$desc]);
        if (is_wp_error($term)) wp_send_json_error(['msg'=>$term->get_error_message()],500);
        $tid = (int) $term['term_id'];
        if ($client_id) update_term_meta($tid, 'kvt_process_client', $client_id);
        if ($contact_name)  update_term_meta($tid, 'contact_name', $contact_name);
        if ($contact_email) update_term_meta($tid, 'contact_email', $contact_email);

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

        if (!$id) wp_send_json_error(['msg'=>'ID inválido'],400);

        $term = wp_update_term($id, self::TAX_CLIENT, ['name'=>$name, 'description'=>$desc]);
        if (is_wp_error($term)) wp_send_json_error(['msg'=>$term->get_error_message()],500);

        update_term_meta($id, 'contact_name', $cname);
        update_term_meta($id, 'contact_email', $cemail);
        update_term_meta($id, 'contact_phone', $cphone);

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

        if (!$id) wp_send_json_error(['msg'=>'ID inválido'],400);

        $term = wp_update_term($id, self::TAX_PROCESS, ['name'=>$name, 'description'=>$desc]);
        if (is_wp_error($term)) wp_send_json_error(['msg'=>$term->get_error_message()],500);

        if ($client_id) update_term_meta($id, 'kvt_process_client', $client_id); else delete_term_meta($id, 'kvt_process_client');
        update_term_meta($id, 'contact_name', $contact_name);
        update_term_meta($id, 'contact_email', $contact_email);

        wp_send_json_success(['id'=>$id]);
    }

    public function ajax_ai_search() {
        check_ajax_referer('kvt_nonce');

        $desc = isset($_POST['description']) ? sanitize_textarea_field($_POST['description']) : '';
        if (!$desc) wp_send_json_error(['msg' => 'Descripción vacía'], 400);

        $key = get_option(self::OPT_OPENAI_KEY, '');
        if (!$key) wp_send_json_error(['msg' => 'Falta la clave'], 400);

        $candidates = get_posts(['post_type' => self::CPT, 'posts_per_page' => -1]);
        $items = [];
        foreach ($candidates as $c) {
            $cv_text = $this->get_candidate_cv_text($c->ID);
            if (!$cv_text) continue;
            $res = $this->openai_match_summary($key, $desc, $cv_text);
            if ($res) {
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
                $items[] = [
                    'id'      => $c->ID,
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

    public function ajax_generate_share_link() {
        check_ajax_referer('kvt_nonce');

        $client  = isset($_POST['client'])  ? intval($_POST['client'])  : 0;
        $process = isset($_POST['process']) ? intval($_POST['process']) : 0;
        $page    = isset($_POST['page'])    ? sanitize_text_field(wp_unslash($_POST['page'])) : '';
        $fields  = isset($_POST['fields'])  ? array_map('sanitize_text_field', (array) $_POST['fields']) : [];
        $steps   = isset($_POST['steps'])   ? array_map('sanitize_text_field', (array) $_POST['steps']) : [];
        $allow_comments = !empty($_POST['comments']);
        $slug   = isset($_POST['slug'])     ? sanitize_text_field(wp_unslash($_POST['slug'])) : '';

        if (!$client || !$process) {
            wp_send_json_error(['msg' => 'missing'], 400);
        }

        $links = get_option('kvt_client_links', []);
        if ($slug && isset($links[$slug])) {
            $links[$slug] = [
                'client'  => $client,
                'process' => $process,
                'fields'  => $fields,
                'steps'   => $steps,
                'page'    => $page,
                'comments'=> $allow_comments ? 1 : 0,
            ];
        } else {
            $client_term  = get_term($client, self::TAX_CLIENT);
            $process_term = get_term($process, self::TAX_PROCESS);
            $cslug = $client_term ? sanitize_title($client_term->name) : 'cliente';
            $pslug = $process_term ? sanitize_title($process_term->name) : 'proceso';
            $rand  = wp_rand(10000, 99999);
            $slug  = $cslug . '-' . $pslug . '-' . $rand;
            $links[$slug] = [
                'client'  => $client,
                'process' => $process,
                'fields'  => $fields,
                'steps'   => $steps,
                'page'    => $page,
                'comments'=> $allow_comments ? 1 : 0,
            ];
        }
        update_option('kvt_client_links', $links, false);

        wp_send_json_success(['slug' => $slug]);
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
        $found = false;
        foreach ($existing as &$cc) {
            if (isset($cc['slug']) && $cc['slug'] === $slug) {
                $cc['name']    = $name;
                $cc['comment'] = $comment;
                $cc['date']    = current_time('mysql');
                $found = true;
                break;
            }
        }
        unset($cc);
        if (!$found) {
            $existing[] = [
                'name'    => $name,
                'comment' => $comment,
                'date'    => current_time('mysql'),
                'slug'    => $slug,
            ];
        }
        update_post_meta($id, 'kvt_client_comments', $existing);
        wp_send_json_success(['name'=>$name,'comment'=>$comment]);
    }

    public function maybe_redirect_share_link() {
        if (isset($_GET['kvt_board'])) return;
        $req  = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
        if ($req === '') return;
        $parts = explode('/', $req);
        $slug  = end($parts);
        if (!preg_match('/^[a-z0-9-]+-[a-z0-9-]+-\d{5}$/i', $slug)) return;
        $links = get_option('kvt_client_links', []);
        if (!isset($links[$slug])) return;
        $target = home_url('/base/?kvt_board=' . $slug);
        wp_redirect($target);
        exit;
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
                ['role' => 'system', 'content' => 'Eres un asistente que extrae del CV nombre, apellidos, email, teléfono, país actual, ciudad actual, puesto y empresa actuales. Devuelve JSON con las claves "first_name","last_name","email","phone","country","city","role" y "company". Si falta algún dato devuelve campo vacío.'],
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
            if ($this->meta_get_compat($post_id, $meta, [substr($meta,4)]) === '') {
                update_post_meta($post_id, $meta, $val);
                update_post_meta($post_id, substr($meta,4), $val);
                $updated[$field] = $val;
            }
        }
        $role = isset($data['role']) ? trim($data['role']) : '';
        $company = isset($data['company']) ? trim($data['company']) : '';
        $role_combined = '';
        if ($role && $company) $role_combined = $role . ' at ' . $company;
        elseif ($role) $role_combined = $role;
        elseif ($company) $role_combined = $company;
        if ($role_combined && $this->meta_get_compat($post_id, 'kvt_current_role', ['current_role']) === '') {
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

          wp_send_json_success(['id'=>$id]);
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
