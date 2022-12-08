<?php

namespace Pckg\Mail\Service\Mail\Template;

use Pckg\Framework\Exception\NotFound;
use Pckg\Framework\View\Twig;
use Pckg\Mail\Entity\Mails;

class Database
{
    public function fetchInfo($template, $data = [], $fulldata = [])
    {
        $email = (new Mails())->where('identifier', $template)->joinFallbackTranslation()->oneOrFail(
            function () use ($template) {
                throw new NotFound('Template ' . $template . ' not found');
            }
        );

        return [$email->subject, $email->content, $email];
    }
}
