<?php

namespace Pckg\Mail\Service\Mail\Adapter;

abstract class AbstractAdapter implements Recipient
{

    protected $fullName;

    protected $email;

    protected $locale;

    protected $language;

    public function getFullName()
    {
        return $this->fullName;
    }

    public function getEmail()
    {
        return $this->email;
    }

    public function getLocale()
    {
        return $this->locale;
    }

    public function getLanguage()
    {
        return $this->language;
    }

    public function getRfc()
    {
        return trim($this->getFullName() . ' <' . $this->getEmail() . '>');
    }
}
