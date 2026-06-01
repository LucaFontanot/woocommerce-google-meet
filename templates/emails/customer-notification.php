<?php
/**
 * Customer notification email template.
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
 * {{EVENT_DESCRIPTION}}  — Event description / notes
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
                            <!-- Greeting -->
                            <p style="color:#1a1a2e;font-size:15px;line-height:1.6;margin:0 0 20px 0;">
                                Gentile <strong>{{CUSTOMER_NAME}}</strong>,
                            </p>
                            <p style="color:#555;font-size:14px;line-height:1.6;margin:0 0 24px 0;">
                                La tua prenotazione &egrave; stata confermata. Di seguito trovi tutti i dettagli.
                            </p>

                            <!-- Meeting details -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f7f8fa;border-radius:6px;margin-bottom:24px;">
                                <tr>
                                    <td style="padding:20px;">
                                        <h2 style="color:#1a1a2e;font-size:16px;font-weight:700;margin:0 0 16px 0;padding-bottom:8px;border-bottom:2px solid{{PRIMARY_COLOR}};">
                                            &#128197; Dettagli appuntamento
                                        </h2>
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td style="padding:6px 12px 6px 0;color:#888;font-size:13px;font-weight:600;width:80px;vertical-align:top;">Data:</td>
                                                <td style="padding:6px 0;color:#1a1a2e;font-size:14px;">{{EVENT_START}}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding:6px 12px 6px 0;color:#888;font-size:13px;font-weight:600;vertical-align:top;">Fine:</td>
                                                <td style="padding:6px 0;color:#1a1a2e;font-size:14px;">{{EVENT_END}}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding:6px 12px 6px 0;color:#888;font-size:13px;font-weight:600;vertical-align:top;">Tipo:</td>
                                                <td style="padding:6px 0;color:#1a1a2e;font-size:14px;">{{EVENT_SUMMARY}}</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <!-- Google Meet CTA -->
                            {{MEETING_URL_BUTTON}}

                            <!-- Additional notes -->
                            {{EVENT_DESCRIPTION_BLOCK}}
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
