<?php namespace Pckg\Mail\Controller;

use Derive\Newsletter\Controller\Newsletter;
use Pckg\Generic\Record\Content;
use Pckg\Mail\Form\MailchimpEnews;

class Mailchimp
{

    public function getEnewsAction(MailchimpEnews $mailchimpEnewsForm, Content $content = null)
    {
        return view('Pckg/Mail:mailchimp/enews', [
            'enewsForm' => $mailchimpEnewsForm,
            'content'   => $content,
        ]);
    }

    public function postEnewsAction(MailchimpEnews $mailchimpEnewsForm)
    {
        return (new Newsletter())->postNewsletterAction();
    }

}