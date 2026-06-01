<?php

namespace WGM;

use DateTimeInterface;
use Google\Service\Calendar\Event;
use WP_Error;

class Availability
{
    public static function init()
    {
        add_action('rest_api_init', function () {
            register_rest_route('wc-gmeet/v1', '/availability', [
                'methods' => 'GET',
                'callback' => [__CLASS__, 'get'],
                'permission_callback' => '__return_true'
            ]);
        });
    }

    public static function getRestUrl(): string
    {
        return esc_url_raw(rest_url('wc-gmeet/v1/availability'));
    }

    public static function get(\WP_REST_Request $req)
    {
        try {
            $calId = Settings::get('calendar_id', '', Settings::OPT_CALENDAR);
            $tz = Settings::get('timezone', 'Europe/Rome', Settings::OPT_CALENDAR);
            $days = 30;
            $month = (int)($req->get_param('month') ?? date('n'));
            $year = (int)($req->get_param('year') ?? date('Y'));

            $start = new \DateTime("{$year}-{$month}-01 00:00:00", new \DateTimeZone($tz));
            $end = (clone $start)->modify("+{$days} days");
            $today = new \DateTime("now", new \DateTimeZone($tz));
            if ($start < $today) {
                $start = $today;
                //End is the end of the month since today
                $end = (clone $start)->modify('last day of this month')->setTime(23, 59, 59);
            }

            $service = \WGM\GoogleClient::calendarService();
            if ($service === null) {
                return new WP_Error('wgm_no_google_service', 'Google Calendar service not available', ['status' => 500]);
            }
            $events = $service->events->listEvents($calId, [
                'timeMin' => $start->format(DateTimeInterface::RFC3339),
                'timeMax' => $end->format(DateTimeInterface::RFC3339),
                'singleEvents' => true,
                'orderBy' => 'startTime'
            ]);

            $eventsByDate = [];
            $prefix = \WGM\Settings::get('prefix', 'PRENOTAZIONE -', Settings::OPT_CALENDAR);
            foreach ($events->getItems() as $event) {
                $start = $event->getStart()->getDateTime() ?: $event->getStart()->getDate();
                $end = $event->getEnd()->getDateTime() ?: $event->getEnd()->getDate();
                if (!$start || !$end) continue;
                $startDt = new \DateTime($start);
                $endDt = new \DateTime($end);
                $dateKey = $startDt->format('Y-m-d');
                if (!isset($eventsByDate[$dateKey])) {
                    $eventsByDate[$dateKey] = [];
                }
                if (str_starts_with('' . $event->getSummary(), $prefix)) {
                    continue;
                }
                $eventsByDate[$dateKey][] = [
                    'id' => $event->getId(),
                    'start' => $startDt->format(DateTimeInterface::RFC3339),
                    'end' => $endDt->format(DateTimeInterface::RFC3339)
                ];
            }
            return [
                'start' => $start,
                'end' => $end,
                'events' => $eventsByDate
            ];
        } catch (\Throwable $e) {
            return new WP_Error('wgm_availability_error', $e->getMessage(), ['status' => 500]);
        }
    }

    public static function buildDescription(\WC_Order $order, \WC_Customer $customer): string
    {
        $description = "Ordine #" . $order->get_id() . "\n";
        $description .= "Data: " . ($order->get_date_created() ? $order->get_date_created()->date('d/m/Y H:i') : '') . "\n";
        $description .= "Cliente: " . trim($customer->get_first_name() . ' ' . $customer->get_last_name()) . "\n";
        $description .= "Email: " . $customer->get_email() . "\n";
        $description .= "Telefono: " . $customer->get_billing_phone() . "\n";
        if ($order->get_customer_note()) {
            $description .= "Note:\n" . $order->get_customer_note() . "\n";
        }
        return $description;
    }

    public static function reserve(Event $event, \WC_Order $order, \WC_Customer $customer): Event|null
    {
        $service = \WGM\GoogleClient::calendarService();
        if (!$service) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                throw new \Exception("Google Calendar service not available");
            }
            return null;
        }
        $calId = Settings::get('calendar_id', '', Settings::OPT_CALENDAR);
        $calResId = Settings::get('calendar_reservations_id', '', Settings::OPT_CALENDAR);
        $prefix = \WGM\Settings::get('prefix', 'PRENOTAZIONE -', Settings::OPT_CALENDAR);
        $createMeeting = Settings::get('enable_meet', 'no', Settings::OPT_MEET) === 'yes';
        if ($event->getId()) {
            try {
                $service->events->delete($calId, $event->getId(), ['sendUpdates' => 'none']);
                $customerName = trim($customer->get_first_name() . ' ' . $customer->get_last_name());
                $eventColor = Settings::get('event_color', '', Settings::OPT_CALENDAR);
                $eventLanguage = Settings::get('event_language', 'en', Settings::OPT_CALENDAR); // Default to English

                $newEvent = new Event([
                    'summary' => $prefix . $customerName,
                    'description' => self::buildDescription($order, $customer),
                    'start' => $event->getStart(),
                    'end' => $event->getEnd(),
                    'attendees' => [
                        ['email' => $customer->get_email()]
                    ],
                    'colorId' => $eventColor ?: null,
                    'conferenceData' => $createMeeting ? [
                        'createRequest' => [
                            'requestId' => uniqid('wgm-', true),
                            'conferenceSolutionKey' => ['type' => 'hangoutsMeet']
                        ]
                    ] : null,
                    'locale' => $eventLanguage, // Set the event language
                ]);
                return $service->events->insert($calResId, $newEvent, ['conferenceDataVersion' => $createMeeting ? 1 : 0, 'sendUpdates' => 'all']);
            } catch (\Exception $e) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    throw new \Exception("Unable to create new event " . $e->getMessage());
                }
            }
        }
        return null;
    }
}