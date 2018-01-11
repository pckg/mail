<?php namespace Pckg\Mail\Service\Mail\Handler;

use Pckg\Mail\Service\Mail\HandlerInterface;

class Queue implements HandlerInterface
{

    public function send($template, $receiver, $data = [])
    {
        $params = [
            'user' => $receiver,
            'data' => $data,
        ];

        if (is_string($template)) {
            $params['template'] = $template;
        } else {
            $params['subject'] = $template['subject'];
            $params['content'] = $template['content'];
        }

        return queue()->create('mail:send', $params);
    }

}