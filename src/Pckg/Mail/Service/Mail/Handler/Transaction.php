<?php

namespace Pckg\Mail\Service\Mail\Handler;

use Pckg\Mail\Console\SendMail;
use Pckg\Mail\Service\Mail\HandlerInterface;

/**
 * Class Transaction
 * @package Pckg\Mail\Service\Mail\Handler
 */
class Transaction implements HandlerInterface
{

    /**
     * @var array
     */
    protected static $queue = [];

    const EVENT_TRANSACTION_ENDED = self::class . '.transactionEnded';

    /**
     * @param       $template
     * @param       $receiver
     * @param array $data
     */
    public function send($template, $receiver, $data = [])
    {
        static::$queue[] = [
            'handler' => config('pckg.mail.handler', null),
            'template' => $template,
            'receiver' => $receiver,
            'data' => $data,
        ];
    }

    /**
     * Process queued messages.
     */
    static public function commit()
    {
        foreach (static::$queue as $action) {
            \Pckg\Framework\Helper\resolve($action['handler'])->send($action['template'], $action['receiver'], $action['data']);
        }

        static::$queue = [];
    }

    /**
     * Drop queued messages.
     */
    static public function rollback()
    {
        static::$queue = [];
    }
}
