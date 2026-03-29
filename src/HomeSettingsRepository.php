<?php

declare(strict_types=1);

namespace ScadenzeFatture;

use RuntimeException;

final class HomeSettingsRepository
{
    private const DEFAULT_SETTINGS = [
        'headline' => 'Scadenze Fatture XML',
        'subheadline' => 'Gestione visiva della home con immagini casuali e layout dinamici.',
        'max_photos' => 3,
        'enabled_layouts' => [1, 2, 3, 4, 5],
        'images' => [],
    ];

    public function __construct(
        private readonly string $configPath,
        private readonly string $uploadDirectory,
    ) {
    }

    /**
     * @return array{headline:string, subheadline:string, max_photos:int, enabled_layouts:list<int>, images:list<array<string,mixed>>}
     */
    public function load(): array
    {
        if (!is_file($this->configPath)) {
            return self::DEFAULT_SETTINGS;
        }

        $decoded = json_decode((string) file_get_contents($this->configPath), true);
        if (!is_array($decoded)) {
            return self::DEFAULT_SETTINGS;
        }

        $settings = array_merge(self::DEFAULT_SETTINGS, $decoded);
        $settings['max_photos'] = $this->normalizeMaxPhotos((int) ($settings['max_photos'] ?? 3));
        $settings['enabled_layouts'] = $this->normalizeLayouts($settings['enabled_layouts'] ?? []);
        $settings['images'] = $this->normalizeImages($settings['images'] ?? []);

        return $settings;
    }

    /**
     * @param array<string,mixed> $input
     * @param array<string,mixed>|null $uploadedFiles
     * @return array{headline:string, subheadline:string, max_photos:int, enabled_layouts:list<int>, images:list<array<string,mixed>>}
     */
    public function saveSettings(array $input, ?array $uploadedFiles): array
    {
        $settings = $this->load();
        $images = [];

        $this->ensureDirectory(dirname($this->configPath));
        $this->ensureDirectory($this->uploadDirectory);

        for ($slot = 0; $slot < 5; $slot++) {
            $current = $settings['images'][$slot] ?? null;
            $remove = ($input['remove_image'][$slot] ?? null) === '1';

            if ($remove && $current !== null) {
                $absoluteCurrentPath = $this->uploadDirectory . '/' . $current['filename'];
                if (is_file($absoluteCurrentPath)) {
                    unlink($absoluteCurrentPath);
                }
                continue;
            }

            $filename = isset($input['keep_image'][$slot]) ? basename((string) $input['keep_image'][$slot]) : null;
            $title = trim((string) ($input['image_title'][$slot] ?? ('Foto home ' . ($slot + 1))));
            $caption = trim((string) ($input['image_caption'][$slot] ?? 'Immagine hero gestita da superadmin.'));

            if ($uploadedFiles !== null && isset($uploadedFiles['error'][$slot]) && $uploadedFiles['error'][$slot] === UPLOAD_ERR_OK) {
                $filename = $this->storeUploadedImage(
                    (string) $uploadedFiles['tmp_name'][$slot],
                    (string) $uploadedFiles['name'][$slot],
                    $slot
                );
            }

            if ($filename === null || $filename === '') {
                continue;
            }

            $images[] = [
                'filename' => $filename,
                'path' => '/uploads/home/' . $filename,
                'title' => $title !== '' ? $title : 'Foto home ' . ($slot + 1),
                'caption' => $caption !== '' ? $caption : 'Immagine hero gestita da superadmin.',
                'updated_at' => date(DATE_ATOM),
            ];
        }

        if ($images === []) {
            $images = [];
        }

        $newSettings = [
            'headline' => trim((string) ($input['headline'] ?? self::DEFAULT_SETTINGS['headline'])) ?: self::DEFAULT_SETTINGS['headline'],
            'subheadline' => trim((string) ($input['subheadline'] ?? self::DEFAULT_SETTINGS['subheadline'])) ?: self::DEFAULT_SETTINGS['subheadline'],
            'max_photos' => $this->normalizeMaxPhotos((int) ($input['max_photos'] ?? self::DEFAULT_SETTINGS['max_photos'])),
            'enabled_layouts' => $this->normalizeLayouts($input['enabled_layouts'] ?? self::DEFAULT_SETTINGS['enabled_layouts']),
            'images' => array_values($images),
        ];

        file_put_contents(
            $this->configPath,
            json_encode($newSettings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
        );

        return $newSettings;
    }

    /**
     * @param array{headline:string, subheadline:string, max_photos:int, enabled_layouts:list<int>, images:list<array<string,mixed>>} $settings
     * @return array{layout:int, images:list<array<string,mixed>>}
     */
    public function selectVariant(array $settings): array
    {
        $images = $settings['images'];
        $layouts = $settings['enabled_layouts'] !== [] ? $settings['enabled_layouts'] : [1];
        $layout = $layouts[array_rand($layouts)];

        if ($images === []) {
            return ['layout' => $layout, 'images' => []];
        }

        shuffle($images);
        $maxPhotos = min($settings['max_photos'], count($images));
        $selectedCount = random_int(1, max(1, $maxPhotos));

        return [
            'layout' => $layout,
            'images' => array_slice($images, 0, $selectedCount),
        ];
    }

    /**
     * @param mixed $layouts
     * @return list<int>
     */
    private function normalizeLayouts(mixed $layouts): array
    {
        if (!is_array($layouts)) {
            return [1];
        }

        $normalized = [];
        foreach ($layouts as $layout) {
            $value = (int) $layout;
            if ($value >= 1 && $value <= 5) {
                $normalized[] = $value;
            }
        }

        $normalized = array_values(array_unique($normalized));

        return $normalized !== [] ? $normalized : [1];
    }

    private function normalizeMaxPhotos(int $value): int
    {
        return max(1, min(5, $value));
    }

    /**
     * @param mixed $images
     * @return list<array<string,mixed>>
     */
    private function normalizeImages(mixed $images): array
    {
        if (!is_array($images)) {
            return [];
        }

        $normalized = [];
        foreach ($images as $image) {
            if (!is_array($image) || !isset($image['filename'])) {
                continue;
            }

            $filename = basename((string) $image['filename']);
            if ($filename === '') {
                continue;
            }

            $normalized[] = [
                'filename' => $filename,
                'path' => '/uploads/home/' . $filename,
                'title' => trim((string) ($image['title'] ?? 'Foto home')) ?: 'Foto home',
                'caption' => trim((string) ($image['caption'] ?? 'Immagine hero gestita da superadmin.')) ?: 'Immagine hero gestita da superadmin.',
                'updated_at' => (string) ($image['updated_at'] ?? date(DATE_ATOM)),
            ];
        }

        return array_slice($normalized, 0, 5);
    }

    private function storeUploadedImage(string $tmpFile, string $originalName, int $slot): string
    {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

        if (!in_array($extension, $allowedExtensions, true)) {
            throw new RuntimeException('Formato immagine non supportato. Usa JPG, PNG, WEBP o GIF.');
        }

        $imageInfo = @getimagesize($tmpFile);
        if ($imageInfo === false) {
            throw new RuntimeException('Il file caricato non è un\'immagine valida.');
        }

        $filename = sprintf('home-slot-%d-%s.%s', $slot + 1, date('YmdHis'), $extension);
        $destination = $this->uploadDirectory . '/' . $filename;

        if (!move_uploaded_file($tmpFile, $destination) && !rename($tmpFile, $destination)) {
            throw new RuntimeException('Impossibile salvare l\'immagine caricata.');
        }

        return $filename;
    }

    private function ensureDirectory(string $directory): void
    {
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Impossibile creare la directory %s', $directory));
        }
    }
}

