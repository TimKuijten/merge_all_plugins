<?php
/**
 * Plugin Name: SMTP Helper
 * Description: Simple plugin to configure multiple SMTP accounts for WordPress.
 * Version: 1.1.0
 * Author: ChatGPT
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add settings page.
 */
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

/**
 * Register option for multiple accounts.
 */
function smtp_helper_register_settings() {
    register_setting('smtp_helper_options_group', 'smtp_helper_accounts', 'smtp_helper_sanitize_accounts');
}
add_action('admin_init', 'smtp_helper_register_settings');

/**
 * Helper to fetch all accounts.
 *
 * @return array
 */
function smtp_helper_get_accounts() {
    $accounts = get_option('smtp_helper_accounts', array());
    if (!is_array($accounts) || empty($accounts)) {
        $accounts = array(array('name' => 'Account 1', 'signature' => ''));
    }
    foreach ($accounts as $i => $acc) {
        if (empty($acc['name'])) {
            $accounts[$i]['name'] = 'Account ' . ($i + 1);
        }
        if (!isset($acc['signature'])) {
            $accounts[$i]['signature'] = '';
        }
    }
    return $accounts;
}

/**
 * Render the settings page.
 */
function smtp_helper_render_settings_page() {
    $accounts = smtp_helper_get_accounts();
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('SMTP Helper Settings', 'smtp-helper'); ?></h1>
        <form method="post" action="options.php">
            <?php settings_fields('smtp_helper_options_group'); ?>
            <div id="smtp-helper-accounts">
                <?php foreach ($accounts as $i => $acc) : ?>
                <fieldset class="smtp-helper-account" style="border:1px solid #ccc;padding:10px;margin-bottom:10px;">
                    <legend><?php echo esc_html($acc['name'] ?? ('Account ' . ($i + 1))); ?></legend>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><label for="smtp_helper_name_<?php echo $i; ?>"><?php esc_html_e('Account Name', 'smtp-helper'); ?></label></th>
                            <td><input name="smtp_helper_accounts[<?php echo $i; ?>][name]" id="smtp_helper_name_<?php echo $i; ?>" type="text" value="<?php echo esc_attr($acc['name'] ?? ('Account ' . ($i + 1))); ?>" class="regular-text" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="smtp_helper_host_<?php echo $i; ?>"><?php esc_html_e('SMTP Host', 'smtp-helper'); ?></label></th>
                            <td><input name="smtp_helper_accounts[<?php echo $i; ?>][host]" id="smtp_helper_host_<?php echo $i; ?>" type="text" value="<?php echo esc_attr($acc['host'] ?? ''); ?>" class="regular-text" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="smtp_helper_port_<?php echo $i; ?>"><?php esc_html_e('SMTP Port', 'smtp-helper'); ?></label></th>
                            <td><input name="smtp_helper_accounts[<?php echo $i; ?>][port]" id="smtp_helper_port_<?php echo $i; ?>" type="number" value="<?php echo esc_attr($acc['port'] ?? ''); ?>" class="small-text" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="smtp_helper_encryption_<?php echo $i; ?>"><?php esc_html_e('Encryption', 'smtp-helper'); ?></label></th>
                            <td>
                                <?php $enc = $acc['encryption'] ?? ''; ?>
                                <select name="smtp_helper_accounts[<?php echo $i; ?>][encryption]" id="smtp_helper_encryption_<?php echo $i; ?>">
                                    <option value="" <?php selected($enc, ''); ?>><?php esc_html_e('None', 'smtp-helper'); ?></option>
                                    <option value="ssl" <?php selected($enc, 'ssl'); ?>>SSL</option>
                                    <option value="tls" <?php selected($enc, 'tls'); ?>>TLS</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="smtp_helper_username_<?php echo $i; ?>"><?php esc_html_e('Username', 'smtp-helper'); ?></label></th>
                            <td><input name="smtp_helper_accounts[<?php echo $i; ?>][username]" id="smtp_helper_username_<?php echo $i; ?>" type="text" value="<?php echo esc_attr($acc['username'] ?? ''); ?>" class="regular-text" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="smtp_helper_password_<?php echo $i; ?>"><?php esc_html_e('Password', 'smtp-helper'); ?></label></th>
                            <td><input name="smtp_helper_accounts[<?php echo $i; ?>][password]" id="smtp_helper_password_<?php echo $i; ?>" type="password" value="<?php echo esc_attr($acc['password'] ?? ''); ?>" class="regular-text" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="smtp_helper_from_email_<?php echo $i; ?>"><?php esc_html_e('From Email', 'smtp-helper'); ?></label></th>
                            <td><input name="smtp_helper_accounts[<?php echo $i; ?>][from_email]" id="smtp_helper_from_email_<?php echo $i; ?>" type="email" value="<?php echo esc_attr($acc['from_email'] ?? ''); ?>" class="regular-text" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="smtp_helper_from_name_<?php echo $i; ?>"><?php esc_html_e('From Name', 'smtp-helper'); ?></label></th>
                            <td><input name="smtp_helper_accounts[<?php echo $i; ?>][from_name]" id="smtp_helper_from_name_<?php echo $i; ?>" type="text" value="<?php echo esc_attr($acc['from_name'] ?? ''); ?>" class="regular-text" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="smtp_helper_signature_<?php echo $i; ?>"><?php esc_html_e('Signature (HTML)', 'smtp-helper'); ?></label></th>
                            <td><textarea name="smtp_helper_accounts[<?php echo $i; ?>][signature]" id="smtp_helper_signature_<?php echo $i; ?>" rows="5" class="large-text code"><?php echo esc_textarea($acc['signature'] ?? ''); ?></textarea></td>
                        </tr>
                    </table>
                </fieldset>
                <?php endforeach; ?>
            </div>
            <p><button type="button" class="button" id="smtp-helper-add-account"><?php esc_html_e('Add Account', 'smtp-helper'); ?></button></p>
            <?php submit_button(); ?>
        </form>
    </div>
    <script>
    document.getElementById('smtp-helper-add-account').addEventListener('click', function(){
        const container = document.getElementById('smtp-helper-accounts');
        const index = container.querySelectorAll('.smtp-helper-account').length;
        const template = container.querySelector('.smtp-helper-account');
        if(!template) return;
        const clone = template.cloneNode(true);
        clone.querySelectorAll('input,select,textarea,label').forEach(function(el){
            if(el.tagName === 'LABEL'){
                const attr = el.getAttribute('for');
                if(attr) el.setAttribute('for', attr.replace(/_\d+$/, '_' + index));
            } else {
                const name = el.getAttribute('name');
                if(name) el.setAttribute('name', name.replace(/\[\d+\]/, '[' + index + ']'));
                const id = el.getAttribute('id');
                if(id) el.setAttribute('id', id.replace(/_\d+$/, '_' + index));
                if(name && /\[name\]$/.test(name)){
                    el.value = 'Account ' + (index + 1);
                } else {
                    el.value = '';
                }
            }
        });
        container.appendChild(clone);
    });
    </script>
    <?php
}

/**
 * Sanitize accounts before saving.
 *
 * @param array $input
 * @return array
 */
function smtp_helper_sanitize_accounts($input) {
    $out = array();
    if (is_array($input)) {
        $i = 1;
        foreach ($input as $acc) {
            $o = array();
            $o['name']      = sanitize_text_field($acc['name'] ?? '');
            if ($o['name'] === '') {
                $o['name'] = 'Account ' . $i;
            }
            $o['host']       = sanitize_text_field($acc['host'] ?? '');
            $o['port']       = intval($acc['port'] ?? 0);
            $o['encryption'] = sanitize_text_field($acc['encryption'] ?? '');
            $o['username']   = sanitize_text_field($acc['username'] ?? '');
            $o['password']   = sanitize_text_field($acc['password'] ?? '');
            $o['from_email'] = sanitize_email($acc['from_email'] ?? '');
            $o['from_name']  = sanitize_text_field($acc['from_name'] ?? '');
            $o['signature']  = wp_kses_post($acc['signature'] ?? '');
            $out[] = $o;
            $i++;
        }
    }
    return $out;
}

/**
 * Configure PHPMailer using selected account.
 */
function smtp_helper_phpmailer_init($phpmailer) {
    $accounts = smtp_helper_get_accounts();
    if (empty($accounts)) {
        return;
    }
    $index = apply_filters('smtp_helper_selected_account_index', 0);
    if (!isset($accounts[$index])) {
        $index = 0;
    }
    $options = $accounts[$index];

    if (empty($options['host'])) {
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

