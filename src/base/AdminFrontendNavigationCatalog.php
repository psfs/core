<?php

namespace PSFS\base;

final class AdminFrontendNavigationCatalog
{
    /**
     * @param array<string, array{visible?: array<int, array{slug: string, label: string, icon: string}>}> $adminRoutes
     * @param callable(string): ?string $routeLocator
     * @return array<int, array{module: string, items: array<int, array{label: string, icon: string, path: string}>}>
     */
    public function build(array $adminRoutes, callable $routeLocator): array
    {
        $catalog = [];
        foreach ($adminRoutes as $module => $section) {
            $items = [];
            foreach ($section['visible'] ?? [] as $entry) {
                $path = $this->toSpaPath($routeLocator($entry['slug']));
                if ($path === null) {
                    continue;
                }
                $items[] = [
                    'label' => $entry['label'],
                    'icon' => $entry['icon'],
                    'path' => $path,
                ];
            }
            if ($items !== []) {
                $catalog[] = ['module' => $module, 'items' => $items];
            }
        }

        return $catalog;
    }

    private function toSpaPath(?string $legacyPath): ?string
    {
        if ($legacyPath === null || !preg_match('#^/admin(?:/(.*))?$#', $legacyPath, $matches)) {
            return null;
        }

        return '/' . ($matches[1] ?? '');
    }
}
