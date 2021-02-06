<?php

namespace Pckg\Mail\Service\Mail\Adapter;

class SimpleUser extends AbstractAdapter
{

    public function __construct($email, $name = null, $surname = null, $locale = 'en_GB', $language = 'en')
    {
        $this->fullName = trim($name . ' ' . $surname);
        $this->email = $email;
        $this->locale = $locale;
        $this->language = $language;
    }

    public function getLocale()
    {
        return $this->getLanguage() == 'sl' ? 'sl_SI' : ($this->getLanguage() == 'hr' ? 'hr_HR' : 'en_GB');
    }
}
