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
        if (!current_user_can('manage_options')) {
            return;
        }
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
        register_setting(self::OPT_ACCOUNT, self::OPT_ACCOUNT);
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
        register_setting(self::OPT_CALENDAR, self::OPT_CALENDAR);
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
        register_setting(self::OPT_MEET, self::OPT_MEET);
        add_settings_section('wgm_meet_main', 'Impostazioni Google Meet', '__return_false', self::OPT_MEET);
        add_settings_field('enable_meet', 'Abilita Google Meet', [__CLASS__, 'checkbox'], self::OPT_MEET, 'wgm_meet_main', [
            'key' => 'enable_meet',
            'option' => self::OPT_MEET,
            'label' => 'Aggiungi automaticamente un link Google Meet agli eventi creati',
            'description' => 'Se abilitato, ogni evento creato includerà un link Google Meet per le videoconferenze. Verrà inviata una email con i dettagli dell\'evento e il link Google Meet.'
        ]);


        // Email
        register_setting(self::OPT_EMAIL, self::OPT_EMAIL);
        add_settings_section('wgm_email_main', 'Impostazioni email', '__return_false', self::OPT_EMAIL);
        add_settings_field('admin_email_enabled', 'Email amministratori abilitata', [__CLASS__, 'checkbox'], self::OPT_EMAIL, 'wgm_email_main', [
            'key' => 'admin_email_enabled',
            'option' => self::OPT_EMAIL,
            'label' => 'Invia una email agli amministratori con i dettagli della prenotazione',
            'description' => ''
        ]);
        add_settings_field('admin_email_list', 'Email amministratori', [__CLASS__, 'text'], self::OPT_EMAIL, 'wgm_email_main', [
            'key' => 'admin_email_list',
            'option' => self::OPT_EMAIL,
            'placeholder' => 'example@example.com',
            'description' => 'Indirizzi email separati da virgola. Le email di notifica verranno inviate a questi indirizzi quando viene creata una nuova prenotazione.'
        ]);
        add_settings_field('admin_email_subject', 'Email amministratori titolo', [__CLASS__, 'text'], self::OPT_EMAIL, 'wgm_email_main', [
            'key' => 'admin_email_subject',
            'option' => self::OPT_EMAIL,
            'placeholder' => 'Nuova prenotazione: [EVENT_SUMMARY]',
            'description' => 'Sono disponibili i seguenti tag: [EVENT_SUMMARY], [EVENT_START], [EVENT_END], [CUSTOMER_NAME], [CUSTOMER_EMAIL]'
        ]);
        add_settings_field('admin_email_template', 'Email amministratori', [__CLASS__, 'textarea'], self::OPT_EMAIL, 'wgm_email_main', [
            'key' => 'admin_email_template',
            'option' => self::OPT_EMAIL,
            'description' => 'Sono disponibili i seguenti tag: [MEET_LINK], [EVENT_SUMMARY], [EVENT_START], [EVENT_END], [EVENT_DESCRIPTION], [CUSTOMER_NAME], [CUSTOMER_EMAIL]',
            'placeholder' => 'HTML Email Template',
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
            'placeholder' => 'Dettagli prenotazione: [EVENT_SUMMARY]',
            'description' => 'Sono disponibili i seguenti tag: [EVENT_SUMMARY], [EVENT_START], [EVENT_END], [CUSTOMER_NAME], [CUSTOMER_EMAIL]'
        ]);
        add_settings_field('customer_email_template', 'Email cliente', [__CLASS__, 'textarea'], self::OPT_EMAIL, 'wgm_email_main', [
            'key' => 'customer_email_template',
            'option' => self::OPT_EMAIL,
            'description' => 'Sono disponibili i seguenti tag: [MEET_LINK], [EVENT_SUMMARY], [EVENT_START], [EVENT_END], [EVENT_DESCRIPTION], [CUSTOMER_NAME], [CUSTOMER_EMAIL]',
            'placeholder' => 'HTML Email Template',
        ]);
    }

    public static function get($key, $default = '', $option = self::OPT_ACCOUNT)
    {
        $opt = get_option($option, []);
        return $opt[$key] ?? $default;
    }

    public static function text($args)
    {
        $v = esc_attr(self::get($args['key'], '', $args['option'] ?? self::OPT_ACCOUNT));
        echo "<input type='text' name='" . ($args['option'] ?? self::OPT_ACCOUNT) . "[{$args['key']}]' value='{$v}' class='regular-text' placeholder='{$args['placeholder']}'/>";
        if (!empty($args['description'])) {
            echo "<p class='description'>{$args['description']}</p>";
        }
    }

    public static function textarea($args)
    {
        $v = esc_textarea(self::get($args['key'], '', $args['option'] ?? self::OPT_ACCOUNT));
        echo "<textarea rows='10' style='width:100%' name='" . ($args['option'] ?? self::OPT_ACCOUNT) . "[{$args['key']}]' placeholder='{$args['placeholder']}'>{$v}</textarea>";
        if (!empty($args['description'])) {
            echo "<p class='description'>{$args['description']}</p>";
        }
    }

    public static function radio($args)
    {
        $v = self::get($args['key'], '', $args['option'] ?? self::OPT_ACCOUNT);
        foreach ($args['options'] as $val => $label) {
            $checked = ($v === $val) ? 'checked' : '';
            echo "<label><input type='radio' name='" . ($args['option'] ?? self::OPT_ACCOUNT) . "[{$args['key']}]' value='{$val}' {$checked}/> {$label}</label><br/>";
        }
        if (!empty($args['description'])) {
            echo "<p class='description'>{$args['description']}</p>";
        }
    }

    public static function checkbox($args)
    {
        $v = self::get($args['key'], 'no', $args['option'] ?? self::OPT_ACCOUNT);
        $checked = ($v === 'yes') ? 'checked' : '';
        echo "<label><input type='checkbox' name='" . ($args['option'] ?? self::OPT_ACCOUNT) . "[{$args['key']}]' value='yes' {$checked}/> {$args['label']}</label>";
        if (!empty($args['description'])) {
            echo "<p class='description'>{$args['description']}</p>";
        }
    }

    public static function radioColors($args)
    {
        $v = self::get($args['key'], '', $args['option'] ?? self::OPT_ACCOUNT);
        foreach ($args['options'] as $val => $color) {
            $checked = ($v == $val) ? 'checked' : '';
            echo "<label style='margin-right:10px;'><input type='radio' name='" . ($args['option'] ?? self::OPT_ACCOUNT) . "[{$args['key']}]' value='{$val}' {$checked}/>"
                . "<span style='display:inline-block;width:20px;height:20px;background-color:{$color};border:1px solid #000;margin-left:5px;vertical-align:middle;'></span></label>";
        }
    }

    public static function google_login_button($args)
    {
        $login_url = esc_url(admin_url('admin.php?page=wgm-account&action=wgm_google_login'));
        echo "<a href='{$login_url}' class='button button-primary'>Login con Google</a>";
        echo "<p class='description'>Accedi con il tuo account Google per autorizzare l'applicazione.</p>";
        if (\WGM\GoogleClient::getEmail() != null) {
            echo "<p>Connesso come: <strong>" . esc_html(\WGM\GoogleClient::getEmail()) . "</strong></p>";
        }
    }

    // Gestione endpoint login e callback
    public static function handle_google_login()
    {
        if (!is_admin() || !current_user_can('manage_options')) return;
        if (isset($_GET['action']) && $_GET['action'] === 'wgm_google_login') {
            $auth_url = \WGM\GoogleClient::getAuthUrl();
            wp_redirect($auth_url);
            exit;
        }
        // Callback: salva token
        if (isset($_GET['code']) && isset($_GET['page']) && $_GET['page'] === 'wgm-account') {
            $token = \WGM\GoogleClient::fetchAccessTokenWithAuthCode($_GET['code']);
            if ($token) {
                $opt = get_option(self::OPT_ACCOUNT, []);
                $opt['google_token'] = $token;
                update_option(self::OPT_ACCOUNT, $opt);
                wp_redirect(admin_url('admin.php?page=wgm-account&google_login=success'));
                exit;
            } else {
                wp_redirect(admin_url('admin.php?page=wgm-account&google_login=error'));
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
        self::render_section('Impostazioni email', self::OPT_EMAIL);
    }

    private static function render_section($title, $option)
    {
        echo "<div class='wrap'><h1>{$title}</h1><form method='post' action='options.php'>";
        settings_fields($option);
        do_settings_sections($option);
        submit_button();
        echo "</form></div>";
    }
}