<?php namespace Pckg\Mail\Provider;

use Pckg\Framework\Provider;
use Pckg\Mail\Console\SendMail;
use Pckg\Mail\Controller\Mail as MailController;
use Pckg\Mail\Resolver\Mail as MailResolver;

class Mail extends Provider
{

    public function routes()
    {
        return [
            'url' => [
                '/mail/requestSend'     => [
                    'controller' => MailController::class,
                    'name'       => 'pckg.mail.requestSend',
                    'view'       => 'requestSend',
                ],
                '/mail/[mail]/template' => [
                    'controller' => MailController::class,
                    'name'       => 'pckg.mail.template',
                    'view'       => 'template',
                    'resolvers'  => [
                        'mail' => MailResolver::class,
                    ],
                ],
                '/mail/parse-log'       => [
                    'controller' => MailController::class,
                    'name'       => 'pckg.mail.parseLog',
                    'view'       => 'parseLog',
                ],
            ],
        ];
    }

    public function consoles()
    {
        return [
            SendMail::class,
        ];
    }

}