<?php

namespace Pckg\Mail\Service\Mail\Handler;

use Pckg\Mail\Service\Mail\HandlerInterface;

class RabbitMQ extends Queue
{
    protected function sendParams(array $params)
    {
        return queue()->job('mail:send', $params);
    }
}
