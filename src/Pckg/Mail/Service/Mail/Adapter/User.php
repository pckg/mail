<?php namespace Pckg\Mail\Service\Mail\Adapter;

use Pckg\Auth\Record\User as UserRecord;

class User implements Recipient
{

    protected $user;

    public function __construct(UserRecord $user)
    {
        $this->user = $user;
    }

    public function getFullName()
    {
        return $this->user->name . ' ' . $this->user->surname;
    }

    public function getEmail()
    {
        return $this->user->email;
    }

    public function getLocale()
    {
        return $this->user->language_id == 'sl' ? 'sl_SI' : ($this->user->language_id == 'hr' ? 'hr_HR' : 'en_GB');
    }

    public function getLanguage()
    {
        return $this->user->language_id;
    }

}