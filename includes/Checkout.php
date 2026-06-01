<?php

namespace WGM;

use WC_Customer;

class Checkout
{
    // Aggiungi checkbox WGM nel backend prodotto
    public static function add_wgm_option_to_product()
    {
        global $product, $post;
        echo '<div class="options_group">';
        woocommerce_wp_checkbox([
            'id' => '_wgm_enabled',
            'label' => __('Abilita WGM', 'wgm'),
            'desc_tip' => true,
            'description' => __('Abilita la selezione evento WGM per questo prodotto.', 'wgm'),
            'value' => get_post_meta($post->ID, '_wgm_enabled', true)
        ]);
        echo '</div>';
    }

    // Salva il valore del checkbox WGM
    public static function save_wgm_option($post_id)
    {
        $enabled = isset($_POST['_wgm_enabled']) ? 'yes' : 'no';
        update_post_meta($post_id, '_wgm_enabled', $enabled);
    }

    public static function init()
    {
        add_action('woocommerce_before_add_to_cart_button', [__CLASS__, 'product_event_id_field']);
        add_filter('woocommerce_add_cart_item_data', [__CLASS__, 'add_event_id_to_cart_item'], 10, 4);
        add_filter('woocommerce_get_item_data', [__CLASS__, 'display_event_id_in_cart'], 10, 2);
        add_action('woocommerce_checkout_create_order_line_item', [__CLASS__, 'add_event_id_to_order_item'], 10, 4);
        // Enqueue script solo nella pagina prodotto
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_event_picker_script']);
        // Backend: aggiungi e salva checkbox
        add_action('woocommerce_product_options_general_product_data', [__CLASS__, 'add_wgm_option_to_product']);
        add_action('woocommerce_process_product_meta', [__CLASS__, 'save_wgm_option']);
        // Validazione evento obbligatoria
        add_filter('woocommerce_add_to_cart_validation', [__CLASS__, 'validate_event_id_on_add_to_cart'], 10, 3);
        // Invio email al cliente dopo pagamento completato
        add_action('woocommerce_order_status_completed', [__CLASS__, 'send_customer_meeting_email_on_payment'], 10, 1);
        add_action('woocommerce_admin_order_actions_end', [__CLASS__, 'add_force_meeting_email_button']);
        add_action('admin_post_wgm_force_meeting_email', [__CLASS__, 'handle_force_meeting_email_action']);
        // Nascondi meta wgm_event_id e wgm_reserved_event_id nel frontend
        add_filter('woocommerce_hidden_order_itemmeta', [__CLASS__, 'hide_wgm_meta']);
        add_filter('woocommerce_blocks_order_item_meta', [__CLASS__, 'hide_wgm_meta_blocks']);
    }

    // Enqueue script e stile per il selettore di eventi
    public static function enqueue_event_picker_script()
    {
        if (is_product()) {
            global $post;
            $enabled = get_post_meta($post->ID, '_wgm_enabled', true);
            if ($enabled === 'yes') {
                wp_enqueue_script(
                    'wgm-event-picker',
                    plugins_url('../assets/product/eventPicker.js', __FILE__),
                    ['jquery'],
                    '0.1',
                    true
                );
                wp_localize_script('wgm-event-picker', 'WGM_AVAIL', [
                    'endpoint' => rest_url('wc-gmeet/v1/availability'),
                ]);
                wp_enqueue_style('wgm-event-picker-css', plugins_url('../assets/product/eventPicker.css', __FILE__), [], '0.1');
            }
        }
    }

    // Mostra il campo nella pagina prodotto
    public static function product_event_id_field()
    {
        global $post;
        $enabled = get_post_meta($post->ID, '_wgm_enabled', true);
        if ($enabled !== 'yes') return;
        echo '<div id="wgm-picker"></div>';
    }

    // Validazione obbligatoria evento
    public static function validate_event_id_on_add_to_cart($passed, $product_id, $quantity)
    {
        $enabled = get_post_meta($product_id, '_wgm_enabled', true);
        if ($enabled === 'yes') {
            if (!isset($_POST['wgm_event_id']) || empty($_POST['wgm_event_id'])) {
                wc_add_notice(__('Devi selezionare un evento.', 'wgm'), 'error');
                return false;
            }
            $event_id = $_POST['wgm_event_id'];
            $event = \WGM\GoogleClient::getEventById($event_id);
            if ($event === null) {
                wc_add_notice(__('Evento non valido.', 'wgm'), 'error');
                return false;
            }
            if ($event->getEnd()->getDateTime() < (new \DateTime())->format(\DateTime::RFC3339)) {
                wc_add_notice(__('L\'evento selezionato è già passato.', 'wgm'), 'error');
                return false;
            }
            if (WC()->cart && is_object(WC()->cart)) {
                foreach (WC()->cart->get_cart() as $cart_item) {
                    if (isset($cart_item['wgm_event_id']) && $cart_item['wgm_event_id'] == $event_id) {
                        wc_add_notice(__('Hai già un prodotto con questo evento nel carrello.', 'wgm'), 'error');
                        return false;
                    }
                }
            }
        }
        return $passed;
    }

    // Salva l'ID evento nel carrello
    public static function add_event_id_to_cart_item($cart_item_data, $product_id, $variation_id, $quantity)
    {
        $enabled = get_post_meta($product_id, '_wgm_enabled', true);
        if ($enabled === 'yes' && isset($_POST['wgm_event_id']) && !empty($_POST['wgm_event_id'])) {
            $cart_item_data['wgm_event_id'] = $_POST['wgm_event_id'];
        }
        return $cart_item_data;
    }

    // Mostra l'ID evento nel carrello
    public static function display_event_id_in_cart($item_data, $cart_item)
    {
        if (isset($cart_item['wgm_event_id'])) {
            $event = \WGM\GoogleClient::getEventById($cart_item['wgm_event_id']);
            if ($event) {
                $startTime = new \DateTime($event->getStart()->getDateTime() ?: $event->getStart()->getDate());
                $endTime = new \DateTime($event->getEnd()->getDateTime() ?: $event->getEnd()->getDate());
                $formatted = $startTime->format('d/m/Y H:i') . ' - ' . $endTime->format('H:i');
                $item_data[] = [
                    'key' => __('Prenotazione', 'wgm'),
                    'value' => esc_html($formatted)
                ];
            }

        }
        return $item_data;
    }

    // Salva l'ID evento come meta privato della riga d'ordine
    public static function add_event_id_to_order_item($item, $cart_item_key, $values, $order)
    {
        if (isset($values['wgm_event_id'])) {
            $event = \WGM\GoogleClient::getEventById($values['wgm_event_id']);
            // Salva l'ID evento come meta privato
            $item->add_meta_data('_wgm_event_id', $values['wgm_event_id'], true);
            if ($event) {
                $startTime = new \DateTime($event->getStart()->getDateTime() ?: $event->getStart()->getDate());
                $endTime = new \DateTime($event->getEnd()->getDateTime() ?: $event->getEnd()->getDate());
                $formatted = $startTime->format('d/m/Y H:i') . ' - ' . $endTime->format('H:i');
                $item->add_meta_data(__('Prenotazione', 'wgm'), $formatted, true);
            }
        }
    }

    /**
     * Invia la mail al cliente con il link del meeting dopo il pagamento
     */
    public static function send_customer_meeting_email_on_payment($order_id)
    {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        // Controlla se l'invio email cliente è abilitato
        $customer = new WC_Customer($order->get_customer_id());
        foreach ($order->get_items() as $item) {
            $event_id = $item->get_meta('_wgm_event_id');
            if (!$event_id) {
                continue;
            }
            $event = \WGM\GoogleClient::getEventById($event_id);
            if (!$event) {
                continue;
            }
            $reservationEvent = \WGM\Availability::reserve($event, $order, $customer);
            if (!$reservationEvent){
                continue;
            }
            $item->add_meta_data('_wgm_reserved_event_id', $reservationEvent->getId());
            $meetingUrl = $reservationEvent->getHangoutLink() ?? '';
            if ($meetingUrl) {
                $item->add_meta_data(__('Link riunione', 'wgm'), $meetingUrl);
            }
            $item->save();
            // Prepara la mail
            $sendCustomerEmail = \WGM\Settings::get('customer_email_enabled', 'no', \WGM\Settings::OPT_EMAIL) === 'yes';
            if ($sendCustomerEmail){
                $mail = new \WGM\Email($event, $order, $customer);
                $mail->setMeetingUrl($meetingUrl);
                $subject = \WGM\Settings::get('customer_email_subject', 'La tua prenotazione', \WGM\Settings::OPT_EMAIL);
                $body = \WGM\Settings::get('customer_email_template', '[MEETING_URL]', \WGM\Settings::OPT_EMAIL);
                $mail->setSubject($subject);
                $mail->setBody($body);
                $mail->send($customer->get_email());
            }
            $sendAdminEmail = \WGM\Settings::get('admin_email_enabled', 'no', \WGM\Settings::OPT_EMAIL) === 'yes';
            if ($sendAdminEmail){
                $adminEmails = explode(',', \WGM\Settings::get('admin_email_list', '', \WGM\Settings::OPT_EMAIL));
                foreach ($adminEmails as $adminEmail) {
                    $adminEmail = trim($adminEmail);
                    if (is_email($adminEmail)) {
                        $mail = new \WGM\Email($event, $order, $customer);
                        $mail->setMeetingUrl($meetingUrl);
                        $subject = \WGM\Settings::get('admin_email_subject', 'Nuova prenotazione', \WGM\Settings::OPT_EMAIL);
                        $body = \WGM\Settings::get('admin_email_template', '[MEETING_URL]', \WGM\Settings::OPT_EMAIL);
                        $mail->setSubject($subject);
                        $mail->setBody($body);
                        $mail->send($adminEmail);
                    }
                }
            }
        }
    }

    /**
     * Aggiunge il pulsante per forzare l'invio della mail meeting nell'admin ordine
     */
    public static function add_force_meeting_email_button($order)
    {
        $url = wp_nonce_url(
            admin_url('admin-post.php?action=wgm_force_meeting_email&order_id=' . $order->get_id()),
            'wgm_force_meeting_email_' . $order->get_id()
        );
        echo '<a class="button tips wgm-force-meeting-email" href="' . esc_url($url) . '" data-tip="Invia nuovamente email meeting"><span class="dashicons dashicons-email"></span></a>';
    }

    /**
     * Gestisce l'azione di invio forzato della mail meeting
     */
    public static function handle_force_meeting_email_action()
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die('Non autorizzato');
        }
        $order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
        if (!$order_id || !wp_verify_nonce($_GET['_wpnonce'], 'wgm_force_meeting_email_' . $order_id)) {
            wp_die('Nonce non valido');
        }
        self::send_customer_meeting_email_on_payment($order_id);
        wp_redirect(esc_url_raw(admin_url('post.php?post=' . $order_id . '&action=edit')));
        exit;
    }

    // Nascondi i meta wgm_event_id e wgm_reserved_event_id nel frontend
    public static function hide_wgm_meta($hidden_meta_keys)
    {
        $hidden_meta_keys[] = 'wgm_event_id';
        $hidden_meta_keys[] = 'wgm_reserved_event_id';
        return $hidden_meta_keys;
    }

    public static function hide_wgm_meta_blocks($item_meta)
    {
        // Rimuovi i meta wgm_event_id e wgm_reserved_event_id dai blocchi
        unset($item_meta['wgm_event_id']);
        unset($item_meta['wgm_reserved_event_id']);
        return $item_meta;
    }
}