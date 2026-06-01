<?php

namespace WGM;

use Google\Service\Calendar\Event;
use WC_Customer;
use WC_Order;

class Email
{
    protected Event $event;
    protected string $meetingUrl;
    protected WC_Order $order;
    protected WC_Customer $customer;
    protected string $subject;
    protected string $body;

    function __construct(Event $event, WC_Order $order, WC_Customer $customer)
    {
        $this->event = $event;
        $this->order = $order;
        $this->customer = $customer;
    }

    public function setMeetingUrl(string $url): void
    {
        $this->meetingUrl = $url;
    }

    protected function formatString(string $string): string
    {
        $replacements = [
            '[EVENT_SUMMARY]' => $this->event->getSummary() ?? '',
            '[EVENT_START]' => $this->event->getStart()->getDateTime() ?? $this->event->getStart()->getDate() ?? '',
            '[EVENT_END]' => $this->event->getEnd()->getDateTime() ?? $this->event->getEnd()->getDate() ?? '',
            '[MEETING_URL]' => $this->meetingUrl ?? '',
        ];
        return strtr($string, $replacements);
    }

    public function setSubject(string $subject): void
    {
        $this->subject = $this->formatString($subject);
    }

    protected function isHtml(string $string): bool
    {
        return strip_tags($string) !== $string;
    }

    public function setBody(string $body): void
    {
        if (!$this->isHtml($body)) {
            $this->body = nl2br($this->formatString($body));
        } else {
            $this->body = $this->formatString($body);
        }
    }

    public function send(string $to) :void {
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        wp_mail($to, $this->subject, $this->body, $headers);
    }


}