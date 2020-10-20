<?php namespace Pckg\Mail\Service\Mail\Adapter;

use Pckg\Auth\Record\User as UserRecord;

class User extends AbstractAdapter
{

    public function __construct(UserRecord $user)
    {
        $this->fullName = trim($user->name . ' ' . $user->surname);
        $this->email = $user->email ?? null;
        $this->locale = $user->language_id == 'sl' ? 'sl_SI' : ($user->language_id == 'hr' ? 'hr_HR' : 'en_GB');
        $this->language = $this->user->language_id ?? null;
    }

}