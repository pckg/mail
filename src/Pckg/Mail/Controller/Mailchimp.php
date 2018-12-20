<?php namespace Pckg\Mail\Controller;

use Derive\Newsletter\Controller\Newsletter;
use Pckg\Generic\Record\Content;
use Pckg\Generic\Service\Generic\Action;
use Pckg\Mail\Form\MailchimpEnews;

class Mailchimp
{

    public function getEnewsAction(MailchimpEnews $mailchimpEnewsForm, Action $action)
    {
        return view('Pckg/Mail:mailchimp/newsletter', [
            'enewsForm' => $mailchimpEnewsForm,
            'action'   => $action,
        ]);
    }

    public function postEnewsAction(MailchimpEnews $mailchimpEnewsForm)
    {
        return (new Newsletter())->postNewsletterAction();
    }

}