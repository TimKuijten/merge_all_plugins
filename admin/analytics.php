<?php
if (!defined('ABSPATH')) exit;
$from = isset($_GET['from']) ? sanitize_text_field($_GET['from']) : '';
$to = isset($_GET['to']) ? sanitize_text_field($_GET['to']) : '';
$process = isset($_GET['process']) ? intval($_GET['process']) : 0;
$recruiter = isset($_GET['recruiter']) ? intval($_GET['recruiter']) : 0;

$args = [
    'post_type' => Kovacic_Pipeline_Visualizer::CPT,
    'post_status' => 'any',
    'numberposts' => -1,
];
if ($from || $to) {
    $date_query = [];
    if ($from) $date_query['after'] = $from;
    if ($to) $date_query['before'] = $to;
    $args['date_query'] = [$date_query];
}
$tax_query = [];
if ($process) {
    $tax_query[] = [
        'taxonomy' => Kovacic_Pipeline_Visualizer::TAX_PROCESS,
        'field' => 'term_id',
        'terms' => $process,
    ];
}
if ($tax_query) $args['tax_query'] = $tax_query;
if ($recruiter) $args['author'] = $recruiter;

$posts = get_posts($args);
$by_status = [];
$hire_times = [];
$by_source = [];
foreach ($posts as $p) {
    $status = get_post_meta($p->ID, 'kvt_status', true);
    if (!$status) $status = 'Desconocido';
    $by_status[$status] = ($by_status[$status] ?? 0) + 1;
    $source = get_post_meta($p->ID, 'source', true);
    if ($source) $by_source[$source] = ($by_source[$source] ?? 0) + 1;
    if ($status === 'Contratado') {
        $created = strtotime($p->post_date_gmt ? $p->post_date_gmt : $p->post_date);
        $hired   = strtotime(get_post_meta($p->ID, 'kvt_hired_date', true));
        if ($created && $hired) $hire_times[] = ($hired - $created) / DAY_IN_SECONDS;
    }
}
$avg_time = $hire_times ? array_sum($hire_times) / count($hire_times) : 0;

$processes = get_terms(['taxonomy' => Kovacic_Pipeline_Visualizer::TAX_PROCESS, 'hide_empty' => false]);
$users = get_users();
?>
<div class="wrap">
<h1><?php esc_html_e('Analytics','kovacic'); ?></h1>
<form method="get">
    <input type="hidden" name="page" value="kvt-analytics">
    <label><?php esc_html_e('Desde','kovacic'); ?> <input type="date" name="from" value="<?php echo esc_attr($from); ?>"></label>
    <label><?php esc_html_e('Hasta','kovacic'); ?> <input type="date" name="to" value="<?php echo esc_attr($to); ?>"></label>
    <label><?php esc_html_e('Proceso','kovacic'); ?>
        <select name="process">
            <option value="0">—</option>
            <?php foreach ($processes as $pr): ?>
            <option value="<?php echo esc_attr($pr->term_id); ?>" <?php selected($process, $pr->term_id); ?>><?php echo esc_html($pr->name); ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label><?php esc_html_e('Recruiter','kovacic'); ?>
        <select name="recruiter">
            <option value="0">—</option>
            <?php foreach ($users as $u): ?>
            <option value="<?php echo esc_attr($u->ID); ?>" <?php selected($recruiter, $u->ID); ?>><?php echo esc_html($u->display_name); ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <?php submit_button(__('Filtrar','kovacic'), 'secondary', '', false); ?>
</form>

<p><?php printf(__('Tiempo medio de contratación: %s días', 'kovacic'), number_format_i18n($avg_time, 2)); ?></p>

<canvas id="kvt_analytics_status" width="400" height="200"></canvas>
<canvas id="kvt_analytics_source" width="400" height="200"></canvas>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded',function(){
  const ctx=document.getElementById('kvt_analytics_status').getContext('2d');
  new Chart(ctx,{
    type:'bar',
    data:{
      labels: <?php echo wp_json_encode(array_keys($by_status)); ?>,
      datasets:[{label:'Candidatos',data:<?php echo wp_json_encode(array_values($by_status)); ?>,backgroundColor:'rgba(54,162,235,0.5)'}]
    },
    options:{responsive:true}
  });
  const ctx2=document.getElementById('kvt_analytics_source').getContext('2d');
  new Chart(ctx2,{
    type:'pie',
    data:{
      labels: <?php echo wp_json_encode(array_keys($by_source)); ?>,
      datasets:[{data:<?php echo wp_json_encode(array_values($by_source)); ?>,backgroundColor:['#60a5fa','#34d399','#f87171','#a78bfa','#fbbf24']}]
    },
    options:{responsive:true}
  });
});
</script>
