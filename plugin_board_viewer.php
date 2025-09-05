<?php
/*
Plugin Name: KVT Board Viewer
Description: Public viewer for Kovacic Pipeline boards via /view-board/<slug> URLs.
Version: 1.0.0
Author: Kovacic Executive Talent Research
*/

if (!defined('ABSPATH')) exit;

class KVT_Board_Viewer {
    public function __construct() {
        add_action('init', [$this, 'add_rewrite']);
        add_action('template_redirect', [$this, 'render_board']);
        register_activation_hook(__FILE__, [$this, 'flush']);
        register_deactivation_hook(__FILE__, [$this, 'flush']);
    }

    public function add_rewrite() {
        add_rewrite_rule('^view-board/([^/]+)/?$', 'index.php?kvt_board=$matches[1]', 'top');
        add_filter('query_vars', function($vars){
            $vars[] = 'kvt_board';
            return $vars;
        });
    }

    public function render_board() {
        $slug = get_query_var('kvt_board');
        if (!$slug) return;
        status_header(200);
        nocache_headers();
        echo '<!DOCTYPE html><html><head><meta charset="utf-8">';
        wp_head();
        echo '<style>.kvt-nav,.kvt-header,.kvt-help,#kvt_board_wrap,#kvt_toggle_kanban,#kvt_filters_bar,#kvt_ats_bar,#kvt_board_base,#kvt_stage_overview,#k-toggle-activity,.k-sideactions,#kvt_active_wrap,#kvt_calendar_wrap,.kvt-widgets{display:none!important;}#k-sidebar{display:none;width:100%!important;margin-top:20px;}.kvt-wrapper{display:block!important;width:100%!important;max-width:none!important;margin:0!important;}.kvt-content{width:100%!important;padding:0!important;}.kvt-main{display:block!important;}#kvt_table_wrap{display:block!important;flex:1 1 100%!important;width:100%!important;overflow:visible!important;}.kvt-table-wrap{overflow:visible!important;max-height:none!important;}body{overflow-x:hidden!important;}</style>';
        echo '</head><body>';
        echo do_shortcode('[kvt_pipeline]');
        wp_footer();
        echo '<script>document.addEventListener("DOMContentLoaded",function(){var b=document.getElementById("kvt_board_wrap");if(b)b.style.display="none";var t=document.getElementById("kvt_table_wrap");if(t)t.style.display="block";var n=document.querySelector(".kvt-nav");if(n)n.style.display="none";var h=document.querySelector(".kvt-header");if(h)h.style.display="none";var hl=document.querySelector(".kvt-help");if(hl)hl.style.display="none";var tk=document.getElementById("kvt_toggle_kanban");if(tk)tk.style.display="none";var fb=document.getElementById("kvt_filters_bar");if(fb)fb.style.display="none";var ab=document.getElementById("kvt_ats_bar");if(ab)ab.style.display="none";var bb=document.getElementById("kvt_board_base");if(bb)bb.style.display="none";var so=document.getElementById("kvt_stage_overview");if(so)so.style.display="none";var log=document.getElementById("kvt_activity")||document.getElementById("k-sidebar");if(log)log.style.display="none";var head=log?log.querySelector(".kvt-widget-title")||log.querySelector(".k-sidehead"):null;if(head)head.textContent="History";var tabs=log?log.querySelector(".kvt-activity-tabs"):null;if(tabs)tabs.style.display="none";var tasks=document.getElementById("kvt_activity_tasks");if(tasks)tasks.style.display="none";var logPane=document.getElementById("kvt_activity_log");if(logPane)logPane.style.display="block";var sideActions=log?log.querySelector(".k-sideactions"):null;if(sideActions)sideActions.style.display="none";var pager=document.getElementById("kvt_table_pager")||document.querySelector(".k-pager");if(pager){var btn=document.createElement("button");btn.id="k-show-log";btn.textContent="History";btn.className="kvt-btn btn";pager.insertAdjacentElement("afterend",btn);if(log)btn.insertAdjacentElement("afterend",log);btn.addEventListener("click",function(){if(log){log.style.display=log.style.display==="none"?"block":"none";}});}});</script>';
        echo '</body></html>';
        exit;
    }

    public function flush() {
        flush_rewrite_rules();
    }
}

new KVT_Board_Viewer();
