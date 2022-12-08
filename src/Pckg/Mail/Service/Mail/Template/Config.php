<?php

namespace Pckg\Mail\Service\Mail\Template;

use Pckg\Framework\Exception\NotFound;
use Pckg\Framework\View\Twig;
use Pckg\Mail\Entity\Mails;

class Config
{
    public function fetchInfo($template, $data = [], $fulldata = [])
    {
        $config = config('pckg.mail.templates', null);
        if (!$config) {
            throw new \Exception('No templates defined in pckg.mail.templates');
        }

        $finalConfig = $config[$template] ?? null;
        if (!$finalConfig) {
            throw new \Exception('Email template ' . $template . ' is not defined');
        }

        return [$finalConfig['subject'], $finalConfig['body'], null];
    }
}
