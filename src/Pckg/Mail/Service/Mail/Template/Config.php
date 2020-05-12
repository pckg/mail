<?php namespace Pckg\Mail\Service\Mail\Template;

use Pckg\Framework\Exception\NotFound;
use Pckg\Framework\View\Twig;
use Pckg\Mail\Entity\Mails;

class Config
{

    public function fetchInfo($template, $data = [], $fulldata = [])
    {
        $config = config('pckg.mail.templates.' . $template, null);
        if (!$config) {
            throw new \Exception('Email template is not defined');
        }

        return [$config['subject'], $config['body'], null];
    }

}