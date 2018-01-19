<?php namespace Pckg\Mail\Service\Mail\Adapter;

class Site extends AbstractAdapter implements Recipient, MultipleRecipients
{

    public function getFullName()
    {
        return config('site.contact.name');
    }

    public function getEmail()
    {
        return config('site.contact.email');
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