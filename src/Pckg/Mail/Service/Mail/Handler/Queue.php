<?php namespace Pckg\Mail\Service\Mail\Handler;

use Exception;
use Pckg\Mail\Service\Mail\HandlerInterface;

class Queue implements HandlerInterface
{

    public function send($template, $receiver, $data = [])
    {
        if (!$template) {
            throw new Exception("Mail template is missing!");
        }

        return queue()->create(
            'mail:send',
            [
                'user'     => $receiver,
                'template' => $template,
                'data'     => $data,
            ]
        );
    }

}