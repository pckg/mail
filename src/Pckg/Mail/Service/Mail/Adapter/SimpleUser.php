<?php namespace Pckg\Mail\Service\Mail\Adapter;

class SimpleUser implements Recipient
{

    protected $name;

    protected $surname;

    protected $email;

    protected $locale;

    protected $language;

    public function __construct($email, $name = null, $surname = null, $locale = 'en_GB', $language = 'en')
    {
        $this->name = $name;
        $this->surname = $surname;
        $this->email = $email;
        $this->locale = $locale;
        $this->language = $language;
    }

    public function getFullName()
    {
        return $this->name . ' ' . $this->surname;
    }

    public function getEmail()
    {
        return $this->email;
    }

    public function getLocale()
    {
        return $this->getLanguage() == 'sl' ? 'sl_SI' : ($this->getLanguage() == 'hr' ? 'hr_HR' : 'en_GB');
    }

    public function getLanguage()
    {
        return $this->language;
    }

}