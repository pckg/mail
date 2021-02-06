<?php

namespace Pckg\Mail\Record;

use Pckg\Database\Record;
use Pckg\Mail\Entity\Mails;
use Pckg\Mail\Service\Mail\Adapter\Site;

/**
 * Class Mail
 * @package Pckg\Mail\Record
 * @property string $reply_to
 */
class Mail extends Record
{

    protected $entity = Mails::class;

    protected $toArray = ['fromEmail', 'fromName', 'replyToEmail'];

    public function getFromEmailAttribute()
    {
        return (new Site())->getEmail();
    }

    public function getFromNameAttribute()
    {
        return (new Site())->getFullName();
    }

    public function getReplyToEmailAttribute()
    {
        return $this->reply_to ?? (new Site())->getEmail();
    }
}
