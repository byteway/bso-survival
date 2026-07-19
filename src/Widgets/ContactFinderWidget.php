<?php

namespace BSO\Survival\Widgets;

use BSO\Survival\Contracts\DashboardWidgetInterface;

class ContactFinderWidget implements DashboardWidgetInterface {
    public function getId(): string { return 'contact_finder'; }
    public function getTitle(): string { return 'Contactzoeker'; }
    public function getPriority(): int { return 50; }
    public function getCapabilities(): array { return ['read']; }

    public function getData(array $overview, array $filters = []): array {
        $teams = isset($overview['teams']) && is_array($overview['teams']) ? $overview['teams'] : [];
        $contacts = [];

        foreach ($teams as $team) {
            if (!is_object($team)) {
                continue;
            }

            $teamName = trim((string) ($team->name ?? ''));
            $contactName = trim((string) ($team->contact_name ?? ''));
            $contactPhone = trim((string) ($team->contact_phone ?? ''));
            $contactEmail = trim((string) ($team->contact_email ?? ''));
            $status = trim((string) ($team->status ?? ''));

            $contacts[] = [
                'team_name' => $teamName,
                'contact_name' => $contactName,
                'contact_phone' => $contactPhone,
                'contact_email' => $contactEmail,
                'status' => $status,
                'search_index' => $this->normalizeForSearch(implode(' ', [
                    $teamName,
                    $contactName,
                    $contactPhone,
                    $contactEmail,
                    $status,
                ])),
            ];
        }

        usort($contacts, static function (array $left, array $right): int {
            return strcmp((string) ($left['team_name'] ?? ''), (string) ($right['team_name'] ?? ''));
        });

        return [
            'contacts' => $contacts,
            'total_contacts' => count($contacts),
        ];
    }

    public function render(array $context): string {
        $data = isset($context['data']) && is_array($context['data']) ? $context['data'] : [];
        $contacts = isset($data['contacts']) && is_array($data['contacts']) ? $data['contacts'] : [];
        $widgetId = (string) ($context['widget_id'] ?? $this->getId());
        $searchInputId = 'bso-contact-finder-search-' . preg_replace('/[^a-z0-9_\-]/i', '-', $widgetId);

        $html = '<article class="bso-widget bso-widget-contact" data-bso-contact-widget="1">';
        $html .= '<h3>' . esc_html($this->getTitle()) . '</h3>';
        $html .= '<label class="bso-widget-contact__label" for="' . esc_attr($searchInputId) . '">' . esc_html(__('Zoeken', 'bso-survival')) . '</label>';
        $html .= '<input id="' . esc_attr($searchInputId) . '" class="bso-widget-contact__search" type="search" placeholder="' . esc_attr(__('Zoek op team, contact, e-mail, telefoon of status', 'bso-survival')) . '" data-bso-contact-search="1" />';
        $html .= '<p class="bso-widget-contact__meta" data-bso-contact-count="1">' . esc_html($this->formatResultCount(count($contacts))) . '</p>';

        if ($contacts === []) {
            $html .= '<p class="bso-widget-contact__empty">' . esc_html(__('Geen contactgegevens beschikbaar voor dit event.', 'bso-survival')) . '</p>';
            $html .= '</article>';
            return $html;
        }

        $html .= '<ul class="bso-widget-contact__list" data-bso-contact-list="1">';
        foreach ($contacts as $contact) {
            if (!is_array($contact)) {
                continue;
            }

            $teamName = trim((string) ($contact['team_name'] ?? ''));
            $contactName = trim((string) ($contact['contact_name'] ?? ''));
            $contactPhone = trim((string) ($contact['contact_phone'] ?? ''));
            $contactEmail = trim((string) ($contact['contact_email'] ?? ''));
            $status = trim((string) ($contact['status'] ?? ''));
            $searchIndex = (string) ($contact['search_index'] ?? '');

            $html .= '<li class="bso-widget-contact__item" data-bso-contact-item="1" data-contact-search="' . esc_attr($searchIndex) . '">';
            $html .= '<p class="bso-widget-contact__team">' . esc_html($teamName !== '' ? $teamName : __('Onbekend team', 'bso-survival')) . '</p>';

            if ($contactName !== '') {
                $html .= '<p class="bso-widget-contact__line"><span>' . esc_html(__('Contact', 'bso-survival')) . ':</span> ' . esc_html($contactName) . '</p>';
            }

            if ($contactEmail !== '') {
                $html .= '<p class="bso-widget-contact__line"><span>' . esc_html(__('E-mail', 'bso-survival')) . ':</span> <a href="mailto:' . esc_attr($contactEmail) . '">' . esc_html($contactEmail) . '</a></p>';
            }

            if ($contactPhone !== '') {
                $phoneHref = preg_replace('/\s+/', '', $contactPhone);
                $html .= '<p class="bso-widget-contact__line"><span>' . esc_html(__('Telefoon', 'bso-survival')) . ':</span> <a href="tel:' . esc_attr((string) $phoneHref) . '">' . esc_html($contactPhone) . '</a></p>';
            }

            if ($status !== '') {
                $html .= '<p class="bso-widget-contact__line"><span>' . esc_html(__('Status', 'bso-survival')) . ':</span> ' . esc_html($status) . '</p>';
            }

            $html .= '</li>';
        }
        $html .= '</ul>';
        $html .= '<p class="bso-widget-contact__empty" data-bso-contact-empty="1" hidden="hidden">' . esc_html(__('Geen resultaten voor deze zoekopdracht.', 'bso-survival')) . '</p>';
        $html .= '</article>';

        return $html;
    }

    public function getScriptDependencies(): array { return ['bso-survival-dashboard-widgets']; }
    public function getStyleDependencies(): array { return ['bso-survival-dashboard-widgets']; }

    private function normalizeForSearch(string $value): string {
        $normalized = trim($value);
        if (function_exists('mb_strtolower')) {
            return mb_strtolower($normalized);
        }

        return strtolower($normalized);
    }

    private function formatResultCount(int $count): string {
        if ($count === 1) {
            return '1 resultaat';
        }

        return sprintf('%d resultaten', $count);
    }
}
