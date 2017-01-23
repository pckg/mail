<?php namespace Pckg\Mail\Controller;

use Derive\Newsletter\Entity\Newsletters;
use Derive\Newsletter\Entity\NewslettersUsers;
use Derive\Newsletter\Record\Newsletter;
use Derive\Newsletter\Record\NewslettersUser;
use Derive\Offers\Record\Offer;
use Derive\Orders\Entity\Users;
use Derive\Orders\Record\User;

class Mailchimp
{

    public function getEnewsAction()
    {
        return view('mailchimp\enews');
    }

    public function postEnewsAction(Offer $offer = null)
    {
        $email = post('email');
        $name = post('name');

        $user = (new Users())->where('email', $email)->oneOr(
            function() use ($email, $name) {
                return User::create(
                    [
                        'email' => $email,
                        'name'  => $name,
                    ]
                );
            }
        );

        $newsletter = (new Newsletters())->where('type', 'default')->one();
        if ($offer) {
            $newsletter = (new Newsletters())->where('offer_id', $offer->id)->oneOr(
                function() use ($offer) {
                    return Newsletter::create(
                        [
                            'offer_id' => $offer->id,
                            'title'    => 'Newsletter for offer ' . $offer->title,
                        ]
                    );
                }
            );
        }

        if ($newsletter) {
            $newslettersUser = (new NewslettersUsers())->where('newsletter_id', $newsletter->id)
                                                       ->where('user_id', $user)
                                                       ->oneOr(
                                                           function() use ($newsletter, $user) {
                                                               return NewslettersUser::create(
                                                                   [
                                                                       'newsletter_id' => $newsletter->id,
                                                                       'user_id'       => $user->id,
                                                                       'created_at'    => date('Y-m-d H:i:s'),
                                                                   ]
                                                               );
                                                           }
                                                       );
        }

        return response()->respondWithSuccess(
            [
                'text' => 'Success',
            ]
        );
    }

}