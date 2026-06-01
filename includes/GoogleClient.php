<?php

namespace WGM;

use Google\Client as GClient;
use Google\Service\Calendar;
use Google\Service\PeopleService;

class GoogleClient {

    private static ?GClient $client = null;

    public static function init(){  }

    private static function getClientWithToken()
    {
        $client = self::getClient();
        if ($client === null) return null;
        $token = self::getToken();
        if ($token === null) return null;
        $client->setAccessToken($token);
        if ($client->isAccessTokenExpired()) {
            // Se c'è un refresh_token, prova a rinnovare il token
            if (isset($token['refresh_token'])) {
                $newToken = $client->fetchAccessTokenWithRefreshToken($token['refresh_token']);
                if (isset($newToken['access_token'])) {
                    // Mantieni il refresh_token se non viene restituito
                    if (!isset($newToken['refresh_token'])) {
                        $newToken['refresh_token'] = $token['refresh_token'];
                    }
                    // Aggiorna il token nelle opzioni di WordPress
                    $settings = get_option(\WGM\Settings::OPT_ACCOUNT, []);
                    $settings['google_token'] = $newToken;
                    update_option(\WGM\Settings::OPT_ACCOUNT, $settings);
                    $client->setAccessToken($newToken);
                } else {
                    return null;
                }
            } else {
                return null;
            }
        }
        return $client;
    }

    public static function calendarService() : Calendar|null {
        $client = self::getClientWithToken();
        if ($client === null) return null;
        return new Calendar($client);
    }

    public static function getCalendarsList(): array
    {
        $service = self::calendarService();
        if ($service === null) return [];
        $calendarList = $service->calendarList->listCalendarList();
        $items = $calendarList->getItems();
        $result = [];
        foreach ($items as $calendar) {
            $result[$calendar->getId()] = $calendar->getSummary();
        }
        return $result;
    }

    public static function getCalendarColors() : array
    {
        $service     = self::calendarService();
        if ($service === null) return [];
        $colors      = $service->colors->get();
        $eventColors = $colors->getEvent();
        $result      = [];
        foreach ($eventColors as $colorId => $color) {
            $colorValue = $color->getBackground();
            $result[''. $colorId] = $colorValue;
        }
        return $result;
    }

    public static function getClient(): ?GClient
    {
        if (self::$client) return self::$client;
        $client = new GClient();
        $settings = get_option(\WGM\Settings::OPT_ACCOUNT, []);
        $json = $settings['auth_json'] ?? '';
        if ($json) {
            $config = json_decode($json, true);
            if (isset($config['web'])) {
                $client->setClientId($config['web']['client_id']);
                $client->setClientSecret($config['web']['client_secret']);
                $client->setRedirectUri(admin_url('admin.php?page=wgm-account'));
                $client->setAccessType('offline');
                $client->setPrompt('consent');
                $client->setScopes([
                    Calendar::CALENDAR,
                    Calendar::CALENDAR_EVENTS,
                    PeopleService::USERINFO_EMAIL,
                ]);
            }
        }
        self::$client = $client;
        return $client;
    }

    public static function getAuthUrl(): string|null
    {
        $client = self::getClient();
        return $client?->createAuthUrl();
    }

    public static function getEmail(): string|null
    {
        $client = self::getClient();
        if (!$client) return null;
        $token = self::getToken();
        if (!$token) return null;
        $client->setAccessToken($token);
        if ($client->isAccessTokenExpired()) {
            return null;
        }
        // Usa Google_Service_Oauth2 invece di PeopleService
        $oauth2 = new \Google_Service_Oauth2($client);
        $userinfo = $oauth2->userinfo->get();
        return $userinfo->email ?? null;
    }

    public static function getToken(): array|null
    {
        $settings = get_option(\WGM\Settings::OPT_ACCOUNT, []);
        return $settings['google_token'] ?? null;
    }

    public static function fetchAccessTokenWithAuthCode($code): false|array
    {
        $client = self::getClient();
        $token = $client->fetchAccessTokenWithAuthCode($code);
        if (isset($token['access_token'])) {
            $client->setAccessToken($token);
            return $token;
        }
        return false;
    }

    public static function getEventById(string $eventId): Calendar\Event|null
    {
        $service = self::calendarService();
        if ($service === null) return null;
        $calId = Settings::get('calendar_id', '', Settings::OPT_CALENDAR);
        try {
            return $service->events->get($calId, $eventId);
        } catch (\Exception $e) {
            return null;
        }
    }
}