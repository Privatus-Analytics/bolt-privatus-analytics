<?php

declare(strict_types=1);

namespace Privatus\BoltAnalytics;

use Bolt\Extension\BaseExtension;

class Extension extends BaseExtension
{
    public function getName(): string
    {
        return 'Privatus Analytics';
    }

    public function initialize(): void
    {
        $this->addWidget(new TrackerWidget());
    }
}
