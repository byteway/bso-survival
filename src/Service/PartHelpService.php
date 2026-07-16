<?php

namespace BSO\Survival\Service;

use BSO\Survival\Database\Repository\PartHelpRepositoryInterface;
use InvalidArgumentException;

class PartHelpService {
    /** @var PartHelpRepositoryInterface */
    private $partHelp;

    public function __construct(PartHelpRepositoryInterface $partHelp) {
        $this->partHelp = $partHelp;
    }

    /** @return object */
    public function getByPartId(int $partId) {
        if ($partId <= 0) {
            throw new InvalidArgumentException('part_id moet positief zijn.');
        }

        $help = $this->partHelp->findByPartId($partId);
        if ($help !== null) {
            return $help;
        }

        return (object) [
            'part_id' => $partId,
            'help_text' => $this->defaultTemplate(),
            'image_urls' => '[]',
        ];
    }

    public function saveForPart(int $partId, string $helpText, string $imageListRaw): bool {
        if ($partId <= 0) {
            throw new InvalidArgumentException('part_id moet positief zijn.');
        }

        if (trim($helpText) === '') {
            $helpText = $this->defaultTemplate();
        }

        $this->validateTemplatePlaceholders($helpText);
        $imageReferences = $this->parseImageReferences($imageListRaw);

        $existing = $this->partHelp->findByPartId($partId);
        $now = gmdate('Y-m-d H:i:s');
        $saved = $this->partHelp->upsertByPartId($partId, [
            'help_text' => $helpText,
            'image_urls' => json_encode($imageReferences, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at' => (string) ($existing->created_at ?? $now),
            'updated_at' => $now,
        ]);

        return $saved !== null;
    }

    /**
     * @param object $part
     * @return array{html:string, images:array<int, string>, context:array<string, string>}
     */
    public function renderForPart($part): array {
        $partId = (int) ($part->id ?? 0);
        if ($partId <= 0) {
            throw new InvalidArgumentException('Onderdeel ontbreekt.');
        }

        $help = $this->getByPartId($partId);
        $context = $this->buildContext($part);

        return [
            'html' => $this->replaceTokens((string) ($help->help_text ?? ''), $context),
            'images' => $this->resolveImageUrls((string) ($help->image_urls ?? '[]')),
            'context' => $context,
        ];
    }

    /** @return array<int, string> */
    public function allowedPlaceholders(): array {
        return [
            'part_id',
            'part_name',
            'event_id',
            'latitude',
            'longitude',
            'meta_json',
        ];
    }

    private function defaultTemplate(): string {
        return '<h3>{part_name}</h3><p>Locatie: <strong>{latitude}, {longitude}</strong></p><p>Gebruik de aanwijzingen van de begeleiding en voer deze opdracht veilig uit.</p>';
    }

    private function validateTemplatePlaceholders(string $content): void {
        if (!preg_match_all('/\{([a-zA-Z0-9_]+)\}/', $content, $matches)) {
            return;
        }

        $allowed = $this->allowedPlaceholders();
        $unknown = [];

        foreach ($matches[1] as $token) {
            if (!in_array($token, $allowed, true)) {
                $unknown[] = $token;
            }
        }

        $unknown = array_values(array_unique($unknown));
        if ($unknown !== []) {
            throw new InvalidArgumentException('Onbekende placeholders: ' . implode(', ', $unknown));
        }
    }

    /** @return array<string, string> */
    private function buildContext($part): array {
        $metaJson = is_string($part->meta_data ?? null) ? (string) $part->meta_data : '';

        return [
            'part_id' => (string) (int) ($part->id ?? 0),
            'part_name' => (string) ($part->name ?? ''),
            'event_id' => (string) (int) ($part->event_id ?? 0),
            'latitude' => (string) ($part->latitude ?? ''),
            'longitude' => (string) ($part->longitude ?? ''),
            'meta_json' => $metaJson,
        ];
    }

    /**
     * @param array<string, string> $context
     */
    private function replaceTokens(string $input, array $context): string {
        $output = $input;
        foreach ($context as $key => $value) {
            $output = str_replace('{' . $key . '}', $value, $output);
        }

        return $output;
    }

    /** @return array<int, string> */
    private function parseImageReferences(string $imageListRaw): array {
        $raw = trim($imageListRaw);
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $lines = $decoded;
        } else {
            $lines = preg_split('/[\r\n,]+/', $raw) ?: [];
        }

        $images = [];
        foreach ($lines as $line) {
            if (!is_scalar($line)) {
                continue;
            }

            $value = trim((string) $line);
            if ($value === '') {
                continue;
            }

            $images[] = $value;
        }

        return array_values(array_unique($images));
    }

    /** @return array<int, string> */
    private function resolveImageUrls(string $imageUrlsJson): array {
        $decoded = json_decode($imageUrlsJson, true);
        if (!is_array($decoded)) {
            return [];
        }

        $urls = [];
        foreach ($decoded as $item) {
            if (!is_scalar($item)) {
                continue;
            }

            $value = trim((string) $item);
            if ($value === '') {
                continue;
            }

            if (ctype_digit($value) && function_exists('wp_get_attachment_image_url')) {
                $resolved = wp_get_attachment_image_url((int) $value, 'large');
                if (is_string($resolved) && $resolved !== '') {
                    $urls[] = $resolved;
                    continue;
                }
            }

            $urls[] = $value;
        }

        return array_values(array_unique($urls));
    }
}
