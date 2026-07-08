<?php

namespace BSO\Survival\Api;

use BSO\Survival\Service\TeamRegistrationService;

class TeamRegistrationRestController {
    private const NAMESPACE = 'bso-survival/v1';
    private const ROUTE = '/registrations';

    /** @var TeamRegistrationService */
    private $registrations;

    public function __construct(TeamRegistrationService $registrations) {
        $this->registrations = $registrations;
    }

    public function registerRoutes(): void {
        if (!function_exists('register_rest_route')) {
            return;
        }

        register_rest_route(self::NAMESPACE, self::ROUTE, [[
            'methods' => 'POST',
            'callback' => [$this, 'createRegistration'],
            'permission_callback' => [$this, 'canSubmit'],
        ]]);
    }

    /** @param mixed $request */
    public function canSubmit($request = null): bool {
        if (!function_exists('wp_verify_nonce')) {
            return true;
        }

        $nonce = $this->extractStringParam($request, 'registration_nonce');
        if ($nonce === '' && is_object($request) && method_exists($request, 'get_header')) {
            $nonce = (string) $request->get_header('X-BSO-Registration-Nonce');
        }

        if ($nonce === '') {
            return false;
        }

        $valid = wp_verify_nonce($nonce, 'bso_survival_registration');
        return $valid === 1 || $valid === 2;
    }

    /**
     * @param mixed $request
     * @return mixed
     */
    public function createRegistration($request) {
        $payload = [
            'event_id' => $this->extractIntParam($request, 'event_id'),
            'team_name' => $this->extractStringParam($request, 'team_name'),
            'contact_name' => $this->extractStringParam($request, 'contact_name'),
            'contact_email' => $this->extractStringParam($request, 'contact_email'),
            'contact_phone' => $this->extractStringParam($request, 'contact_phone'),
            'team_members' => $this->extractArrayParam($request, 'team_members'),
            'idempotency_key' => $this->extractStringParam($request, 'idempotency_key'),
        ];

        $result = $this->registrations->register($payload);

        return $this->response([
            'created' => true,
            'result' => $result,
        ]);
    }

    /** @param mixed $request */
    private function extractIntParam($request, string $key): int {
        if (is_object($request) && method_exists($request, 'get_param')) {
            return (int) $request->get_param($key);
        }

        if (is_array($request) && isset($request[$key])) {
            return (int) $request[$key];
        }

        return 0;
    }

    /** @param mixed $request */
    private function extractStringParam($request, string $key): string {
        if (is_object($request) && method_exists($request, 'get_param')) {
            return trim((string) $request->get_param($key));
        }

        if (is_array($request) && isset($request[$key])) {
            return trim((string) $request[$key]);
        }

        return '';
    }

    /**
     * @param mixed $request
     * @return array<int, mixed>
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
