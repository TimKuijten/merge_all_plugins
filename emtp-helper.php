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
    register_setting('smtp_helper_options_group', 'smtp_helper_accounts', 'smtp_helper_sanitize_accounts');
}
add_action('admin_init', 'smtp_helper_register_settings');

function smtp_helper_render_settings_page() {
    $accounts = get_option('smtp_helper_accounts', array());
    if (!is_array($accounts) || empty($accounts)) {
        $accounts = array(array());
    }
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('SMTP Helper Settings', 'smtp-helper'); ?></h1>
        <form method="post" action="options.php">
            <?php settings_fields('smtp_helper_options_group'); ?>
            <table id="smtp_helper_accounts" class="form-table" role="presentation">
                <?php foreach ($accounts as $i => $acc): ?>
                <tr class="smtp-account"><td>
                    <h2><?php printf(esc_html__('Account %d', 'smtp-helper'), $i + 1); ?></h2>
                    <p><label><?php esc_html_e('From Name', 'smtp-helper'); ?> <input name="smtp_helper_accounts[<?php echo $i; ?>][from_name]" type="text" value="<?php echo esc_attr($acc['from_name'] ?? ''); ?>" class="regular-text" /></label></p>
                    <p><label><?php esc_html_e('From Email', 'smtp-helper'); ?> <input name="smtp_helper_accounts[<?php echo $i; ?>][from_email]" type="email" value="<?php echo esc_attr($acc['from_email'] ?? ''); ?>" class="regular-text" /></label></p>
                    <p><label><?php esc_html_e('SMTP Host', 'smtp-helper'); ?> <input name="smtp_helper_accounts[<?php echo $i; ?>][host]" type="text" value="<?php echo esc_attr($acc['host'] ?? ''); ?>" class="regular-text" /></label></p>
                    <p><label><?php esc_html_e('SMTP Port', 'smtp-helper'); ?> <input name="smtp_helper_accounts[<?php echo $i; ?>][port]" type="number" value="<?php echo esc_attr($acc['port'] ?? ''); ?>" class="small-text" /></label></p>
                    <p><label><?php esc_html_e('Encryption', 'smtp-helper'); ?>
                        <?php $enc = $acc['encryption'] ?? ''; ?>
                        <select name="smtp_helper_accounts[<?php echo $i; ?>][encryption]">
                            <option value="" <?php selected($enc, ''); ?>><?php esc_html_e('None', 'smtp-helper'); ?></option>
                            <option value="ssl" <?php selected($enc, 'ssl'); ?>>SSL</option>
                            <option value="tls" <?php selected($enc, 'tls'); ?>>TLS</option>
                        </select>
                    </label></p>
                    <p><label><?php esc_html_e('Username', 'smtp-helper'); ?> <input name="smtp_helper_accounts[<?php echo $i; ?>][username]" type="text" value="<?php echo esc_attr($acc['username'] ?? ''); ?>" class="regular-text" /></label></p>
                    <p><label><?php esc_html_e('Password', 'smtp-helper'); ?> <input name="smtp_helper_accounts[<?php echo $i; ?>][password]" type="password" value="<?php echo esc_attr($acc['password'] ?? ''); ?>" class="regular-text" /></label></p>
                    <p><label><?php esc_html_e('Signature (HTML)', 'smtp-helper'); ?>
                        <textarea name="smtp_helper_accounts[<?php echo $i; ?>][signature]" rows="5" class="large-text code"><?php echo esc_textarea($acc['signature'] ?? ''); ?></textarea>
                    </label></p>
                </td></tr>
                <?php endforeach; ?>
            </table>
            <p><button type="button" class="button" id="smtp_helper_add_account"><?php esc_html_e('Add Account', 'smtp-helper'); ?></button></p>
            <?php submit_button(); ?>
        </form>
    </div>
    <script type="text/html" id="smtp_helper_account_template">
        <tr class="smtp-account"><td>
            <h2><?php esc_html_e('Account', 'smtp-helper'); ?> __INDEX__</h2>
            <p><label><?php esc_html_e('From Name', 'smtp-helper'); ?> <input name="smtp_helper_accounts[__INDEX__][from_name]" type="text" class="regular-text" /></label></p>
            <p><label><?php esc_html_e('From Email', 'smtp-helper'); ?> <input name="smtp_helper_accounts[__INDEX__][from_email]" type="email" class="regular-text" /></label></p>
            <p><label><?php esc_html_e('SMTP Host', 'smtp-helper'); ?> <input name="smtp_helper_accounts[__INDEX__][host]" type="text" class="regular-text" /></label></p>
            <p><label><?php esc_html_e('SMTP Port', 'smtp-helper'); ?> <input name="smtp_helper_accounts[__INDEX__][port]" type="number" class="small-text" /></label></p>
            <p><label><?php esc_html_e('Encryption', 'smtp-helper'); ?>
                <select name="smtp_helper_accounts[__INDEX__][encryption]">
                    <option value=""><?php esc_html_e('None', 'smtp-helper'); ?></option>
                    <option value="ssl">SSL</option>
                    <option value="tls">TLS</option>
                </select>
            </label></p>
            <p><label><?php esc_html_e('Username', 'smtp-helper'); ?> <input name="smtp_helper_accounts[__INDEX__][username]" type="text" class="regular-text" /></label></p>
            <p><label><?php esc_html_e('Password', 'smtp-helper'); ?> <input name="smtp_helper_accounts[__INDEX__][password]" type="password" class="regular-text" /></label></p>
            <p><label><?php esc_html_e('Signature (HTML)', 'smtp-helper'); ?>
                <textarea name="smtp_helper_accounts[__INDEX__][signature]" rows="5" class="large-text code"></textarea>
            </label></p>
        </td></tr>
    </script>
    <script>
    document.getElementById('smtp_helper_add_account').addEventListener('click', function(){
        const table = document.getElementById('smtp_helper_accounts');
        const idx = table.querySelectorAll('.smtp-account').length;
        const tpl = document.getElementById('smtp_helper_account_template').innerHTML.replace(/__INDEX__/g, idx);
        table.insertAdjacentHTML('beforeend', tpl);
    });
    </script>
    <?php
}

function smtp_helper_sanitize_accounts($input) {
    $out = array();
    if (is_array($input)) {
        foreach ($input as $acc) {
            $out[] = array(
                'host'       => sanitize_text_field($acc['host'] ?? ''),
                'port'       => intval($acc['port'] ?? 0),
                'encryption' => sanitize_text_field($acc['encryption'] ?? ''),
                'username'   => sanitize_text_field($acc['username'] ?? ''),
                'password'   => sanitize_text_field($acc['password'] ?? ''),
                'from_email' => sanitize_email($acc['from_email'] ?? ''),
                'from_name'  => sanitize_text_field($acc['from_name'] ?? ''),
                'signature'  => wp_kses_post($acc['signature'] ?? ''),
            );
        }
    }
    return $out;
}

function smtp_helper_get_accounts() {
    $acc = get_option('smtp_helper_accounts', array());
    return is_array($acc) ? $acc : array();
}

function smtp_helper_phpmailer_init($phpmailer) {
    $accounts = smtp_helper_get_accounts();
    $options = $accounts[0] ?? array();
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
