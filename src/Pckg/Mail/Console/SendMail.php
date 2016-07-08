<?php namespace Pckg\Mail\Console;

use Pckg\Framework\Console\Command;
use Pckg\Mail\Service\Mail;

class SendMail extends Command
{

    protected function configure()
    {
        $this->setName('mail:send')
             ->setDescription('Send an email')
             ->addOptions(
                 [
                     'template' => 'Template slug',
                     'user'     => 'User id',
                     'data'     => 'Template data',
                 ]
             );
    }

    public function handle(Mail $mailService)
    {
    }

}