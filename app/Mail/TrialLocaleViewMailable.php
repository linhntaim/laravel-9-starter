<?php

namespace App\Mail;

use App\Support\Mail\Mailable;

class TrialLocaleViewMailable extends Mailable
{
    protected bool $viewOnLocale = true;

    protected function sendBefore()
    {
        $this->view('trial', [
            'date' => date_timer()->compound('longDate', ' ', 'longTime'),
        ]);
    }
}