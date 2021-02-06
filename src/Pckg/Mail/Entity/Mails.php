<?php

namespace Pckg\Mail\Entity;

use Pckg\Database\Entity;
use Pckg\Mail\Record\Mail;

class Mails extends Entity
{

    protected $record = Mail::class;

    public function boot()
    {
        $this->joinTranslations();
    }
}
