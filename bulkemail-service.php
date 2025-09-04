<?php
if (!defined('ABSPATH')) exit;

class KVT_BulkEmail_Service {
    const OPTION_KEY   = 'kt_abm_settings';
    const DEFAULT_FROM = 'timkuijten@kovacictalent.com';
    const SENT_LOG     = 'kt_abm_sent_log';

    public static function import_candidates() {
        $q = new WP_Query([
            'post_type'      => 'kvt_candidate',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'no_found_rows'  => true,
        ]);

        $rows = [];
        foreach ($q->posts as $p) {
            $email = sanitize_email(get_post_meta($p->ID, 'kvt_email', true));
            if (!$email) $email = sanitize_email(get_post_meta($p->ID, 'email', true));
            if (!$email) continue;

            $first = sanitize_text_field(get_post_meta($p->ID, 'kvt_first_name', true));
            if ($first === '') $first = sanitize_text_field(get_post_meta($p->ID, 'first_name', true));

            $last = sanitize_text_field(get_post_meta($p->ID, 'kvt_last_name', true));
            if ($last === '') $last = sanitize_text_field(get_post_meta($p->ID, 'last_name', true));

            $country = sanitize_text_field(get_post_meta($p->ID, 'kvt_country', true));
            if ($country === '') $country = sanitize_text_field(get_post_meta($p->ID, 'country', true));

            $city = sanitize_text_field(get_post_meta($p->ID, 'kvt_city', true));
            if ($city === '') $city = sanitize_text_field(get_post_meta($p->ID, 'city', true));

            $client = '';
            $terms = get_the_terms($p->ID, 'kvt_client');
            if ($terms && !is_wp_error($terms) && !empty($terms)) {
                $client = sanitize_text_field($terms[0]->name);
            }

            $role = '';
            $terms = get_the_terms($p->ID, 'kvt_process');
            if ($terms && !is_wp_error($terms) && !empty($terms)) {
                $role = sanitize_text_field($terms[0]->name);
                if ($client === '') {
                    $linked = (int) get_term_meta($terms[0]->term_id, 'kvt_process_client', true);
                    if ($linked) {
                        $client_term = get_term($linked, 'kvt_client');
                        if ($client_term && !is_wp_error($client_term)) {
                            $client = sanitize_text_field($client_term->name);
                        }
                    }
                }
            }

            $status = sanitize_text_field(get_post_meta($p->ID, 'kvt_status', true));

            $rows[] = [
                'email'      => $email,
                'first_name' => $first,
                'surname'    => $last,
                'country'    => $country,
                'city'       => $city,
                'client'     => $client,
                'role'       => $role,
                'status'     => $status,
            ];
        }
        return $rows;
    }

    public static function generate($prompt) {
        $prompt = (string)$prompt;
        if ($prompt === '') {
            return new WP_Error('missing_prompt', 'Falta el prompt');
        }
        $o = get_option(self::OPTION_KEY, []);
        $api_key = $o['openai_api_key'] ?? '';
        $model   = $o['openai_model']   ?? 'gpt-4o-mini';

        $fallback = [
            'subject'   => '{{first_name}}, nota rápida para el proceso {{role}} en {{city}}',
            'body_html' => 'Hola {{first_name}},<br><br>Te escribo desde Kovacic Talent. Por tu experiencia en el proceso <strong>{{role}}</strong> en {{city}}, {{country}}, creo que esto puede interesarte.<br><br>'.esc_html($prompt).'<br><br>Si te encaja, responde a este correo y te cuento más.<br><br>Saludos,<br>{{sender}}'
        ];

        if (!$api_key) {
            return ['subject_template' => $fallback['subject'], 'body_template' => $fallback['body_html']];
        }

        $sys = "Eres un redactor de emails de Kovacic Executive Talent Research. Devuelve un JSON con las claves 'subject' y 'body_html'. ".
               "Debes usar SIEMPRE estos placeholders cuando corresponda y NO inventar otros: ".
               "Nombre = {{first_name}}, Apellido = {{surname}}, País = {{country}}, Ciudad = {{city}}, Cliente = {{client}}, Proceso = {{role}}, Estado = {{status}}, El remitente = {{sender}}. ".
               "El cuerpo debe ser HTML y usar saltos de línea <br> (NO uses etiquetas <p>). Y Nunca uses '—'.";

        $req = [
            'model' => $model,
            'temperature' => 0.7,
            'response_format' => ['type' => 'json_object'],
            'messages' => [
                ['role' => 'system', 'content' => $sys],
                ['role' => 'user', 'content' => $prompt],
            ],
        ];

        $resp = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer '.$api_key,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode($req),
            'timeout' => 30,
        ]);

        if (is_wp_error($resp)) {
            return ['subject_template' => $fallback['subject'], 'body_template' => $fallback['body_html']];
        }

        $code = wp_remote_retrieve_response_code($resp);
        $body = wp_remote_retrieve_body($resp);
        $json = json_decode($body, true);

        if ($code !== 200 || empty($json['choices'][0]['message']['content'])) {
            return ['subject_template' => $fallback['subject'], 'body_template' => $fallback['body_html']];
        }

        $content = json_decode($json['choices'][0]['message']['content'], true);
        $subject = isset($content['subject']) ? (string)$content['subject'] : $fallback['subject'];
        $html    = isset($content['body_html']) ? (string)$content['body_html'] : $fallback['body_html'];

        return ['subject_template' => $subject, 'body_template' => $html];
    }

    public static function send($payload) {
        $subject_tpl  = (string)($payload['subject_template'] ?? '');
        $body_tpl     = (string)($payload['body_template'] ?? '');
        $recipients   = (array)($payload['recipients'] ?? []);
        $from_email   = sanitize_email($payload['from_email'] ?? '');
        $from_name    = sanitize_text_field($payload['from_name'] ?? '');

        $o = get_option(self::OPTION_KEY, []);
        if (!$from_email) $from_email = sanitize_email($o['from_email'] ?? self::DEFAULT_FROM);
        if (!$from_email) $from_email = self::DEFAULT_FROM;
        if (!$from_name)  $from_name  = sanitize_text_field($o['from_name'] ?? get_bloginfo('name'));

        $from_cb = null;
        $from_name_cb = null;

        if ($from_email) {
            $from_cb = function() use ($from_email){ return $from_email; };
            add_filter('wp_mail_from', $from_cb, 99);
        }
        if ($from_name)  {
            $from_name_cb = function() use ($from_name){ return $from_name; };
            add_filter('wp_mail_from_name', $from_name_cb, 99);
        }

        $sent = 0; $errors = [];
        $log = get_option(self::SENT_LOG, []);
        if (!is_array($log)) $log = [];
        $last_error = null;
        $failed_hook = function($wp_error) use (&$last_error) {
            if (is_wp_error($wp_error)) {
                $last_error = $wp_error->get_error_message();
            } else {
                $last_error = is_string($wp_error) ? $wp_error : 'Unknown mail error';
            }
        };
        add_action('wp_mail_failed', $failed_hook);

        foreach ($recipients as $r) {
            $email      = isset($r['email']) ? sanitize_email($r['email']) : '';
            $first_name = isset($r['first_name']) ? sanitize_text_field($r['first_name']) : '';
            $surname    = isset($r['surname']) ? sanitize_text_field($r['surname']) : '';
            $country    = isset($r['country']) ? sanitize_text_field($r['country']) : '';
            $city       = isset($r['city']) ? sanitize_text_field($r['city']) : '';
            $role       = isset($r['role']) ? sanitize_text_field($r['role']) : '';
            if (!$email) continue;

            $data = compact('first_name','surname','country','city','role');
            $subject = self::render($subject_tpl, $data);

            $body_raw = self::render($body_tpl, array_merge($data, [
                'sender' => $from_name ?: $from_email ?: get_bloginfo('name')
            ]));

            $body = self::normalize_br_html($body_raw);

            $headers = ['Content-Type: text/html; charset=UTF-8'];
            if ($from_email) $headers[] = 'Reply-To: '.$from_name.' <'.$from_email.'>';

            $ok = wp_mail($email, $subject, $body, $headers);
            if ($ok) {
                $sent++;
                $log[] = [
                    'time'    => current_time('mysql'),
                    'to'      => $email,
                    'subject' => $subject,
                    'body'    => $body,
                    'from'    => $from_email,
                ];
            } else {
                $errors[] = ['email'=>$email, 'error'=>$last_error ?: 'wp_mail falló'];
            }
            usleep(250000);
        }

        if ($from_cb)      remove_filter('wp_mail_from', $from_cb, 99);
        if ($from_name_cb) remove_filter('wp_mail_from_name', $from_name_cb, 99);
        remove_action('wp_mail_failed', $failed_hook);

        update_option(self::SENT_LOG, $log, false);

        if ($last_error) $errors[] = ['email'=>'(general)', 'error'=>$last_error];

        return ['sent'=>$sent, 'errors'=>$errors];
    }

    public static function create_eml_zip($payload) {
        $subject_tpl = (string)($payload['subject_template'] ?? '');
        $body_tpl    = (string)($payload['body_template'] ?? '');
        $recipients  = (array)($payload['recipients'] ?? []);
        $from_email  = sanitize_email($payload['from_email'] ?? '');
        $from_name   = sanitize_text_field($payload['from_name'] ?? '');

        $o = get_option(self::OPTION_KEY, []);
        if (!$from_email) $from_email = sanitize_email($o['from_email'] ?? self::DEFAULT_FROM);
        if (!$from_email) $from_email = self::DEFAULT_FROM;
        if (!$from_name)  $from_name  = sanitize_text_field($o['from_name'] ?? get_bloginfo('name'));

        if (!class_exists('ZipArchive')) {
            return new WP_Error('missing_zip', 'ZipArchive no disponible');
        }

        $zip_path = tempnam(sys_get_temp_dir(), 'kt_eml_');
        $zip = new ZipArchive();
        if ($zip->open($zip_path, ZipArchive::OVERWRITE) !== true) {
            return new WP_Error('zip_error', 'No se pudo crear el ZIP temporal');
        }

        $sender_disp = $from_name ? $from_name.' <'.$from_email.'>' : $from_email;

        $i = 0;
        foreach ($recipients as $r) {
            $email      = isset($r['email']) ? sanitize_email($r['email']) : '';
            if (!$email) continue;

            $first_name = isset($r['first_name']) ? sanitize_text_field($r['first_name']) : '';
            $surname    = isset($r['surname']) ? sanitize_text_field($r['surname']) : '';
            $country    = isset($r['country']) ? sanitize_text_field($r['country']) : '';
            $city       = isset($r['city']) ? sanitize_text_field($r['city']) : '';
            $role       = isset($r['role']) ? sanitize_text_field($r['role']) : '';

            $data = compact('first_name','surname','country','city','role');
            $subject   = self::render($subject_tpl, $data);
            $body_raw  = self::render($body_tpl, array_merge($data, ['sender' => $from_name ?: $from_email ?: get_bloginfo('name')]));

            $body_html = self::normalize_br_html($body_raw);

            $to_disp = trim(($first_name . ' ' . $surname));
            $to_disp = $to_disp ? ($to_disp.' <'.$email.'>') : $email;

            $eml = self::build_eml(
                $sender_disp,
                $to_disp,
                $subject,
                $body_html
            );

            $fname = self::safe_filename(($first_name ?: 'contacto').'_'.$i.'_'.$email.'.eml');
            $zip->addFromString($fname, $eml);
            $i++;
        }

        $zip->close();
        return $zip_path;
    }

    private static function build_eml($from, $to, $subject, $html){
        $date = date('r');
        $host = parse_url(home_url(), PHP_URL_HOST);
        if (!$host) { $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'local'; }
        $message_id = '<'.wp_generate_uuid4().'@'.$host.'>';

        $encoded_subject = self::encode_header_utf8($subject);

        $body_html = self::normalize_eol($html);
        $body_b64 = chunk_split(base64_encode($body_html));

        $headers  = '';
        $headers .= "X-Unsent: 1\r\n";
        $headers .= "From: ".self::fold_header_line($from)."\r\n";
        $headers .= "To: ".self::fold_header_line($to)."\r\n";
        $headers .= "Subject: ".$encoded_subject."\r\n";
        $headers .= "Date: ".$date."\r\n";
        $headers .= "Message-ID: ".$message_id."\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "Content-Transfer-Encoding: base64\r\n";
        $headers .= "\r\n";

        return $headers.$body_b64;
    }

    private static function encode_header_utf8($text){
        if (!preg_match('/[^\x20-\x7E]/', $text)) return $text;
        return '=?UTF-8?B?'.base64_encode($text).'?=';
    }

    private static function normalize_eol($html){
        $body = trim($html);
        if (!preg_match('~</?(html|body)~i', $body)) {
            $body = "<html><body>\n".$body."\n</body></html>";
        }
        $body = preg_replace("/\r\n|\n|\r/", "\r\n", $body);
        return $body;
    }

    private static function fold_header_line($v){
        $v = preg_replace('/\r|\n/', ' ', $v);
        $out = '';
        while (strlen($v) > 76) {
            $out .= substr($v, 0, 76)."\r\n ";
            $v = substr($v, 76);
        }
        return $out.$v;
    }

    private static function safe_filename($name){
        $name = preg_replace('/[^\w\-.@]+/u', '_', $name);
        $name = trim($name, '_');
        if ($name === '') $name = 'draft';
        if (!preg_match('/\.eml$/i', $name)) $name .= '.eml';
        return $name;
    }

    private static function normalize_br_html($html){
        $out = (string)$html;
        $out = preg_replace('~</p>\s*<p>~i', '<br><br>', $out);
        $out = preg_replace('~</?p[^>]*>~i', '', $out);
        $out = preg_replace("/\r\n|\r/", "\n", $out);
        $out = preg_replace("/\n{2,}/", "<br><br>", $out);
        $out = str_replace("\n", "<br>", $out);
        return $out;
    }

    private static function render($tpl, $data) {
        return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', function($m) use ($data){
            $k = $m[1];
            return isset($data[$k]) ? esc_html($data[$k]) : $m[0];
        }, $tpl);
    }
}

