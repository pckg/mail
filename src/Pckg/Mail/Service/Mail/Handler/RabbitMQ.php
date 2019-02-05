<?php namespace Pckg\Mail\Service\Mail\Handler;

use Pckg\Mail\Service\Mail\HandlerInterface;

class RabbitMQ implements HandlerInterface
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

        /**
         * Workers will simply receive command to execute, for example:
         *  - mail:send --template=test --user=schtr4jh@schtr4jh.net --data={...}
         *  - furs:confirm --order=123
         *  - pdf:generate --order=123
         *  - campaign:send --campaign=222 (long task?)
         * When command is received, we mark message as ack.
         * If error is thrown we redeliver message to queue / RabbitMQ.
         * So SendMail command is still used to actually send mail, only trigger is other.
         */
        // $rabbit->message('mail:send --template=test --user=schtr4jh@schtr4jh.net --data={...}')
        return queue()->create('mail:send', $params);
    }

}