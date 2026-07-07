<?php

namespace BSO\Survival\Api;

use BSO\Survival\Service\DashboardWidgetLayoutService;

class DashboardWidgetLayoutRestController {
    private const NAMESPACE = 'bso-survival/v1';
    private const ROUTE = '/dashboard-layout/(?P<event_id>\d+)';

    /** @var DashboardWidgetLayoutService */
    private $layoutService;

    public function __construct(DashboardWidgetLayoutService $layoutService) {
        $this->layoutService = $layoutService;
    }

    public function registerRoutes(): void {
        if (!function_exists('register_rest_route')) {
            return;
        }

        register_rest_route(self::NAMESPACE, self::ROUTE, [
            [
                'methods' => 'GET',
                'callback' => [$this, 'getLayout'],
                'permission_callback' => [$this, 'canRead'],
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'updateLayout'],
                'permission_callback' => [$this, 'canManage'],
            ],
        ]);
    }

    public function canRead(): bool {
        return function_exists('current_user_can') && current_user_can('read');
    }

    public function canManage(): bool {
        return function_exists('current_user_can') && current_user_can('manage_options');
    }

    /**
     * @param mixed $request
     * @return mixed
     */
    public function getLayout($request) {
        $eventId = $this->extractEventId($request);
        $layout = $this->layoutService->getLayoutForEvent($eventId);

        return $this->response([
            'event_id' => $eventId,
            'layout' => $layout,
        ]);
    }

    /**
     * @param mixed $request
     * @return mixed
     */
    public function updateLayout($request) {
        $eventId = $this->extractEventId($request);
        $layout = $this->extractLayoutPayload($request);

        $saved = $this->layoutService->saveLayoutForEvent($eventId, $layout);

        return $this->response([
            'event_id' => $eventId,
            'layout' => $saved,
            'updated' => true,
        ]);
    }

    /**
     * @param mixed $request
     */
    private function extractEventId($request): int {
        if (is_object($request) && method_exists($request, 'get_param')) {
            return (int) $request->get_param('event_id');
        }

        if (is_array($request) && isset($request['event_id'])) {
            return (int) $request['event_id'];
        }

        return 0;
    }

    /**
     * @param mixed $request
     * @return array<string, array<int, string>>
     */
    private function extractLayoutPayload($request): array {
        if (is_object($request) && method_exists($request, 'get_param')) {
            $layout = $request->get_param('layout');
            return is_array($layout) ? $layout : [];
        }

        if (is_array($request) && isset($request['layout']) && is_array($request['layout'])) {
            return $request['layout'];
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
