<?php namespace Pckg\Mail\Resolver;

use Pckg\Framework\Provider\Helper\EntityResolver;
use Pckg\Framework\Provider\RouteResolver;
use Pckg\Mail\Entity\Mails;

class Mail implements RouteResolver
{

    use EntityResolver;

    protected $entity = Mails::class;

}