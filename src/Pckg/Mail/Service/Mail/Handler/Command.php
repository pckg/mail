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
        (new SendMail())->executeManually([
                                              'user'     => $receiver,
                                              'template' => $template,
                                              'data'     => $data,
                                          ]);
    }

}