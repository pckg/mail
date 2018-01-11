<?php namespace Pckg\Mail\Service\Mail\Handler;

use Pckg\Mail\Console\SendMail;
use Pckg\Mail\Service\Mail\HandlerInterface;

class Command implements HandlerInterface
{

    /**
     * @param       $template
     * @param       $receiver
     * @param array $data
     */
    public function send($template, $receiver, $data = [])
    {
        $params = [
            '--user' => $receiver,
            '--data' => $data,
        ];

        if (is_string($template)) {
            $params['--template'] = $template;
        } else {
            $params['--subject'] = $template['subject'];
            $params['--content'] = $template['content'];
        }

        (new SendMail())->executeManually($params);
    }

}