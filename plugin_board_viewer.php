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
        echo '<style>.kvt-nav,.kvt-header,.kvt-help,#kvt_board_wrap,#kvt_toggle_kanban,#kvt_filters_bar,#kvt_ats_bar,#kvt_board_base,#kvt_stage_overview{display:none!important;}#kvt_table_wrap{display:block!important;}</style>';
        echo '</head><body>';
        echo do_shortcode('[kvt_pipeline]');
        wp_footer();
        echo '<script>document.addEventListener("DOMContentLoaded",function(){var b=document.getElementById("kvt_board_wrap");if(b)b.style.display="none";var t=document.getElementById("kvt_table_wrap");if(t)t.style.display="block";var n=document.querySelector(".kvt-nav");if(n)n.style.display="none";var h=document.querySelector(".kvt-header");if(h)h.style.display="none";var hl=document.querySelector(".kvt-help");if(hl)hl.style.display="none";var tk=document.getElementById("kvt_toggle_kanban");if(tk)tk.style.display="none";var fb=document.getElementById("kvt_filters_bar");if(fb)fb.style.display="none";var ab=document.getElementById("kvt_ats_bar");if(ab)ab.style.display="none";var bb=document.getElementById("kvt_board_base");if(bb)bb.style.display="none";var so=document.getElementById("kvt_stage_overview");if(so)so.style.display="none";});</script>';
        echo '</body></html>';
        exit;
    }

    public function flush() {
        flush_rewrite_rules();
    }
}

new KVT_Board_Viewer();
