<?php namespace Pckg\Mail\Service\Mail\Adapter;

use Pckg\Collection;

class Admin implements Recipient, MultipleRecipients
{

    public function getFullName()
    {
        return config('site.admin.name');
    }

    public function getEmail()
    {
        return (new Collection(explode(',', config('site.admin.email'))))->trim()->all();
    }

    public function getLocale()
    {
        return config('pckg.locale.default');
    }

    public function getLanguage()
    {
        return config('pckg.locale.language');
    }

}