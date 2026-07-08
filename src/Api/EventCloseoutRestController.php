<?php

namespace BSO\Survival\Api;

use BSO\Survival\Service\EventCloseoutService;
use BSO\Survival\Service\EventPublicationService;

class EventCloseoutRestController {
    private const NAMESPACE = 'bso-survival/v1';
    private const CLOSEOUT_ROUTE = '/event-closeout/(?P<event_id>\d+)';
    private const PUBLISH_ROUTE = '/event-closeout/(?P<event_id>\d+)/publish';
    private const PUBLICATION_ROUTE = '/event-closeout/(?P<event_id>\d+)/publication';

    /** @var EventCloseoutService */
    private $closeout;

    /** @var EventPublicationService|null */
    private $publications;

    public function __construct(EventCloseoutService $closeout, EventPublicationService $publications = null) {
        $this->closeout = $closeout;
        $this->publications = $publications;
    }

    public function registerRoutes(): void {
        if (!function_exists('register_rest_route')) {
            return;
        }

        register_rest_route(self::NAMESPACE, self::CLOSEOUT_ROUTE, [[
            'methods' => 'POST',
            'callback' => [$this, 'closeoutEvent'],
            'permission_callback' => [$this, 'canManage'],
        ]]);

        register_rest_route(self::NAMESPACE, self::PUBLISH_ROUTE, [[
            'methods' => 'POST',
            'callback' => [$this, 'publishEvent'],
            'permission_callback' => [$this, 'canManage'],
        ]]);

        register_rest_route(self::NAMESPACE, self::PUBLICATION_ROUTE, [[
            'methods' => 'GET',
            'callback' => [$this, 'getPublicationResult'],
            'permission_callback' => [$this, 'canManage'],
        ]]);
    }

    /**
     * @param mixed $request
     */
    public function canManage($request = null): bool {
        if (!function_exists('current_user_can') || !current_user_can('manage_options')) {
            return false;
        }

        if (!function_exists('wp_verify_nonce') || !is_object($request) || !method_exists($request, 'get_header')) {
            return true;
        }

        $nonce = (string) $request->get_header('X-WP-Nonce');
        if ($nonce === '') {
            $nonce = (string) $request->get_header('x-wp-nonce');
        }

        if ($nonce === '') {
            return false;
        }

        $valid = wp_verify_nonce($nonce, 'wp_rest');

        return $valid === 1 || $valid === 2;
    }

    /**
     * @param mixed $request
     * @return mixed
     */
    public function closeoutEvent($request) {
        $eventId = $this->extractEventId($request);
        $changedBy = $this->extractStringParam($request, 'changed_by');
        $certificates = $this->extractArrayParam($request, 'certificates');

        $result = $this->closeout->closeEvent($eventId, $changedBy, $certificates);

        return $this->response([
            'updated' => true,
            'phase' => 'closeout',
            'result' => $result,
        ]);
    }

    /**
     * @param mixed $request
     * @return mixed
     */
    public function publishEvent($request) {
        $eventId = $this->extractEventId($request);
        $changedBy = $this->extractStringParam($request, 'changed_by');
        $publication = $this->extractArrayParam($request, 'publication');

        $result = $this->closeout->publishEvent($eventId, $changedBy, $publication);

        return $this->response([
            'updated' => true,
            'phase' => 'publication',
            'result' => $result,
        ]);
    }

    /**
     * @param mixed $request
     * @return mixed
     */
    public function getPublicationResult($request) {
        $eventId = $this->extractEventId($request);
        $publication = null;

        if ($this->publications !== null && $eventId > 0) {
            $publication = $this->publications->getForEvent($eventId);
        }

        return $this->response([
            'event_id' => $eventId,
            'publication' => $publication,
        ]);
    }

    /** @param mixed $request */
    private function extractEventId($request): int {
        if (is_object($request) && method_exists($request, 'get_param')) {
            return (int) $request->get_param('event_id');
        }

        if (is_array($request) && isset($request['event_id'])) {
            return (int) $request['event_id'];
        }

        return 0;
    }

    /** @param mixed $request */
    private function extractStringParam($request, string $key): string {
        if (is_object($request) && method_exists($request, 'get_param')) {
            return (string) $request->get_param($key);
        }

        if (is_array($request) && isset($request[$key])) {
            return (string) $request[$key];
        }

        return '';
    }

    /**
     * @param mixed $request
     * @return array<int|string, mixed>
     */
    private function extractArrayParam($request, string $key): array {
        if (is_object($request) && method_exists($request, 'get_param')) {
            $value = $request->get_param($key);
            return is_array($value) ? $value : [];
        }

        if (is_array($request) && isset($request[$key]) && is_array($request[$key])) {
            return $request[$key];
        }

        return [];
    }

    /**
     * @param array<string, mixed> $payload
     * @return mixed
     */
    private function response(array $payload) {
        if (function_exists('rest_ensure_response')) {
            return rest_ensure_response($payload);
        }

        return $payload;
    }
}
