<?php
/**
 * Plugin Name: KVT Outlook Front-End
 * Description: Adds Microsoft Outlook calendar integration (via Microsoft Graph) for front-end pages using shortcodes.
 * Plugin URI: https://example.com
 * Version: 1.0.0
 * Author: Your Name
 * Text Domain: kvt-outlook
 *
 * Setup:
 * 1. In Azure: create App registration → copy Tenant ID & Client ID → create Client Secret → set Redirect URI to: https://yourdomain.com/wp-json/kvt/v1/oauth/callback
 * 2. In WP admin: go to Settings → KVT Outlook → paste Tenant ID, Client ID, Client Secret, Redirect URI → Save.
 * 3. Place shortcodes on a page:
 *    [kvt_outlook_connect]
 *    [kvt_outlook_calendar]
 *    [kvt_outlook_create_event] (optional)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Activation hook - flush rewrite rules for REST routes
 */
function kvt_outlook_activate() {
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'kvt_outlook_activate' );

/**
 * Helper to get plugin options
 */
function kvt_get_option( $key, $default = '' ) {
    $opt = get_option( $key, $default );
    return is_string( $opt ) ? trim( $opt ) : $opt;
}

/**
 * Register settings page
 */
add_action( 'admin_menu', function() {
    add_options_page( 'KVT Outlook', 'KVT Outlook', 'manage_options', 'kvt-outlook', 'kvt_settings_page' );
} );

add_action( 'admin_init', function() {
    register_setting( 'kvt_outlook', 'kvt_tenant_id', [ 'sanitize_callback' => 'sanitize_text_field' ] );
    register_setting( 'kvt_outlook', 'kvt_client_id', [ 'sanitize_callback' => 'sanitize_text_field' ] );
    register_setting( 'kvt_outlook', 'kvt_client_secret', [ 'sanitize_callback' => 'sanitize_text_field' ] );
    register_setting( 'kvt_outlook', 'kvt_redirect_uri', [ 'sanitize_callback' => 'esc_url_raw' ] );
    register_setting( 'kvt_outlook', 'kvt_allow_create', [ 'sanitize_callback' => function( $v ){ return $v ? 1 : 0; } ] );
} );

function kvt_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    $callback = rest_url( 'kvt/v1/oauth/callback' );
    if ( ! kvt_get_option( 'kvt_redirect_uri' ) ) {
        update_option( 'kvt_redirect_uri', esc_url_raw( $callback ) );
    }
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'KVT Outlook Settings', 'kvt-outlook' ); ?></h1>
        <form method="post" action="options.php">
            <?php settings_fields( 'kvt_outlook' ); ?>
            <table class="form-table" role="presentation">
                <tr><th scope="row"><label for="kvt_tenant_id"><?php esc_html_e( 'Tenant ID', 'kvt-outlook' ); ?></label></th>
                    <td><input name="kvt_tenant_id" id="kvt_tenant_id" type="text" value="<?php echo esc_attr( kvt_get_option( 'kvt_tenant_id' ) ); ?>" class="regular-text" /></td></tr>
                <tr><th scope="row"><label for="kvt_client_id"><?php esc_html_e( 'Client ID', 'kvt-outlook' ); ?></label></th>
                    <td><input name="kvt_client_id" id="kvt_client_id" type="text" value="<?php echo esc_attr( kvt_get_option( 'kvt_client_id' ) ); ?>" class="regular-text" /></td></tr>
                <tr><th scope="row"><label for="kvt_client_secret"><?php esc_html_e( 'Client Secret', 'kvt-outlook' ); ?></label></th>
                    <td><input name="kvt_client_secret" id="kvt_client_secret" type="password" value="<?php echo esc_attr( kvt_get_option( 'kvt_client_secret' ) ); ?>" class="regular-text" /></td></tr>
                <tr><th scope="row"><label for="kvt_redirect_uri"><?php esc_html_e( 'Redirect URI', 'kvt-outlook' ); ?></label></th>
                    <td><input name="kvt_redirect_uri" id="kvt_redirect_uri" type="url" value="<?php echo esc_attr( kvt_get_option( 'kvt_redirect_uri', $callback ) ); ?>" class="regular-text" /></td></tr>
                <tr><th scope="row"><label for="kvt_allow_create"><?php esc_html_e( 'Allow front-end event creation', 'kvt-outlook' ); ?></label></th>
                    <td><input name="kvt_allow_create" id="kvt_allow_create" type="checkbox" value="1" <?php checked( kvt_get_option( 'kvt_allow_create' ), 1 ); ?> /></td></tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

/**
 * Build authorization URL
 */
function kvt_oauth_authorize_url() {
    $tenant   = kvt_get_option( 'kvt_tenant_id' );
    $client   = kvt_get_option( 'kvt_client_id' );
    $redirect = kvt_get_option( 'kvt_redirect_uri' );
    if ( ! $tenant || ! $client || ! $redirect ) {
        return '';
    }
    $user_id = get_current_user_id();
    $state   = wp_create_nonce( 'kvt_oauth_state_' . $user_id );
    update_user_meta( $user_id, 'kvt_oauth_state', $state );
    $params  = [
        'client_id'     => $client,
        'response_type' => 'code',
        'response_mode' => 'query',
        'redirect_uri'  => $redirect,
        'scope'         => 'offline_access Calendars.Read Calendars.ReadWrite',
        'state'         => $state,
    ];
    $url = 'https://login.microsoftonline.com/' . rawurlencode( $tenant ) . '/oauth2/v2.0/authorize?' . http_build_query( $params, '', '&', PHP_QUERY_RFC3986 );
    return $url;
}

/**
 * REST route for OAuth callback
 */
add_action( 'rest_api_init', function() {
    register_rest_route( 'kvt/v1', '/oauth/callback', [
        'methods'             => 'GET',
        'callback'            => 'kvt_oauth_handle_callback',
        'permission_callback' => '__return_true',
    ] );
} );

function kvt_oauth_handle_callback( WP_REST_Request $request ) {
    if ( ! is_user_logged_in() ) {
        return new WP_REST_Response( 'Not logged in', 401 );
    }
    $code  = sanitize_text_field( $request->get_param( 'code' ) );
    $state = sanitize_text_field( $request->get_param( 'state' ) );
    $user_id = get_current_user_id();
    $saved_state = get_user_meta( $user_id, 'kvt_oauth_state', true );
    if ( ! $code || ! $state || $state !== $saved_state || ! wp_verify_nonce( $state, 'kvt_oauth_state_' . $user_id ) ) {
        return new WP_REST_Response( 'Invalid state', 400 );
    }
    delete_user_meta( $user_id, 'kvt_oauth_state' );

    $token_response = kvt_exchange_code_for_token( $code );
    if ( is_wp_error( $token_response ) ) {
        return new WP_REST_Response( $token_response->get_error_message(), 500 );
    }
    wp_safe_redirect( home_url() );
    exit;
}

function kvt_exchange_code_for_token( $code ) {
    $tenant   = kvt_get_option( 'kvt_tenant_id' );
    $client   = kvt_get_option( 'kvt_client_id' );
    $secret   = kvt_get_option( 'kvt_client_secret' );
    $redirect = kvt_get_option( 'kvt_redirect_uri' );

    $body = [
        'client_id'     => $client,
        'scope'         => 'offline_access Calendars.Read Calendars.ReadWrite',
        'code'          => $code,
        'redirect_uri'  => $redirect,
        'grant_type'    => 'authorization_code',
        'client_secret' => $secret,
    ];

    $response = wp_remote_post( 'https://login.microsoftonline.com/' . rawurlencode( $tenant ) . '/oauth2/v2.0/token', [
        'body' => $body,
    ] );
    if ( is_wp_error( $response ) ) {
        error_log( '[KVT-Outlook] Token exchange error: ' . $response->get_error_message() );
        return $response;
    }
    $code = wp_remote_retrieve_response_code( $response );
    $data = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( 200 !== $code || empty( $data['access_token'] ) ) {
        error_log( '[KVT-Outlook] Token exchange failed: ' . wp_remote_retrieve_body( $response ) );
        return new WP_Error( 'token_error', __( 'Token exchange failed', 'kvt-outlook' ) );
    }
    $user_id = get_current_user_id();
    update_user_meta( $user_id, 'kvt_ms_access_token', sanitize_text_field( $data['access_token'] ) );
    if ( ! empty( $data['refresh_token'] ) ) {
        update_user_meta( $user_id, 'kvt_ms_refresh_token', sanitize_text_field( $data['refresh_token'] ) );
    }
    $expires = time() + intval( $data['expires_in'] ) - 300;
    update_user_meta( $user_id, 'kvt_ms_token_expires_at', $expires );
    return true;
}

/**
 * Get a valid access token, refreshing if necessary
 */
function kvt_token_get_valid() {
    $user_id = get_current_user_id();
    $access  = get_user_meta( $user_id, 'kvt_ms_access_token', true );
    $refresh = get_user_meta( $user_id, 'kvt_ms_refresh_token', true );
    $expires = (int) get_user_meta( $user_id, 'kvt_ms_token_expires_at', true );
    if ( $access && $expires > time() + 60 ) {
        return $access;
    }
    if ( ! $refresh ) {
        return false;
    }
    $tenant   = kvt_get_option( 'kvt_tenant_id' );
    $client   = kvt_get_option( 'kvt_client_id' );
    $secret   = kvt_get_option( 'kvt_client_secret' );

    $body = [
        'client_id'     => $client,
        'grant_type'    => 'refresh_token',
        'refresh_token' => $refresh,
        'scope'         => 'offline_access Calendars.Read Calendars.ReadWrite',
        'client_secret' => $secret,
    ];
    $response = wp_remote_post( 'https://login.microsoftonline.com/' . rawurlencode( $tenant ) . '/oauth2/v2.0/token', [
        'body' => $body,
    ] );
    if ( is_wp_error( $response ) ) {
        error_log( '[KVT-Outlook] Token refresh error: ' . $response->get_error_message() );
        return false;
    }
    $code = wp_remote_retrieve_response_code( $response );
    $data = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( 200 !== $code || empty( $data['access_token'] ) ) {
        error_log( '[KVT-Outlook] Token refresh failed: ' . wp_remote_retrieve_body( $response ) );
        return false;
    }
    update_user_meta( $user_id, 'kvt_ms_access_token', sanitize_text_field( $data['access_token'] ) );
    if ( ! empty( $data['refresh_token'] ) ) {
        update_user_meta( $user_id, 'kvt_ms_refresh_token', sanitize_text_field( $data['refresh_token'] ) );
    }
    $expires = time() + intval( $data['expires_in'] ) - 300;
    update_user_meta( $user_id, 'kvt_ms_token_expires_at', $expires );
    return $data['access_token'];
}

function kvt_is_connected() {
    return (bool) kvt_token_get_valid();
}

function kvt_disconnect() {
    $user_id = get_current_user_id();
    delete_user_meta( $user_id, 'kvt_ms_access_token' );
    delete_user_meta( $user_id, 'kvt_ms_refresh_token' );
    delete_user_meta( $user_id, 'kvt_ms_token_expires_at' );
}

/**
 * Graph helpers
 */
function kvt_graph_get( $path, $query = [] ) {
    $token = kvt_token_get_valid();
    if ( ! $token ) {
        return new WP_Error( 'no_token', __( 'Not connected', 'kvt-outlook' ) );
    }
    $url = 'https://graph.microsoft.com/v1.0' . $path;
    if ( $query ) {
        $url .= '?' . http_build_query( $query, '', '&', PHP_QUERY_RFC3986 );
    }
    $response = wp_remote_get( $url, [ 'headers' => [ 'Authorization' => 'Bearer ' . $token ] ] );
    if ( is_wp_error( $response ) ) {
        return $response;
    }
    $code = wp_remote_retrieve_response_code( $response );
    $body = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( 200 !== $code ) {
        return new WP_Error( 'graph_error', isset( $body['error']['message'] ) ? $body['error']['message'] : 'Error' );
    }
    return $body;
}

function kvt_graph_post( $path, $body = [] ) {
    $token = kvt_token_get_valid();
    if ( ! $token ) {
        return new WP_Error( 'no_token', __( 'Not connected', 'kvt-outlook' ) );
    }
    $url = 'https://graph.microsoft.com/v1.0' . $path;
    $response = wp_remote_post( $url, [
        'headers' => [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type'  => 'application/json',
        ],
        'body'    => wp_json_encode( $body ),
    ] );
    if ( is_wp_error( $response ) ) {
        return $response;
    }
    $code = wp_remote_retrieve_response_code( $response );
    $data = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( $code < 200 || $code >= 300 ) {
        return new WP_Error( 'graph_error', isset( $data['error']['message'] ) ? $data['error']['message'] : 'Error' );
    }
    return $data;
}

function kvt_format_datetime_for_display( $iso ) {
    try {
        $dt = new DateTimeImmutable( $iso );
        $tz = wp_timezone();
        $dt = $dt->setTimezone( $tz );
        return wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $dt->getTimestamp(), $tz );
    } catch ( Exception $e ) {
        return esc_html( $iso );
    }
}

/**
 * Shortcode: [kvt_outlook_connect]
 */
function kvt_shortcode_connect() {
    if ( ! is_user_logged_in() ) {
        return '<p>' . esc_html__( 'You must be logged in.', 'kvt-outlook' ) . '</p>';
    }
    $output = '';
    if ( kvt_is_connected() ) {
        $url = wp_nonce_url( add_query_arg( 'kvt_disconnect', 1, wp_unslash( $_SERVER['REQUEST_URI'] ) ), 'kvt_disconnect' );
        $output .= '<span class="kvt-connected">' . esc_html__( 'Connected to Outlook', 'kvt-outlook' ) . '</span> ';
        $output .= '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Disconnect', 'kvt-outlook' ) . '</a>';
    } else {
        $auth = kvt_oauth_authorize_url();
        if ( ! $auth ) {
            $output .= '<p>' . esc_html__( 'OAuth not configured.', 'kvt-outlook' ) . '</p>';
        } else {
            $output .= '<a class="button" href="' . esc_url( $auth ) . '">' . esc_html__( 'Connect with Outlook', 'kvt-outlook' ) . '</a>';
        }
    }
    return $output;
}
add_shortcode( 'kvt_outlook_connect', 'kvt_shortcode_connect' );

/**
 * Handle disconnect
 */
add_action( 'init', function() {
    if ( isset( $_GET['kvt_disconnect'] ) && wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'kvt_disconnect' ) ) {
        kvt_disconnect();
        wp_safe_redirect( remove_query_arg( [ 'kvt_disconnect', '_wpnonce' ] ) );
        exit;
    }
} );

/**
 * Shortcode: [kvt_outlook_calendar]
 */
function kvt_shortcode_calendar() {
    if ( ! is_user_logged_in() ) {
        return '<p>' . esc_html__( 'You must be logged in.', 'kvt-outlook' ) . '</p>';
    }
    if ( ! kvt_is_connected() ) {
        return kvt_shortcode_connect();
    }
    $user_id = get_current_user_id();
    $cache_key = 'kvt_calendar_' . $user_id;
    $events = get_transient( $cache_key );
    if ( false === $events ) {
        $res = kvt_graph_get( '/me/events', [ '$top' => 10, '$orderby' => 'start/dateTime' ] );
        if ( is_wp_error( $res ) ) {
            return '<p>' . esc_html__( 'Error fetching events: ', 'kvt-outlook' ) . esc_html( $res->get_error_message() ) . '</p>';
        }
        $events = isset( $res['value'] ) ? $res['value'] : [];
        set_transient( $cache_key, $events, 60 );
    }
    if ( empty( $events ) ) {
        return '<p>' . esc_html__( 'No upcoming events.', 'kvt-outlook' ) . '</p>';
    }
    $out = '<ul class="kvt-events">';
    foreach ( $events as $event ) {
        $subject = esc_html( $event['subject'] ?? __( '(No subject)', 'kvt-outlook' ) );
        $start = isset( $event['start']['dateTime'] ) ? kvt_format_datetime_for_display( $event['start']['dateTime'] ) : '';
        $end   = isset( $event['end']['dateTime'] ) ? kvt_format_datetime_for_display( $event['end']['dateTime'] ) : '';
        $link  = ! empty( $event['webLink'] ) ? esc_url( $event['webLink'] ) : '';
        $loc   = esc_html( $event['location']['displayName'] ?? '' );
        $att   = isset( $event['attendees'] ) ? count( $event['attendees'] ) : 0;
        $out  .= '<li>'; 
        if ( $link ) {
            $out .= '<a href="' . $link . '" target="_blank" rel="noopener">' . $subject . '</a>';
        } else {
            $out .= $subject;
        }
        $out .= '<br/><small>' . esc_html( $start . ' - ' . $end );
        if ( $loc ) {
            $out .= ' | ' . esc_html( $loc );
        }
        $out .= ' | ' . sprintf( esc_html__( '%d attendees', 'kvt-outlook' ), $att );
        $out .= '</small></li>';
    }
    $out .= '</ul>';
    return $out;
}
add_shortcode( 'kvt_outlook_calendar', 'kvt_shortcode_calendar' );

/**
 * Shortcode: [kvt_outlook_create_event]
 */
function kvt_shortcode_create_event() {
    if ( ! is_user_logged_in() ) {
        return '<p>' . esc_html__( 'You must be logged in.', 'kvt-outlook' ) . '</p>';
    }
    if ( ! kvt_get_option( 'kvt_allow_create' ) ) {
        return '';
    }
    if ( ! kvt_is_connected() ) {
        return kvt_shortcode_connect();
    }
    $notice = '';
    if ( isset( $_GET['kvt_event'] ) ) {
        if ( 'success' === $_GET['kvt_event'] ) {
            $notice = '<div class="kvt-notice success">' . esc_html__( 'Event created successfully.', 'kvt-outlook' ) . '</div>';
        } elseif ( 'error' === $_GET['kvt_event'] ) {
            $err = esc_html( $_GET['message'] ?? '' );
            $notice = '<div class="kvt-notice error">' . esc_html__( 'Error creating event: ', 'kvt-outlook' ) . $err . '</div>';
        }
    }
    $action = esc_url( admin_url( 'admin-post.php' ) );
    $nonce  = wp_create_nonce( 'kvt_create_event' );
    $out = $notice;
    $out .= '<form method="post" action="' . $action . '">';
    $out .= '<input type="hidden" name="action" value="kvt_create_event" />';
    $out .= '<input type="hidden" name="_wpnonce" value="' . esc_attr( $nonce ) . '" />';
    $out .= '<p><label>' . esc_html__( 'Subject', 'kvt-outlook' ) . '<br/><input type="text" name="subject" required></label></p>';
    $out .= '<p><label>' . esc_html__( 'Start', 'kvt-outlook' ) . '<br/><input type="datetime-local" name="start" required></label></p>';
    $out .= '<p><label>' . esc_html__( 'End', 'kvt-outlook' ) . '<br/><input type="datetime-local" name="end" required></label></p>';
    $out .= '<p><label>' . esc_html__( 'Location', 'kvt-outlook' ) . '<br/><input type="text" name="location"></label></p>';
    $out .= '<p><label>' . esc_html__( 'Attendees (comma emails)', 'kvt-outlook' ) . '<br/><input type="text" name="attendees"></label></p>';
    $out .= '<p><label>' . esc_html__( 'Description', 'kvt-outlook' ) . '<br/><textarea name="description"></textarea></label></p>';
    $out .= '<p><label><input type="checkbox" name="online" value="1"> ' . esc_html__( 'Teams Online Meeting', 'kvt-outlook' ) . '</label></p>';
    $out .= '<p><button type="submit" class="button button-primary">' . esc_html__( 'Create Event', 'kvt-outlook' ) . '</button></p>';
    $out .= '</form>';
    return $out;
}
add_shortcode( 'kvt_outlook_create_event', 'kvt_shortcode_create_event' );

/**
 * Handle event creation
 */
add_action( 'admin_post_kvt_create_event', 'kvt_handle_create_event' );
function kvt_handle_create_event() {
    if ( ! is_user_logged_in() || ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'kvt_create_event' ) ) {
        wp_die( __( 'Invalid request', 'kvt-outlook' ) );
    }
    if ( ! kvt_is_connected() ) {
        wp_die( __( 'Not connected', 'kvt-outlook' ) );
    }
    $subject = sanitize_text_field( $_POST['subject'] ?? '' );
    $start   = sanitize_text_field( $_POST['start'] ?? '' );
    $end     = sanitize_text_field( $_POST['end'] ?? '' );
    $location= sanitize_text_field( $_POST['location'] ?? '' );
    $att_raw = sanitize_text_field( $_POST['attendees'] ?? '' );
    $desc    = wp_kses_post( $_POST['description'] ?? '' );
    $online  = ! empty( $_POST['online'] );

    $tz_string = wp_timezone_string();
    $start_dt = DateTime::createFromFormat( 'Y-m-d\TH:i', $start, wp_timezone() );
    $end_dt   = DateTime::createFromFormat( 'Y-m-d\TH:i', $end, wp_timezone() );
    if ( ! $subject || ! $start_dt || ! $end_dt ) {
        wp_safe_redirect( add_query_arg( [ 'kvt_event' => 'error', 'message' => urlencode( 'Invalid data' ) ], wp_get_referer() ) );
        exit;
    }
    $payload = [
        'subject' => $subject,
        'body'    => [ 'contentType' => 'HTML', 'content' => $desc ],
        'start'   => [ 'dateTime' => $start_dt->format( 'Y-m-d\TH:i:s' ), 'timeZone' => $tz_string ],
        'end'     => [ 'dateTime' => $end_dt->format( 'Y-m-d\TH:i:s' ), 'timeZone' => $tz_string ],
    ];
    if ( $location ) {
        $payload['location'] = [ 'displayName' => $location ];
    }
    if ( $att_raw ) {
        $emails = array_filter( array_map( 'trim', explode( ',', $att_raw ) ) );
        $payload['attendees'] = [];
        foreach ( $emails as $e ) {
            $payload['attendees'][] = [
                'emailAddress' => [ 'address' => $e ],
                'type' => 'required',
            ];
        }
    }
    if ( $online ) {
        $payload['isOnlineMeeting']    = true;
        $payload['onlineMeetingProvider'] = 'teamsForBusiness';
    }

    $res = kvt_graph_post( '/me/events', $payload );
    if ( is_wp_error( $res ) ) {
        $msg = urlencode( $res->get_error_message() );
        wp_safe_redirect( add_query_arg( [ 'kvt_event' => 'error', 'message' => $msg ], wp_get_referer() ) );
    } else {
        delete_transient( 'kvt_calendar_' . get_current_user_id() );
        wp_safe_redirect( add_query_arg( 'kvt_event', 'success', wp_get_referer() ) );
    }
    exit;
}

