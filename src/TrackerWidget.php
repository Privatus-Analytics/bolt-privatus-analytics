<?php

declare(strict_types=1);

namespace Privatus\BoltAnalytics;

use Bolt\Extension\BaseExtension;
use Bolt\Widget\BaseWidget;
use Bolt\Widget\Injector\RequestZone;
use Bolt\Widget\Injector\Target;
use Bolt\Widget\ResponseAwareInterface;
use Throwable;

/**
 * Prints the Privatus Analytics tag at the end of <head> on front-end pages.
 */
class TrackerWidget extends BaseWidget implements ResponseAwareInterface
{
    protected $name = 'Privatus Analytics';

    protected $target = Target::END_OF_HEAD;

    protected $zone = RequestZone::FRONTEND;

    protected $priority = 200;

    /**
     * @param array<string, mixed> $params
     */
    protected function run(array $params = []): ?string
    {
        $extension = $this->getExtension();
        if (!$extension instanceof BaseExtension) {
            return null;
        }

        /** @var array<string, mixed> $settings */
        $settings = $extension->getConfig()->all();

        if (($settings['skip_logged_in'] ?? true) && $this->isLoggedIn($extension)) {
            return null;
        }

        // Bolt can set up extensions twice in one request (an error page runs a
        // second controller), and a theme may already have the tag. Print it once.
        if ($this->pageHasTag()) {
            return null;
        }

        $tag = TagBuilder::build($settings);

        return $tag === '' ? null : $tag;
    }

    private function pageHasTag(): bool
    {
        try {
            return str_contains((string) $this->getResponse()->getContent(), 'privatusstats.com/js/pa.js');
        } catch (Throwable) {
            return false;
        }
    }

    /** True when someone is logged in to Bolt, so their own visits are not counted. */
    private function isLoggedIn(BaseExtension $extension): bool
    {
        try {
            $token = $extension->getService('security.token_storage')->getToken();
        } catch (Throwable) {
            return false;
        }

        return $token !== null && $token->getUser() !== null;
    }
}
