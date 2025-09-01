<?php
if (!defined('ABSPATH')) exit;
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
