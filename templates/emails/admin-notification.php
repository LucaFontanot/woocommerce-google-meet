<?php
/**
 * Admin notification email template.
 *
 * Placeholders:
 * {{SUBJECT}}           — Email subject
 * {{HEADER_TEXT}}        — Custom header text from personalization settings
 * {{LOGO}}               — Logo <img> tag (or empty if no logo set)
 * {{PRIMARY_COLOR}}      — Primary brand color (hex)
 * {{ACCENT_COLOR}}       — Accent brand color (hex)
 * {{MEETING_URL}}        — Google Meet link
 * {{EVENT_SUMMARY}}      — Event title/summary
 * {{EVENT_START}}        — Event start date/time
 * {{EVENT_END}}          — Event end date/time
 * {{EVENT_DESCRIPTION}}  — Event description (order details)
 * {{CUSTOMER_NAME}}      — Customer full name
 * {{CUSTOMER_EMAIL}}     — Customer email address
 * {{SITE_NAME}}          — WordPress site name
 * {{FOOTER_TEXT}}        — Custom footer text from personalization settings
 * {{FOOTER_LINKS}}       — Custom footer links HTML
 * {{CURRENT_YEAR}}       — Current year
 */
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{SUBJECT}}</title>
</head>
<body style="margin:0;padding:0;background-color:#f0f2f5;font-family:Arial,Helvetica,sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f0f2f5;padding:20px 0;">
        <tr>
            <td align="center">
                <!-- Main container -->
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="background-color:#ffffff;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.08);max-width:600px;">
                    <!-- Header -->
                    <tr>
                        <td style="background-color:{{PRIMARY_COLOR}};padding:32px 30px;text-align:center;border-radius:8px 8px 0 0;">
                            {{LOGO}}
                            <h1 style="color:#ffffff;font-size:20px;font-weight:700;margin:{{LOGO_MARGIN}};line-height:1.3;">{{HEADER_TEXT}}</h1>
                        </td>
                    </tr>
                    <!-- Body -->
                    <tr>
                        <td style="padding:30px;">
                            <!-- Alert banner -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:{{ACCENT_COLOR}};border-radius:6px;margin-bottom:24px;">
                                <tr>
                                    <td style="padding:14px 18px;">
                                        <p style="color:#ffffff;font-size:14px;font-weight:600;margin:0;text-align:center;">
                                            &#128197; Nuova prenotazione ricevuta
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <!-- Meeting details -->
                            <h2 style="color:#1a1a2e;font-size:16px;font-weight:700;margin:0 0 16px 0;padding-bottom:8px;border-bottom:2px solid{{PRIMARY_COLOR}};">
                                Dettagli riunione
                            </h2>
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px;">
                                <tr>
                                    <td style="padding:8px 12px 8px 0;color:#555;font-size:13px;font-weight:600;width:120px;vertical-align:top;">Riunione:</td>
                                    <td style="padding:8px 0;color:#1a1a2e;font-size:14px;font-weight:600;">{{EVENT_SUMMARY}}</td>
                                </tr>
                                <tr>
                                    <td style="padding:8px 12px 8px 0;color:#555;font-size:13px;font-weight:600;vertical-align:top;">Inizio:</td>
                                    <td style="padding:8px 0;color:#1a1a2e;font-size:14px;">{{EVENT_START}}</td>
                                </tr>
                                <tr>
                                    <td style="padding:8px 12px 8px 0;color:#555;font-size:13px;font-weight:600;vertical-align:top;">Fine:</td>
                                    <td style="padding:8px 0;color:#1a1a2e;font-size:14px;">{{EVENT_END}}</td>
                                </tr>
                                {{MEETING_URL_ROW}}
                            </table>

                            <!-- Customer details -->
                            <h2 style="color:#1a1a2e;font-size:16px;font-weight:700;margin:0 0 16px 0;padding-bottom:8px;border-bottom:2px solid{{PRIMARY_COLOR}};">
                                Dettagli cliente
                            </h2>
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px;">
                                <tr>
                                    <td style="padding:8px 12px 8px 0;color:#555;font-size:13px;font-weight:600;width:120px;vertical-align:top;">Nome:</td>
                                    <td style="padding:8px 0;color:#1a1a2e;font-size:14px;">{{CUSTOMER_NAME}}</td>
                                </tr>
                                <tr>
                                    <td style="padding:8px 12px 8px 0;color:#555;font-size:13px;font-weight:600;vertical-align:top;">Email:</td>
                                    <td style="padding:8px 0;color:#1a1a2e;font-size:14px;">
                                        <a href="mailto:{{CUSTOMER_EMAIL}}" style="color:{{PRIMARY_COLOR}};text-decoration:none;">{{CUSTOMER_EMAIL}}</a>
                                    </td>
                                </tr>
                            </table>

                            <!-- Description -->
                            {{EVENT_DESCRIPTION_BLOCK}}

                            <!-- CTA -->
                            {{MEETING_URL_BUTTON}}
                        </td>
                    </tr>
                    <!-- Footer -->
                    <tr>
                        <td style="background-color:#f7f8fa;padding:20px 30px;text-align:center;border-radius:0 0 8px 8px;border-top:1px solid #e8eaed;">
                            <p style="color:#88909b;font-size:12px;line-height:1.5;margin:0 0 8px 0;">{{FOOTER_TEXT}}</p>
                            <p style="color:#88909b;font-size:11px;line-height:1.5;margin:0 0 4px 0;">{{FOOTER_LINKS}}</p>
                            <p style="color:#b0b7c2;font-size:10px;margin:8px 0 0 0;">&copy; {{CURRENT_YEAR}} {{SITE_NAME}}. Tutti i diritti riservati.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
