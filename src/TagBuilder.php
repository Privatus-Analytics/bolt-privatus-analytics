<?php

declare(strict_types=1);

namespace Privatus\BoltAnalytics;

use Privatus\Analytics\Config;
use Privatus\Analytics\Tag;

/**
 * Turns the extension settings into the tracker tag. It has no Bolt
 * dependencies, so it can be tested on its own.
 */
final class TagBuilder
{
    public const SITE_ID_PATTERN = '/^pa_[A-Za-z0-9]+$/';

    /**
     * @param array<string, mixed> $settings The extension config: site_id, modules.
     *
     * @return string The tag, or an empty string when there is no valid site id.
     */
    public static function build(array $settings): string
    {
        $siteId = self::siteId($settings);
        if ($siteId === null) {
            return '';
        }

        $tag = new Tag(new Config(
            siteId: $siteId,
            modules: self::modules($settings),
            enabled: true,
        ));

        return $tag->render();
    }

    /**
     * The site id from the config, or from the PRIVATUS_SITE_ID environment
     * variable when the config has none. Null when it is missing or not valid.
     *
     * @param array<string, mixed> $settings
     */
    public static function siteId(array $settings): ?string
    {
        $siteId = is_string($settings['site_id'] ?? null) ? trim($settings['site_id']) : '';
        if ($siteId === '') {
            $siteId = trim((string) getenv('PRIVATUS_SITE_ID'));
        }

        return preg_match(self::SITE_ID_PATTERN, $siteId) === 1 ? $siteId : null;
    }

    /**
     * The modules to load, in a fixed order. Unknown names are dropped. A
     * missing setting loads every module. An empty list loads none.
     *
     * @param array<string, mixed> $settings
     *
     * @return list<string>
     */
    public static function modules(array $settings): array
    {
        if (!array_key_exists('modules', $settings) || $settings['modules'] === null) {
            return Config::MODULES;
        }

        $wanted = is_array($settings['modules'])
            ? $settings['modules']
            : explode(',', (string) $settings['modules']);
        $wanted = array_map(static fn ($module): string => trim((string) $module), $wanted);

        return array_values(array_intersect(Config::MODULES, $wanted));
    }
}
