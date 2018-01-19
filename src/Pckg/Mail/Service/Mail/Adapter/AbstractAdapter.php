<?php namespace Pckg\Mail\Service\Mail\Adapter;

abstract class AbstractAdapter implements Recipient
{

    public function getRfc()
    {
        return trim($this->getFullName() . ' <' . $this->getEmail() . '>');
    }

}