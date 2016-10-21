<?php namespace Pckg\Mail\Resolver;

use Gnp\Mail\Entity\Mails;
use Pckg\Framework\Provider\RouteResolver;

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