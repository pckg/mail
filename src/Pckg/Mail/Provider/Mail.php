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
            'url' => array_merge_array([
                                           'tags' => [
                                               'group:admin',
                                           ],
                                       ], [
                                           '/mail/requestSend'     => [
                                               'controller' => MailController::class,
                                               'name'       => 'pckg.mail.requestSend',
                                               'view'       => 'requestSend',
                                               'tags'       => [
                                                   'group:admin',
                                               ],
                                           ],
                                           '/mail/[mail]/template' => [
                                               'controller' => MailController::class,
                                               'name'       => 'pckg.mail.template',
                                               'view'       => 'template',
                                               'resolvers'  => [
                                                   'mail' => MailResolver::class,
                                               ],
                                               'tags'       => [
                                                   'group:admin',
                                               ],
                                           ],
                                           '/mail/parse-log'       => [
                                               'controller' => MailController::class,
                                               'name'       => 'pckg.mail.parseLog',
                                               'view'       => 'parseLog',
                                               'tags'       => [
                                                   'group:admin',
                                               ],
                                           ],
                                       ]),
        ];
    }

    public function consoles()
    {
        return [
            SendMail::class,
        ];
    }

    public function paths()
    {
        return $this->getViewPaths();
    }

}