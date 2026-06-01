<?php

namespace WGM;

use Google\Service\Calendar\Event;
use WC_Customer;
use WC_Order;

/**
 * Email notification dispatcher.
 *
 * Loads HTML template files, applies branding/personalization,
 * replaces placeholders with event/order/customer data, and sends.
 */
class EmailDispatcher
{
    /**
     * Template types.
     */
    const TYPE_ADMIN_NOTIFICATION    = 'admin-notification';
    const TYPE_CUSTOMER_NOTIFICATION = 'customer-notification';

    /**
     * Default colors when none configured.
     */
    const DEFAULT_PRIMARY_COLOR = '#1a73e8';
    const DEFAULT_ACCENT_COLOR  = '#e8f0fe';

    /**
     * Send admin notification.
     */
    public static function sendAdminNotification(
        Event $event,
        WC_Order $order,
        WC_Customer $customer,
        string $meetingUrl
    ): bool {
        if (Settings::get('admin_email_enabled', 'no', Settings::OPT_EMAIL) !== 'yes') {
            return false;
        }

        $adminEmails = self::getAdminRecipients();
        if (empty($adminEmails)) {
            return false;
        }

        $subject = Settings::get('admin_email_subject', 'Nuova prenotazione: {{EVENT_SUMMARY}}', Settings::OPT_EMAIL);
        $data    = self::buildTemplateData($event, $order, $customer, $meetingUrl);

        $body = self::render(self::TYPE_ADMIN_NOTIFICATION, $data);
        $body = self::applyBranding($body);
        $body = self::replacePlaceholders($body, $data);

        $subject = self::replacePlaceholders($subject, $data);

        $sent = false;
        foreach ($adminEmails as $to) {
            if (self::send($to, $subject, $body)) {
                $sent = true;
            }
        }
        return $sent;
    }

    /**
     * Send customer notification.
     */
    public static function sendCustomerNotification(
        Event $event,
        WC_Order $order,
        WC_Customer $customer,
        string $meetingUrl
    ): bool {
        if (Settings::get('customer_email_enabled', 'no', Settings::OPT_EMAIL) !== 'yes') {
            return false;
        }

        $to      = $customer->get_email();
        $subject = Settings::get('customer_email_subject', 'Dettagli prenotazione: {{EVENT_SUMMARY}}', Settings::OPT_EMAIL);
        $data    = self::buildTemplateData($event, $order, $customer, $meetingUrl);

        $body = self::render(self::TYPE_CUSTOMER_NOTIFICATION, $data);
        $body = self::applyBranding($body);
        $body = self::replacePlaceholders($body, $data);

        $subject = self::replacePlaceholders($subject, $data);

        return self::send($to, $subject, $body);
    }

    /**
     * Load a template file and return its contents.
     *
     * @param string $type Template type (use TYPE_* constants).
     * @return string Raw template HTML.
     */
    public static function loadTemplate(string $type): string
    {
        $file = WGM_PATH . 'templates/emails/' . $type . '.php';

        if (!file_exists($file)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
                trigger_error('WGM: Email template not found: ' . esc_html($file), E_USER_WARNING);
            }
            return self::fallbackTemplate($type);
        }

        ob_start();
        include $file;
        return ob_get_clean();
    }

    /**
     * Render a template with conditional blocks resolved.
     *
     * This handles {{MEETING_URL_ROW}}, {{MEETING_URL_BUTTON}},
     * {{EVENT_DESCRIPTION_BLOCK}}, {{LOGO}}, {{LOGO_MARGIN}}
     * before final placeholder replacement.
     */
    public static function render(string $type, array $data): string
    {
        $html = self::loadTemplate($type);

        // Resolve conditional blocks
        $html = self::resolveConditionalBlocks($html, $data);

        return $html;
    }

    /**
     * Resolve conditional blocks in the template.
     *
     * Handles:
     *  - {{MEETING_URL_ROW}}      — Table row with meeting link
     *  - {{MEETING_URL_BUTTON}}   — CTA button for meeting link
     *  - {{EVENT_DESCRIPTION_BLOCK}} — Description section
     *  - {{LOGO}}                 — Logo <img> or empty
     *  - {{LOGO_MARGIN}}          — Margin for header text when logo present/absent
     */
    protected static function resolveConditionalBlocks(string $html, array $data): string
    {
        $meetingUrl = $data['{{MEETING_URL}}'] ?? '';

        // Meeting URL table row
        if (!empty($meetingUrl)) {
            $meetingRow = '
                                <tr>
                                    <td style="padding:8px 12px 8px 0;color:#555;font-size:13px;font-weight:600;width:120px;vertical-align:top;">Link Meet:</td>
                                    <td style="padding:8px 0;color:#1a1a2e;font-size:14px;">
                                        <a href="' . esc_url($meetingUrl) . '" style="color:{{PRIMARY_COLOR}};text-decoration:none;word-break:break-all;">' . esc_html($meetingUrl) . '</a>
                                    </td>
                                </tr>';
        } else {
            $meetingRow = '';
        }
        $html = str_replace('{{MEETING_URL_ROW}}', $meetingRow, $html);

        // Meeting URL button
        if (!empty($meetingUrl)) {
            $meetingButton = '
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px;">
                                <tr>
                                    <td align="center">
                                        <a href="' . esc_url($meetingUrl) . '" target="_blank" style="display:inline-block;background-color:{{PRIMARY_COLOR}};color:#ffffff;font-size:15px;font-weight:700;text-decoration:none;padding:14px 36px;border-radius:6px;text-align:center;">
                                            &#128279; Partecipa alla riunione
                                        </a>
                                    </td>
                                </tr>
                            </table>';
        } else {
            $meetingButton = '';
        }
        $html = str_replace('{{MEETING_URL_BUTTON}}', $meetingButton, $html);

        // Event description block
        $description = $data['{{EVENT_DESCRIPTION}}'] ?? '';
        if (!empty($description)) {
            $descBlock = '
                            <h2 style="color:#1a1a2e;font-size:16px;font-weight:700;margin:0 0 16px 0;padding-bottom:8px;border-bottom:2px solid{{PRIMARY_COLOR}};">
                                Note
                            </h2>
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px;">
                                <tr>
                                    <td style="padding:12px;background-color:#f7f8fa;border-radius:6px;color:#1a1a2e;font-size:13px;line-height:1.6;white-space:pre-line;">' . esc_html($description) . '</td>
                                </tr>
                            </table>';
        } else {
            $descBlock = '';
        }
        $html = str_replace('{{EVENT_DESCRIPTION_BLOCK}}', $descBlock, $html);

        // Logo
        $logoUrl = Settings::get('email_logo_url', '', Settings::OPT_EMAIL);
        if (!empty($logoUrl)) {
            $logo = '<img src="' . esc_url($logoUrl) . '" alt="' . esc_attr($data['{{SITE_NAME}}'] ?? '') . '" style="max-width:180px;height:auto;display:block;margin:0 auto 12px auto;" />';
            $html = str_replace('{{LOGO_MARGIN}}', '6px 0 0 0', $html);
        } else {
            $logo = '';
            $html = str_replace('{{LOGO_MARGIN}}', '0', $html);
        }
        $html = str_replace('{{LOGO}}', $logo, $html);

        return $html;
    }

    /**
     * Apply branding/personalization to the template.
     *
     * Injects colors, header/footer text, and links from settings.
     */
    public static function applyBranding(string $html): string
    {
        $primaryColor = Settings::get('email_primary_color', self::DEFAULT_PRIMARY_COLOR, Settings::OPT_EMAIL);
        $accentColor  = Settings::get('email_accent_color', self::DEFAULT_ACCENT_COLOR, Settings::OPT_EMAIL);
        $headerText   = Settings::get('email_header_text', get_bloginfo('name'), Settings::OPT_EMAIL);
        $footerText   = Settings::get('email_footer_text', '', Settings::OPT_EMAIL);
        $footerLinks  = Settings::get('email_footer_links', '', Settings::OPT_EMAIL);

        $replacements = [
            '{{PRIMARY_COLOR}}' => esc_attr($primaryColor),
            '{{ACCENT_COLOR}}'  => esc_attr($accentColor),
            '{{HEADER_TEXT}}'   => esc_html($headerText),
            '{{FOOTER_TEXT}}'   => esc_html($footerText),
            '{{FOOTER_LINKS}}'  => self::formatFooterLinks($footerLinks),
            '{{CURRENT_YEAR}}'  => esc_html(date('Y')),
        ];

        return strtr($html, $replacements);
    }

    /**
     * Replace data placeholders in the template.
     *
     * Supports both {{PLACEHOLDER}} and legacy [PLACEHOLDER] formats.
     */
    public static function replacePlaceholders(string $html, array $data): string
    {
        // Build legacy bracket-format equivalents
        $legacy = [];
        foreach ($data as $key => $value) {
            $legacyKey = '[' . substr($key, 2, -2) . ']';
            $legacy[$legacyKey] = $value;
        }

        // Also add MEET_LINK legacy alias
        if (isset($data['{{MEETING_URL}}'])) {
            $legacy['[MEET_LINK]'] = $data['{{MEETING_URL}}'];
        }

        $all = array_merge($data, $legacy);
        return strtr($html, $all);
    }

    /**
     * Build the data array for template placeholders.
     */
    protected static function buildTemplateData(
        Event $event,
        WC_Order $order,
        WC_Customer $customer,
        string $meetingUrl
    ): array {
        $start = $event->getStart();
        $end   = $event->getEnd();
        $startStr = $start->getDateTime() ?? $start->getDate() ?? '';
        $endStr   = $end->getDateTime() ?? $end->getDate() ?? '';

        // Format dates for display
        try {
            $startDt = new \DateTime($startStr);
            $endDt   = new \DateTime($endStr);
            $startFormatted = $startDt->format('d/m/Y H:i');
            $endFormatted   = $endDt->format('H:i');
        } catch (\Throwable $e) {
            $startFormatted = $startStr;
            $endFormatted   = $endStr;
        }

        return [
            '{{SUBJECT}}'          => '',
            '{{MEETING_URL}}'      => $meetingUrl,
            '{{EVENT_SUMMARY}}'    => $event->getSummary() ?? '',
            '{{EVENT_START}}'      => $startFormatted,
            '{{EVENT_END}}'        => $endFormatted,
            '{{EVENT_DESCRIPTION}}' => $event->getDescription() ?? '',
            '{{CUSTOMER_NAME}}'    => trim($customer->get_first_name() . ' ' . $customer->get_last_name()),
            '{{CUSTOMER_EMAIL}}'   => $customer->get_email(),
            '{{SITE_NAME}}'        => get_bloginfo('name'),
        ];
    }

    /**
     * Get admin recipient emails as array.
     */
    protected static function getAdminRecipients(): array
    {
        $raw = Settings::get('admin_email_list', [], Settings::OPT_EMAIL);

        // Handle legacy comma-separated string
        if (is_string($raw)) {
            $raw = explode(',', $raw);
        }

        if (!is_array($raw)) {
            return [];
        }

        return array_values(array_filter(array_map(function ($email) {
            $email = trim($email);
            return is_email($email) ? $email : '';
        }, $raw)));
    }

    /**
     * Format footer links from setting value.
     *
     * Accepts pipe-separated "Text|URL" pairs, one per line.
     * Example:
     *   Privacy Policy|https://example.com/privacy
     *   Terms|https://example.com/terms
     */
    protected static function formatFooterLinks(string $raw): string
    {
        if (empty(trim($raw))) {
            return '';
        }

        $lines = explode("\n", $raw);
        $links = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }
            $parts = explode('|', $line, 2);
            if (count($parts) === 2) {
                $text = trim($parts[0]);
                $url  = trim($parts[1]);
                if (!empty($text) && !empty($url)) {
                    $links[] = '<a href="' . esc_url($url) . '" style="color:#88909b;text-decoration:underline;margin:0 8px;">' . esc_html($text) . '</a>';
                }
            }
        }

        return implode('<span style="color:#b0b7c2;">|</span>', $links);
    }

    /**
     * Send the email via wp_mail.
     */
    protected static function send(string $to, string $subject, string $body): bool
    {
        $senderName  = Settings::get('email_sender_name', '', Settings::OPT_EMAIL);
        $replyTo     = Settings::get('email_reply_to', '', Settings::OPT_EMAIL);

        $headers = ['Content-Type: text/html; charset=UTF-8'];

        if (!empty($senderName)) {
            $fromEmail = self::getFromEmail();
            $headers[] = 'From: ' . self::encodeHeader($senderName) . ' <' . $fromEmail . '>';
        }

        if (!empty($replyTo) && is_email($replyTo)) {
            $headers[] = 'Reply-To: ' . $replyTo;
        }

        return wp_mail($to, $subject, $body, $headers);
    }

    /**
     * Get the From email address using WordPress defaults.
     */
    protected static function getFromEmail(): string
    {
        $sitename = wp_parse_url(network_home_url(), PHP_URL_HOST);
        $fromEmail = 'wordpress@';

        if ($sitename !== null) {
            $fromEmail .= $sitename;
        } else {
            $fromEmail .= 'example.com';
        }

        return apply_filters('wp_mail_from', $fromEmail);
    }

    /**
     * Encode a string for use in an email header (RFC 2047).
     */
    protected static function encodeHeader(string $value): string
    {
        if (preg_match('/[^\x20-\x7E]/', $value)) {
            return '=?UTF-8?B?' . base64_encode($value) . '?=';
        }
        return $value;
    }

    /**
     * Fallback template when file is missing.
     */
    protected static function fallbackTemplate(string $type): string
    {
        $label = ($type === self::TYPE_ADMIN_NOTIFICATION) ? 'Admin Notification' : 'Customer Notification';
        return '<!DOCTYPE html><html><body style="font-family:Arial,sans-serif;padding:20px;">'
            . '<h2>' . esc_html($label) . '</h2>'
            . '<p><strong>{{EVENT_SUMMARY}}</strong></p>'
            . '<p>Inizio: {{EVENT_START}} | Fine: {{EVENT_END}}</p>'
            . '<p>Cliente: {{CUSTOMER_NAME}} ({{CUSTOMER_EMAIL}})</p>'
            . '<p>Link: {{MEETING_URL}}</p>'
            . '<p>{{EVENT_DESCRIPTION}}</p>'
            . '<hr><p style="font-size:11px;color:#999;">{{FOOTER_TEXT}}</p>'
            . '</body></html>';
    }
}
