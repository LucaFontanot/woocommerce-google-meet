<?php

namespace WGM;

class Settings
{
    // Opzioni separate per ogni sezione
    const OPT_ACCOUNT = 'wgm_account_settings';
    const OPT_CALENDAR = 'wgm_calendar_settings';
    const OPT_MEET = 'wgm_meet_settings';
    const OPT_EMAIL = 'wgm_email_settings';

    public static function init()
    {
        add_action('admin_menu', [__CLASS__, 'menu']);
        add_action('admin_init', [__CLASS__, 'register']);
        add_action('admin_init', [__CLASS__, 'handle_google_login']);
    }

    public static function menu()
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        add_menu_page('WGM', 'WGM', 'manage_options', 'wgm', [__CLASS__, 'render_dashboard'], 'dashicons-video-alt3');
        add_submenu_page('wgm', 'Impostazioni account', 'Impostazioni account', 'manage_options', 'wgm-account', [__CLASS__, 'render_account']);
        add_submenu_page('wgm', 'Impostazioni calendario', 'Impostazioni calendario', 'manage_options', 'wgm-calendar', [__CLASS__, 'render_calendar']);
        add_submenu_page('wgm', 'Impostazioni Google Meet', 'Impostazioni Google Meet', 'manage_options', 'wgm-meet', [__CLASS__, 'render_meet']);
        add_submenu_page('wgm', 'Impostazioni email', 'Impostazioni email', 'manage_options', 'wgm-email', [__CLASS__, 'render_email']);
    }

    public static function render_dashboard()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Non hai i permessi per accedere a questa pagina.', 'wgm'));
        }
        echo "<div class='wrap'><h1>WGM - Woo Google Meetings</h1><p>Benvenuto nel pannello di controllo WGM. Usa il menu a sinistra per accedere alle impostazioni specifiche.</p></div>";
    }

    public static function register()
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        // Account
        register_setting(self::OPT_ACCOUNT, self::OPT_ACCOUNT, [
            'sanitize_callback' => [__CLASS__, 'sanitize_account'],
        ]);
        add_settings_section('wgm_account_main', 'Impostazioni account', '__return_false', self::OPT_ACCOUNT);
        add_settings_field('auth_json', 'Credenziali Google API Console (JSON)', [__CLASS__, 'textarea'], self::OPT_ACCOUNT, 'wgm_account_main', [
            'key' => 'auth_json',
            'option' => self::OPT_ACCOUNT,
            'placeholder' => 'Incolla il JSON OAuth o Service Account',
            'description' => 'Crea le credenziali su <a href="https://console.developers.google.com/apis/credentials" target="_blank">Google API Console</a>. Leggi la <a href="https://github.com/googleapis/google-api-php-client/blob/main/docs/oauth-web.md#create-authorization-credentials" target="_blank">guida</a> per maggiori dettagli.',
        ]);
        // Pulsante Login con Google
        add_settings_field('google_login', 'Login con Google', [__CLASS__, 'google_login_button'], self::OPT_ACCOUNT, 'wgm_account_main', []);

        // Calendario
        register_setting(self::OPT_CALENDAR, self::OPT_CALENDAR, [
            'sanitize_callback' => [__CLASS__, 'sanitize_calendar'],
        ]);
        add_settings_section('wgm_calendar_main', 'Impostazioni calendario', '__return_false', self::OPT_CALENDAR);
        add_settings_field('calendar_id', 'Calendario fonte', [__CLASS__, 'radio'], self::OPT_CALENDAR, 'wgm_calendar_main', [
            'key' => 'calendar_id',
            'option' => self::OPT_CALENDAR,
            'options' => \WGM\GoogleClient::getCalendarsList(),
            'description' => 'Seleziona il calendario da cui verranno lette le disponibilità per le prenotazioni.'
        ]);
        add_settings_field('calendar_reservations_id', 'Calendario', [__CLASS__, 'radio'], self::OPT_CALENDAR, 'wgm_calendar_main', [
            'key' => 'calendar_reservations_id',
            'option' => self::OPT_CALENDAR,
            'options' => \WGM\GoogleClient::getCalendarsList(),
            'description' => 'Seleziona il calendario in cui verranno creati gli eventi delle prenotazioni.'
        ]);
        add_settings_field('event_language', 'Lingua prenotazione', [__CLASS__, 'text'], self::OPT_CALENDAR, 'wgm_calendar_main', [
            'key' => 'event_language',
            'option' => self::OPT_CALENDAR,
            'placeholder' => 'it',
        ]);
        add_settings_field('prefix', 'Prefisso prenotazione', [__CLASS__, 'text'], self::OPT_CALENDAR, 'wgm_calendar_main', [
            'key' => 'prefix',
            'option' => self::OPT_CALENDAR,
            'placeholder' => 'PRENOTAZIONE - ',
            'description' => 'Prefisso da aggiungere al titolo degli eventi creati per le prenotazioni.'
        ]);
        add_settings_field('event_color', 'Colore eventi', [__CLASS__, 'radioColors'], self::OPT_CALENDAR, 'wgm_calendar_main', [
            'key' => 'event_color',
            'option' => self::OPT_CALENDAR,
            'options' => \WGM\GoogleClient::getCalendarColors()
        ]);
        add_settings_field('timezone', 'Fuso orario', [__CLASS__, 'text'], self::OPT_CALENDAR, 'wgm_calendar_main', [
            'key' => 'timezone',
            'option' => self::OPT_CALENDAR,
            'placeholder' => 'Europe/Rome',
            'description' => 'Verifica il funzionamento del calendario cliccando <a href="' . \WGM\Availability::getRestUrl() . '" target="_blank">qui</a>.'
        ]);

        // Google Meet
        register_setting(self::OPT_MEET, self::OPT_MEET, [
            'sanitize_callback' => [__CLASS__, 'sanitize_meet'],
        ]);
        add_settings_section('wgm_meet_main', 'Impostazioni Google Meet', '__return_false', self::OPT_MEET);
        add_settings_field('enable_meet', 'Abilita Google Meet', [__CLASS__, 'checkbox'], self::OPT_MEET, 'wgm_meet_main', [
            'key' => 'enable_meet',
            'option' => self::OPT_MEET,
            'label' => 'Aggiungi automaticamente un link Google Meet agli eventi creati',
            'description' => 'Se abilitato, ogni evento creato includerà un link Google Meet per le videoconferenze. Verrà inviata una email con i dettagli dell\'evento e il link Google Meet.'
        ]);


        // Email
        register_setting(self::OPT_EMAIL, self::OPT_EMAIL, [
            'sanitize_callback' => [__CLASS__, 'sanitize_email'],
        ]);
        add_settings_section('wgm_email_main', 'Impostazioni email', '__return_false', self::OPT_EMAIL);
        add_settings_field('admin_email_enabled', 'Email amministratori abilitata', [__CLASS__, 'checkbox'], self::OPT_EMAIL, 'wgm_email_main', [
            'key' => 'admin_email_enabled',
            'option' => self::OPT_EMAIL,
            'label' => 'Invia una email agli amministratori con i dettagli della prenotazione',
            'description' => ''
        ]);
        add_settings_field('admin_email_list', 'Email amministratori', [__CLASS__, 'email_list'], self::OPT_EMAIL, 'wgm_email_main', [
            'key' => 'admin_email_list',
            'option' => self::OPT_EMAIL,
            'description' => 'Aggiungi gli indirizzi email degli amministratori che riceveranno le notifiche di nuova prenotazione.'
        ]);
        add_settings_field('admin_email_subject', 'Email amministratori titolo', [__CLASS__, 'text'], self::OPT_EMAIL, 'wgm_email_main', [
            'key' => 'admin_email_subject',
            'option' => self::OPT_EMAIL,
            'placeholder' => 'Nuova prenotazione: {{EVENT_SUMMARY}}',
            'description' => 'Sono disponibili i seguenti tag: {{EVENT_SUMMARY}}, [EVENT_START], [EVENT_END], [CUSTOMER_NAME], [CUSTOMER_EMAIL]'
        ]);
        add_settings_field('customer_email_enabled', 'Email cliente abilitata', [__CLASS__, 'checkbox'], self::OPT_EMAIL, 'wgm_email_main', [
            'key' => 'customer_email_enabled',
            'option' => self::OPT_EMAIL,
            'label' => 'Invia una email al cliente con i dettagli della prenotazione',
            'description' => ''
        ]);
        add_settings_field('customer_email_subject', 'Email cliente titolo', [__CLASS__, 'text'], self::OPT_EMAIL, 'wgm_email_main', [
            'key' => 'customer_email_subject',
            'option' => self::OPT_EMAIL,
            'placeholder' => 'Dettagli prenotazione: {{EVENT_SUMMARY}}',
            'description' => 'Sono disponibili i seguenti tag: {{EVENT_SUMMARY}}, [EVENT_START], [EVENT_END], [CUSTOMER_NAME], [CUSTOMER_EMAIL]'
        ]);

        // Personalizzazione template
        add_settings_section('wgm_email_personalization', 'Personalizzazione template', function () {
            echo '<p>Personalizza l\'aspetto delle email in uscita con i colori, il logo e i testi del tuo brand.</p>';
        }, self::OPT_EMAIL);
        add_settings_field('email_primary_color', 'Colore primario', [__CLASS__, 'color'], self::OPT_EMAIL, 'wgm_email_personalization', [
            'key' => 'email_primary_color',
            'option' => self::OPT_EMAIL,
            'default' => '#1a73e8',
            'description' => 'Colore principale per intestazioni, pulsanti e link.'
        ]);
        add_settings_field('email_accent_color', 'Colore accento', [__CLASS__, 'color'], self::OPT_EMAIL, 'wgm_email_personalization', [
            'key' => 'email_accent_color',
            'option' => self::OPT_EMAIL,
            'default' => '#e8f0fe',
            'description' => 'Colore secondario per sfondo dei banner informativi.'
        ]);
        add_settings_field('email_logo_url', 'URL logo', [__CLASS__, 'text'], self::OPT_EMAIL, 'wgm_email_personalization', [
            'key' => 'email_logo_url',
            'option' => self::OPT_EMAIL,
            'placeholder' => 'https://example.com/logo.png',
            'description' => 'URL dell\'immagine del logo da mostrare nell\'intestazione delle email. Lascia vuoto per non mostrare alcun logo.'
        ]);
        add_settings_field('email_header_text', 'Testo intestazione', [__CLASS__, 'text'], self::OPT_EMAIL, 'wgm_email_personalization', [
            'key' => 'email_header_text',
            'option' => self::OPT_EMAIL,
            'placeholder' => get_bloginfo('name'),
            'description' => 'Testo mostrato nell\'intestazione colorata delle email. Default: nome del sito.'
        ]);
        add_settings_field('email_footer_text', 'Testo pi&egrave; di pagina', [__CLASS__, 'text'], self::OPT_EMAIL, 'wgm_email_personalization', [
            'key' => 'email_footer_text',
            'option' => self::OPT_EMAIL,
            'placeholder' => 'Grazie per averci scelto!',
            'description' => 'Testo mostrato nel pi&egrave; di pagina delle email.'
        ]);
        add_settings_field('email_footer_links', 'Link pi&egrave; di pagina', [__CLASS__, 'textarea'], self::OPT_EMAIL, 'wgm_email_personalization', [
            'key' => 'email_footer_links',
            'option' => self::OPT_EMAIL,
            'placeholder' => "Privacy Policy|https://example.com/privacy\nTermini|https://example.com/terms",
            'description' => 'Link nel pi&egrave; di pagina. Uno per riga nel formato: Testo|URL'
        ]);
        add_settings_field('email_sender_name', 'Nome mittente', [__CLASS__, 'text'], self::OPT_EMAIL, 'wgm_email_personalization', [
            'key' => 'email_sender_name',
            'option' => self::OPT_EMAIL,
            'placeholder' => get_bloginfo('name'),
            'description' => 'Nome visualizzato come mittente delle email. Lascia vuoto per usare il default di WordPress.'
        ]);
        add_settings_field('email_reply_to', 'Indirizzo Reply-To', [__CLASS__, 'text'], self::OPT_EMAIL, 'wgm_email_personalization', [
            'key' => 'email_reply_to',
            'option' => self::OPT_EMAIL,
            'placeholder' => 'info@example.com',
            'description' => 'Indirizzo email a cui il destinatario pu&ograve; rispondere.'
        ]);
    }

    public static function sanitize_account($input)
    {
        $sanitized = [];
        if (isset($input['auth_json'])) {
            // Validate JSON structure but store as-is (contains OAuth credentials)
            $decoded = json_decode($input['auth_json'], true);
            if ($decoded !== null && isset($decoded['web'])) {
                $sanitized['auth_json'] = $input['auth_json'];
            }
        }
        // google_token is set programmatically via OAuth flow, not from settings form
        $existing = get_option(self::OPT_ACCOUNT, []);
        if (isset($existing['google_token'])) {
            $sanitized['google_token'] = $existing['google_token'];
        }
        return $sanitized;
    }

    public static function sanitize_calendar($input)
    {
        $sanitized = [];
        $string_keys = ['calendar_id', 'calendar_reservations_id', 'event_language', 'prefix', 'event_color', 'timezone'];
        foreach ($string_keys as $key) {
            if (isset($input[$key])) {
                $sanitized[$key] = sanitize_text_field($input[$key]);
            }
        }
        return $sanitized;
    }

    public static function sanitize_meet($input)
    {
        return [
            'enable_meet' => isset($input['enable_meet']) && $input['enable_meet'] === 'yes' ? 'yes' : 'no',
        ];
    }

    public static function sanitize_email($input)
    {
        $sanitized = [];
        $checkbox_keys = ['admin_email_enabled', 'customer_email_enabled'];
        foreach ($checkbox_keys as $key) {
            $sanitized[$key] = isset($input[$key]) && $input[$key] === 'yes' ? 'yes' : 'no';
        }
        // Admin email list — array of individual emails
        if (isset($input['admin_email_list']) && is_array($input['admin_email_list'])) {
            $sanitized['admin_email_list'] = array_values(array_filter(array_map('sanitize_email', $input['admin_email_list'])));
        } elseif (isset($input['admin_email_list']) && is_string($input['admin_email_list'])) {
            // Legacy: single comma-separated string
            $sanitized['admin_email_list'] = array_values(array_filter(array_map('sanitize_email', array_map('trim', explode(',', $input['admin_email_list'])))));
        }
        $text_keys = ['admin_email_subject', 'customer_email_subject'];
        foreach ($text_keys as $key) {
            if (isset($input[$key])) {
                $sanitized[$key] = sanitize_text_field($input[$key]);
            }
        }
        // Personalization fields
        $string_keys = [
            'email_primary_color',
            'email_accent_color',
            'email_logo_url',
            'email_header_text',
            'email_footer_text',
            'email_sender_name',
            'email_reply_to',
        ];
        foreach ($string_keys as $key) {
            if (isset($input[$key])) {
                $sanitized[$key] = sanitize_text_field($input[$key]);
            }
        }
        // Footer links — allow line breaks but strip HTML
        if (isset($input['email_footer_links'])) {
            $sanitized['email_footer_links'] = sanitize_textarea_field($input['email_footer_links']);
        }
        return $sanitized;
    }

    public static function get($key, $default = '', $option = self::OPT_ACCOUNT)
    {
        $opt = get_option($option, []);
        return $opt[$key] ?? $default;
    }

    public static function text($args)
    {
        $v = esc_attr(self::get($args['key'], '', $args['option'] ?? self::OPT_ACCOUNT));
        $placeholder = esc_attr($args['placeholder'] ?? '');
        echo "<input type='text' name='" . esc_attr($args['option'] ?? self::OPT_ACCOUNT) . "[" . esc_attr($args['key']) . "]' value='{$v}' class='regular-text' placeholder='{$placeholder}'/>";
        if (!empty($args['description'])) {
            echo '<p class="description">' . wp_kses($args['description'], ['a' => ['href' => [], 'target' => [], 'rel' => []]]) . '</p>';
        }
    }

    public static function textarea($args)
    {
        $v = esc_textarea(self::get($args['key'], '', $args['option'] ?? self::OPT_ACCOUNT));
        $placeholder = esc_attr($args['placeholder'] ?? '');
        echo "<textarea rows='10' style='width:100%' name='" . esc_attr($args['option'] ?? self::OPT_ACCOUNT) . "[" . esc_attr($args['key']) . "]' placeholder='{$placeholder}'>{$v}</textarea>";
        if (!empty($args['description'])) {
            echo '<p class="description">' . wp_kses($args['description'], ['a' => ['href' => [], 'target' => [], 'rel' => []]]) . '</p>';
        }
    }

    public static function radio($args)
    {
        $v = self::get($args['key'], '', $args['option'] ?? self::OPT_ACCOUNT);
        foreach ($args['options'] as $val => $label) {
            $checked = ($v === $val) ? 'checked' : '';
            echo '<label><input type="radio" name="' . esc_attr($args['option'] ?? self::OPT_ACCOUNT) . '[' . esc_attr($args['key']) . ']" value="' . esc_attr($val) . '" ' . $checked . '/> ' . esc_html($label) . '</label><br/>';
        }
        if (!empty($args['description'])) {
            echo '<p class="description">' . wp_kses($args['description'], ['a' => ['href' => [], 'target' => [], 'rel' => []]]) . '</p>';
        }
    }

    public static function checkbox($args)
    {
        $v = self::get($args['key'], 'no', $args['option'] ?? self::OPT_ACCOUNT);
        $checked = ($v === 'yes') ? 'checked' : '';
        echo '<label><input type="checkbox" name="' . esc_attr($args['option'] ?? self::OPT_ACCOUNT) . '[' . esc_attr($args['key']) . ']" value="yes" ' . $checked . '/> ' . esc_html($args['label'] ?? '') . '</label>';
        if (!empty($args['description'])) {
            echo '<p class="description">' . wp_kses($args['description'], ['a' => ['href' => [], 'target' => [], 'rel' => []]]) . '</p>';
        }
    }

    public static function radioColors($args)
    {
        $v = self::get($args['key'], '', $args['option'] ?? self::OPT_ACCOUNT);
        foreach ($args['options'] as $val => $color) {
            $checked = ($v == $val) ? 'checked' : '';
            $safe_color = sanitize_hex_color($color) ?: '#000000';
            echo '<label style="margin-right:10px;"><input type="radio" name="' . esc_attr($args['option'] ?? self::OPT_ACCOUNT) . '[' . esc_attr($args['key']) . ']" value="' . esc_attr($val) . '" ' . $checked . '/>'
                . '<span style="display:inline-block;width:20px;height:20px;background-color:' . esc_attr($safe_color) . ';border:1px solid #000;margin-left:5px;vertical-align:middle;"></span></label>';
        }
    }

    public static function email_list($args)
    {
        $emails = self::get($args['key'], [], $args['option'] ?? self::OPT_EMAIL);
        if (!is_array($emails)) {
            $emails = !empty($emails) ? array_map('trim', explode(',', $emails)) : [];
        }
        if (empty($emails)) {
            $emails = [''];
        }
        $option_name = esc_attr(($args['option'] ?? self::OPT_EMAIL) . '[' . $args['key'] . '][]');
        echo '<div id="wgm-email-list" style="max-width:400px;">';
        foreach ($emails as $i => $email) {
            echo '<div class="wgm-email-row" style="display:flex;align-items:center;gap:6px;margin-bottom:6px;">';
            echo '<input type="email" name="' . $option_name . '" value="' . esc_attr($email) . '" class="regular-text" placeholder="admin@example.com" style="flex:1;" />';
            echo '<button type="button" class="button button-secondary wgm-remove-email" title="Rimuovi">&times;</button>';
            echo '</div>';
        }
        // Hidden template for cloning
        echo '<div class="wgm-email-row wgm-email-row-template" style="display:none;align-items:center;gap:6px;margin-bottom:6px;">';
        echo '<input type="email" name="' . $option_name . '" value="" class="regular-text" placeholder="admin@example.com" style="flex:1;" disabled />';
        echo '<button type="button" class="button button-secondary wgm-remove-email" title="Rimuovi">&times;</button>';
        echo '</div>';
        echo '<button type="button" class="button wgm-add-email" style="margin-top:4px;">+ Aggiungi email</button>';
        echo '</div>';
        if (!empty($args['description'])) {
            echo '<p class="description">' . wp_kses($args['description'], ['a' => ['href' => [], 'target' => [], 'rel' => []]]) . '</p>';
        }
    }

    public static function color($args)
    {
        $v = esc_attr(self::get($args['key'], $args['default'] ?? '#000000', $args['option'] ?? self::OPT_EMAIL));
        echo '<input type="color" name="' . esc_attr($args['option'] ?? self::OPT_EMAIL) . '[' . esc_attr($args['key']) . ']" value="' . $v . '" />';
        echo ' <code style="margin-left:4px;">' . $v . '</code>';
        if (!empty($args['description'])) {
            echo '<p class="description">' . wp_kses($args['description'], ['a' => ['href' => [], 'target' => [], 'rel' => []]]) . '</p>';
        }
    }

    public static function google_login_button($args)
    {
        $login_url = wp_nonce_url(admin_url('admin.php?page=wgm-account&action=wgm_google_login'), 'wgm_google_login');
        echo '<a href="' . esc_url($login_url) . '" class="button button-primary">Login con Google</a>';
        echo '<p class="description">Accedi con il tuo account Google per autorizzare l\'applicazione.</p>';
        if (\WGM\GoogleClient::getEmail() != null) {
            echo '<p>Connesso come: <strong>' . esc_html(\WGM\GoogleClient::getEmail()) . '</strong></p>';
        }
    }

    // Gestione endpoint login e callback
    public static function handle_google_login()
    {
        if (!is_admin() || !current_user_can('manage_options')) return;
        if (isset($_GET['action']) && $_GET['action'] === 'wgm_google_login') {
            check_admin_referer('wgm_google_login');
            $auth_url = \WGM\GoogleClient::getAuthUrl();
            wp_redirect(esc_url_raw($auth_url));
            exit;
        }
        // Callback: salva token
        if (isset($_GET['code']) && isset($_GET['page']) && $_GET['page'] === 'wgm-account') {
            // OAuth callback from Google — state parameter is handled by Google client
            $token = \WGM\GoogleClient::fetchAccessTokenWithAuthCode(sanitize_text_field($_GET['code']));
            if ($token) {
                $opt = get_option(self::OPT_ACCOUNT, []);
                $opt['google_token'] = $token;
                update_option(self::OPT_ACCOUNT, $opt);
                wp_redirect(esc_url_raw(admin_url('admin.php?page=wgm-account&google_login=success')));
                exit;
            } else {
                wp_redirect(esc_url_raw(admin_url('admin.php?page=wgm-account&google_login=error')));
                exit;
            }
        }
    }

    public static function render_account()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Non hai i permessi per accedere a questa pagina.', 'wgm'));
        }
        self::render_section('Impostazioni account', self::OPT_ACCOUNT);
    }

    public static function render_calendar()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Non hai i permessi per accedere a questa pagina.', 'wgm'));
        }
        self::render_section('Impostazioni calendario', self::OPT_CALENDAR);
    }

    public static function render_meet()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Non hai i permessi per accedere a questa pagina.', 'wgm'));
        }
        self::render_section('Impostazioni Google Meet', self::OPT_MEET);
    }

    public static function render_email()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Non hai i permessi per accedere a questa pagina.', 'wgm'));
        }
        wp_enqueue_script(
            'wgm-email-settings',
            WGM_URL . 'assets/admin/email-settings.js',
            ['jquery'],
            '1.0',
            true
        );
        self::render_section('Impostazioni email', self::OPT_EMAIL);
    }

    private static function render_section($title, $option)
    {
        echo "<div class='wrap'><h1>" . esc_html($title) . "</h1><form method='post' action='options.php'>";
        settings_fields($option);
        do_settings_sections($option);
        submit_button();
        echo "</form></div>";
    }
}