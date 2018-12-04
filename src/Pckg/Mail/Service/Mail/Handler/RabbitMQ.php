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

        /**
         * Same way we can handle queue.
         * Queue is still saved to database for log, with status.
         * Instead of running tasks with cron we queue them to RabbitMQ.
         * Mailo example:
         *  - proxy + multiple web workers + db backend
         *  - ? cron workers?
         *  - sendmail workers listening to RabbitMQ channels:
         *    - mailo:console:mail:send:newsletter
         *    - mailo:console:mail:send:transactional
         */
    }

}