<?php
/**
 * Plugin Name: SMTP Helper
 * Description: Simple plugin to configure SMTP settings for WordPress.
 * Version: 1.2.0
 * Author: ChatGPT
 */

if (!defined('ABSPATH')) {
    exit;
}

function smtp_helper_add_menu() {
    add_options_page(
        'SMTP Helper',
        'SMTP Helper',
        'manage_options',
        'smtp-helper',
        'smtp_helper_render_settings_page'
    );
}
add_action('admin_menu', 'smtp_helper_add_menu');

function smtp_helper_register_settings() {
    register_setting('smtp_helper_options_group', 'smtp_helper_options', 'smtp_helper_sanitize');
}
add_action('admin_init', 'smtp_helper_register_settings');

function smtp_helper_render_settings_page() {
    $options = get_option('smtp_helper_options', array());
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('SMTP Helper Settings', 'smtp-helper'); ?></h1>
        <form method="post" action="options.php">
            <?php settings_fields('smtp_helper_options_group'); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="smtp_helper_host"><?php esc_html_e('SMTP Host', 'smtp-helper'); ?></label></th>
                    <td><input name="smtp_helper_options[host]" id="smtp_helper_host" type="text" value="<?php echo esc_attr($options['host'] ?? ''); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="smtp_helper_port"><?php esc_html_e('SMTP Port', 'smtp-helper'); ?></label></th>
                    <td><input name="smtp_helper_options[port]" id="smtp_helper_port" type="number" value="<?php echo esc_attr($options['port'] ?? ''); ?>" class="small-text" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="smtp_helper_encryption"><?php esc_html_e('Encryption', 'smtp-helper'); ?></label></th>
                    <td>
                        <?php $enc = $options['encryption'] ?? ''; ?>
                        <select name="smtp_helper_options[encryption]" id="smtp_helper_encryption">
                            <option value="" <?php selected($enc, ''); ?>><?php esc_html_e('None', 'smtp-helper'); ?></option>
                            <option value="ssl" <?php selected($enc, 'ssl'); ?>>SSL</option>
                            <option value="tls" <?php selected($enc, 'tls'); ?>>TLS</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="smtp_helper_username"><?php esc_html_e('Username', 'smtp-helper'); ?></label></th>
                    <td><input name="smtp_helper_options[username]" id="smtp_helper_username" type="text" value="<?php echo esc_attr($options['username'] ?? ''); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="smtp_helper_password"><?php esc_html_e('Password', 'smtp-helper'); ?></label></th>
                    <td><input name="smtp_helper_options[password]" id="smtp_helper_password" type="password" value="<?php echo esc_attr($options['password'] ?? ''); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="smtp_helper_from_email"><?php esc_html_e('From Email', 'smtp-helper'); ?></label></th>
                    <td><input name="smtp_helper_options[from_email]" id="smtp_helper_from_email" type="email" value="<?php echo esc_attr($options['from_email'] ?? ''); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="smtp_helper_from_name"><?php esc_html_e('From Name', 'smtp-helper'); ?></label></th>
                    <td><input name="smtp_helper_options[from_name]" id="smtp_helper_from_name" type="text" value="<?php echo esc_attr($options['from_name'] ?? ''); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="smtp_helper_signature"><?php esc_html_e('Signature (HTML)', 'smtp-helper'); ?></label></th>
                    <td><textarea name="smtp_helper_options[signature]" id="smtp_helper_signature" rows="5" class="large-text code"><?php echo esc_textarea($options['signature'] ?? ''); ?></textarea></td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

function smtp_helper_sanitize($input) {
    $output = array();
    $output['host']       = sanitize_text_field($input['host'] ?? '');
    $output['port']       = intval($input['port'] ?? 0);
    $output['encryption'] = sanitize_text_field($input['encryption'] ?? '');
    $output['username']   = sanitize_text_field($input['username'] ?? '');
    $output['password']   = sanitize_text_field($input['password'] ?? '');
    $output['from_email'] = sanitize_email($input['from_email'] ?? '');
    $output['from_name']  = sanitize_text_field($input['from_name'] ?? '');
    $output['signature']  = wp_kses_post($input['signature'] ?? '');
    return $output;
}

function smtp_helper_phpmailer_init($phpmailer) {
    $options = get_option('smtp_helper_options');
    if (empty($options) || empty($options['host'])) {
        return;
    }

    $phpmailer->isSMTP();
    $phpmailer->Host = $options['host'];
    if (!empty($options['port'])) {
        $phpmailer->Port = $options['port'];
    }
    if (!empty($options['username'])) {
        $phpmailer->SMTPAuth = true;
        $phpmailer->Username = $options['username'];
        $phpmailer->Password = $options['password'];
    }
    if (!empty($options['encryption'])) {
        $phpmailer->SMTPSecure = $options['encryption'];
    }
    if (!empty($options['from_email'])) {
        $phpmailer->setFrom($options['from_email'], $options['from_name'] ?? '');
    }
}
add_action('phpmailer_init', 'smtp_helper_phpmailer_init');
