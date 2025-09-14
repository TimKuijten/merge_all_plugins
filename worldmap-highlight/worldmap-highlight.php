<?php
/**
 * Plugin Name: World Map Highlight
 * Description: Displays a gray world map with selected countries highlighted.
 * Version: 1.0.0
 * Author: ChatGPT
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Register settings
function wmh_register_settings() {
    register_setting('wmh_options_group', 'wmh_countries');
}
add_action('admin_init', 'wmh_register_settings');

// Add settings page
function wmh_add_settings_page() {
    add_options_page(
        'World Map Highlight',
        'World Map Highlight',
        'manage_options',
        'wmh-settings',
        'wmh_render_settings_page'
    );
}
add_action('admin_menu', 'wmh_add_settings_page');

function wmh_render_settings_page() {
    ?>
    <div class="wrap">
        <h1>World Map Highlight</h1>
        <form method="post" action="options.php">
            <?php settings_fields('wmh_options_group'); ?>
            <p>Enter one country per line in the format <code>CODE|Information</code>. Example: <code>US|Information about USA</code>.</p>
            <textarea name="wmh_countries" rows="10" cols="50" class="large-text"><?php echo esc_textarea(get_option('wmh_countries')); ?></textarea>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

// Register scripts and styles
function wmh_register_assets() {
    wp_register_style('wmh-jvectormap', 'https://cdnjs.cloudflare.com/ajax/libs/jvectormap/2.0.5/jquery-jvectormap.css');
    wp_register_script('wmh-jvectormap', 'https://cdnjs.cloudflare.com/ajax/libs/jvectormap/2.0.5/jquery-jvectormap.min.js', array('jquery'), null, true);
    wp_register_script('wmh-jvectormap-world', 'https://cdnjs.cloudflare.com/ajax/libs/jvectormap/2.0.5/maps/world_mill_en.min.js', array('wmh-jvectormap'), null, true);
    wp_register_script('wmh-script', plugins_url('wmh.js', __FILE__), array('jquery', 'wmh-jvectormap-world'), null, true);
}
add_action('wp_enqueue_scripts', 'wmh_register_assets');

// Shortcode to display map
function wmh_render_map() {
    $raw = get_option('wmh_countries', '');
    $countries = array();
    foreach (preg_split("/(\r?\n)/", $raw) as $line) {
        if (strpos($line, '|') !== false) {
            list($code, $info) = array_map('trim', explode('|', $line, 2));
            $code = strtoupper($code);
            if ($code) {
                $countries[$code] = $info;
            }
        }
    }

    wp_enqueue_style('wmh-jvectormap');
    wp_enqueue_script('wmh-jvectormap');
    wp_enqueue_script('wmh-jvectormap-world');
    wp_localize_script('wmh-script', 'wmhData', array('countries' => $countries));
    wp_enqueue_script('wmh-script');

    return '<div id="wmh-map" style="width: 600px; height: 400px;"></div>';
}
add_shortcode('worldmap', 'wmh_render_map');
