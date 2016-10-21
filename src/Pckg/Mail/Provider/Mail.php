<?php namespace Pckg\Mail\Provider;

use Pckg\Framework\Provider;
use Pckg\Mail\Console\SendMail;
use Pckg\Mail\Resolver\Mail as MailResolver;

class Mail extends Provider
{

    public function routes()
    {
        return [
            'url' => [
                '/mail/requestSend'     => [
                    'controller' => \Pckg\Mail\Controller\Mail::class,
                    'name'       => 'pckg.mail.requestSend',
                    'view'       => 'requestSend',
                ],
                '/mail/[mail]/template' => [
                    'controller' => \Pckg\Mail\Controller\Mail::class,
                    'name'       => 'pckg.mail.template',
                    'view'       => 'template',
                    'resolvers'  => [
                        'mail' => MailResolver::class,
                    ],
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