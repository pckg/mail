<?php namespace Pckg\Mail\Provider;

use Pckg\Framework\Provider;
use Pckg\Mail\Console\SendMail;

class Config extends Provider
{

    public function consoles() {
        return [
            SendMail::class,
        ];
    }

}