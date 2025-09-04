<?php
/*
Plugin Name: Kovacic Client Board Viewer
Description: Public viewer for Kovacic pipeline boards.
Version: 1.0.0
Author: Tim Kuijten - Kovacic Executive Talent Research
*/

if (!defined('ABSPATH')) exit;

/**
 * Shortcode handler for [kvt_public_board].
 * Validates the requested board slug and reuses the main pipeline shortcode
 * to render the Kanban board for public viewers.
 */
function kvt_public_board_shortcode($atts = []) {
    $slug  = isset($_GET['kvt_board']) ? sanitize_text_field(wp_unslash($_GET['kvt_board'])) : '';
    $links = get_option('kvt_client_links', []);
    if (!$slug || !isset($links[$slug])) {
        return '<div class="kvt-wrapper"><p>Tablero no disponible.</p></div>';
    }
    return do_shortcode('[kovacic_pipeline]');
}
add_shortcode('kvt_public_board', 'kvt_public_board_shortcode');

/**
 * Ensure the same CSS/JS assets used by the pipeline plugin are loaded.
 */
function kvt_public_board_enqueue_assets() {
    if (wp_style_is('kvt-style', 'registered')) {
        wp_enqueue_style('kvt-style');
    }
    if (wp_script_is('kvt-app', 'registered')) {
        wp_enqueue_script('kvt-app');
    }
}
add_action('wp_enqueue_scripts', 'kvt_public_board_enqueue_assets');

/**
 * Register /view-board/ endpoint where the shortcode renders.
 */
function kvt_public_board_rewrite() {
    add_rewrite_rule('^view-board/?$', 'index.php?kvt_public_board=1', 'top');
}
add_action('init', 'kvt_public_board_rewrite');

function kvt_public_board_query_vars($vars) {
    $vars[] = 'kvt_public_board';
    return $vars;
}
add_filter('query_vars', 'kvt_public_board_query_vars');

function kvt_public_board_template_redirect() {
    if (get_query_var('kvt_public_board')) {
        status_header(200);
        nocache_headers();
        echo '<!DOCTYPE html><html><head>';
        wp_head();
        echo '</head><body>';
        echo do_shortcode('[kvt_public_board]');
        wp_footer();
        echo '</body></html>';
        exit;
    }
}
add_action('template_redirect', 'kvt_public_board_template_redirect');

