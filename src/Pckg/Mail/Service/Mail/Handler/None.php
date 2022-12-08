<?php

namespace Pckg\Mail\Service\Mail\Handler;

use Pckg\Mail\Service\Mail\HandlerInterface;

class None implements HandlerInterface
{
    public function send($template, $receiver, $data = [])
    {
        return true;
    }
}
