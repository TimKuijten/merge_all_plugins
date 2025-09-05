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
        echo '</head><body>';
        echo do_shortcode('[kvt_pipeline]');
        wp_footer();
        echo '</body></html>';
        exit;
    }

    public function flush() {
        flush_rewrite_rules();
    }
}

new KVT_Board_Viewer();
