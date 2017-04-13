<?php namespace Pckg\Mail\Controller;

use Derive\Newsletter\Controller\Newsletter;
use Pckg\Mail\Form\MailchimpEnews;

class Mailchimp
{

    public function getEnewsAction(MailchimpEnews $mailchimpEnewsForm)
    {
        return view('Pckg/Mail:mailchimp/enews', [
            'enewsForm' => $mailchimpEnewsForm,
        ]);
    }

    public function postEnewsAction(MailchimpEnews $mailchimpEnewsForm)
    {
        return (new Newsletter())->postNewsletterAction();
    }

}