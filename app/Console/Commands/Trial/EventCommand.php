<?php

namespace App\Console\Commands\Trial;

use App\Events\Trial\Event as TrialEvent;
use App\Support\Console\Commands\Command;

class EventCommand extends Command
{
    protected function handling(): int
    {
        TrialEvent::dispatch();
        return $this->exitSuccess();
    }
}
