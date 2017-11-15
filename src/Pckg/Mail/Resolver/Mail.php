<?php namespace Pckg\Mail\Resolver;

use Pckg\Framework\Provider\RouteResolver;
use Pckg\Mail\Entity\Mails;

class Mail implements RouteResolver
{

    public function resolve($value)
    {
        return (new Mails())->where('id', $value)->oneOrFail();
    }

    public function parametrize($record)
    {
        return $record->id;
    }

}