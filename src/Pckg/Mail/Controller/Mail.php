<?php namespace Pckg\Mail\Controller;

use Derive\Inquiry\Entity\Inquiries;
use Derive\Inquiry\Record\Inquiry;
use Derive\Offers\Entity\Offers;
use Derive\Orders\Entity\Orders;
use Derive\Orders\Entity\OrdersUsers;
use Derive\Orders\Entity\Users;
use Derive\User\Service\Mail\User;
use Exception;
use Pckg\Collection;
use Pckg\Database\Query;
use Pckg\Database\Relation\HasMany;
use Pckg\Framework\Helper\Traits;
use Pckg\Mail\Entity\Mails;
use Pckg\Mail\Record\Mail as MailDbRecord;
use Pckg\Mail\Record\Mail as MailRecord;
use Pckg\Mail\Service\Mail\Adapter\SimpleUser;
use Pckg\Queue\Record\Queue;

class Mail
{

    use Traits;

    public function getMailsAction()
    {
        $type = get('type') == 'newsletter' ? 'newsletter' : 'frontend';
        
        return ['mails' => (new Mails())->where('type', $type)->all()];
    }

    public function getPrepareAction()
    {
    }

    public function postCreateAction()
    {
        $data = post('mail');
        unset($data['id']);

        return [
            'mail' => MailDbRecord::create($data),
        ];
    }

    public function postMailAction(MailDbRecord $mail)
    {
        $mail->setAndSave(post('mail'));

        return $this->response()->respondWithSuccess();
    }

    /**
     * We will send email to:
     *  - order(s) (order.user.email, order.user.fullName)
     *  - orderUser(s) (orderUser.user.email, orderUser.user.fullName)
     *  - user(s) (user.email, user.fullName)
     * ...
     * with prepared:
     *  - subject
     *  - content
     *  - sender email
     *  - sender name
     * ...
     * and possible attachment(s):
     *  - estimate(s) (order.estimate_url, orderUser.order.estimate_url)
     *  - bills(s) (order.bill_url, orderUser.order.bill_url)
     *  - voucher(s) (order.voucher_url, orderUser.order.voucher_url)
     * ...
     */
    public function postRequestSendAction(MailRecord $mail)
    {
        $recipients = new Collection(explode(',', $this->post('recipients')));
        $attachments = new Collection($this->post('attachments', []));
        $template = $this->post('mail');
        $receiverType = $this->post('receiverType');
        $type = $this->post('type'); // newsletter, inquiry, ...

        /**
         * If type === offer
         */
        if ($receiverType === 'offer') {
            if ($type === 'upsell') {
                /**
                 * Get all orders for offer that have data.
                 */
                $recipients = $this->getUpsellOfferRecipients($recipients->all());
                $receiverType = 'order';
            } else if ($type === 'remind') {
                /**
                 * Get all orders for offer that has missing data.
                 */
                $recipients = $this->getRemindOfferRecipients($recipients->all());
                $receiverType = 'order';
            } else {
                throw new Exception('Type is required when sending to offer');
            }
        }

        /**
         * Send only 1 mail in test mode.
         */
        $test = $this->post('test');
        if ($test) {
            $recipients = $recipients->slice(0, 1);
        }

        /**
         * Append offers for newsletters.
         */
        $offers = $this->post('offers');
        $offersHtml = null;
        if ($offers) {
            $offersHtml = (new Offers())->where('id', $offers)->all()->map('upsellHtml')->implode();
        }

        $recipients->each(
            function($recipient) use (
                $attachments, $template, $mail, $test, $offersHtml, $receiverType, $type, $recipients
            ) {
                $data = [];
                /**
                 * Handle fetches.
                 */
                $order = null;
                if ($receiverType == 'user') {
                    $user = (new Users())->where('id', $recipient)->one();
                    $receiver = new User($user);
                    $data['fetch']['user'][Users::class] = $user->id;
                } else if ($receiverType == 'orderUser') {
                    $orderUser = (new OrdersUsers())->where('id', $recipient)->one();
                    $receiver = new User($orderUser->user);
                    $data['fetch']['orderUser'][OrdersUsers::class] = $orderUser->id;
                    $data['fetch']['order'][Orders::class] = $orderUser->order_id;
                    $data['fetch']['user'][Users::class] = $orderUser->user_id;
                    $order = $orderUser->order;
                } else if ($receiverType == 'order') {
                    $order = (new Orders())->where('id', $recipient)->one();
                    $receiver = new User($order->user);
                    $data['fetch']['order'][Orders::class] = $order->id;
                    $data['fetch']['user'][Users::class] = $order->user_id;
                } else if ($receiverType == 'inquiry') {
                    $inquiry = (new Inquiries())->where('id', $recipient)->one();
                    $receiver = new SimpleUser($inquiry->email, $inquiry->name, $inquiry->surname);
                    $data['fetch']['inquiry'][Inquiries::class] = $inquiry->id;
                    if (!$test) {
                        $data['trigger'][Inquiry::class . '.responded'] = 'inquiry';
                    }
                } else {
                    throw new Exception("Unknown recipient type");
                }

                /**
                 * Test receivers
                 */
                $receivers = [];
                if ($test) {
                    $testEmails = explode(' ', str_replace([',', '  '], ' ', post('testRecipients')));
                    $auth = $this->auth();
                    foreach ($testEmails as $testEmail) {
                        $receivers[] = new SimpleUser($testEmail, $auth->user('name'), $auth->user('surname'));
                    }
                } else {
                    $receivers[] = $receiver;
                }

                if (!$receiver) {
                    throw new Exception("No receiver");
                }

                /**
                 * Handle attachments.
                 */
                foreach (['estimate', 'bill', 'voucher'] as $document) {
                    if ($attachments->has($document)) {
                        $data['attach'][$document] = __('document.' . $document . '.title', ['order' => $order]);
                    }
                }

                /**
                 * Handle custom newsletter.
                 */
                $finalTemplate = null;
                $mail = post('mail');
                if (isset($mail['content']) && isset($mail['subject'])) {
                    $finalTemplate = [
                        'content' => $mail['content'],
                        'subject' => $mail['subject'],
                    ];
                } else {
                    $finalTemplate = $template['identifier'];
                }

                /**
                 * Handle offers.
                 */
                $data['data']['afterContent'] = $offersHtml;

                /**
                 * Handle type.
                 */
                $data['data']['type'] = $type == 'newsletter' ? 'newsletter' : 'transactional';
                $data['data']['realType'] = $type;

                /**
                 * Put non-campaign mails to queue after document generation.
                 */
                foreach ($receivers as $r) {
                    $queue = email($finalTemplate, $r, $data);
                    if ($queue instanceof Queue) {
                        $queue->makeTimeoutAfterLast('mail:send', '+2seconds');
                    }
                }
            }
        );

        return $this->response()->respondWithSuccess();
    }

    /**
     * Return ids for orders with complete data and no upsell.
     *
     * @param $offers
     *
     * @return \Pckg\Database\Collection
     */
    public function getUpsellOfferRecipients($offers)
    {
        return (new Orders())->selectForDataCheck()
                             ->joinUpsellNotificationLogs(function(HasMany $logs) {
                                 $logs->leftJoin()->groupBy('orders.id');
                             })
                             ->addSelect([
                                             'latestUpsellNotificationDate' => 'MAX(order_logs.created_at)',
                                         ])
                             ->where('offers.id', $offers)
                             ->having('hasCompleteData = 1 AND latestUpsellNotificationDate IS NULL', true)
                             ->all()
                             ->map('id');
    }

    public function getRemindOfferRecipients($offers)
    {
        return (new Orders())->selectForDataCheck()
                             ->where('offers.id', $offers)
                             ->having('hasCompleteData', true, Query::NOT_LIKE)
                             ->all()
                             ->map('id');
    }

    public function getTemplateAction(MailRecord $mail)
    {
        return [
            'mail' => runInLocale(function() use ($mail) {
                return \Pckg\Mail\Record\Mail::gets(['id' => $mail->id]);
            }, $_SESSION['pckg_dynamic_lang_id']),
        ];
    }

    public function getParseLogAction()
    {
        $file = '/var/log/mail.log';
        $content = file_get_contents($file);
        $data = [
            'msgId'       => [],
            'code'        => [],
            'code2msgId'  => [],
            'msgId2code'  => [],
            'date'        => [],
            'code2stat'   => [],
            'stat'        => [],
            'verifyFail'  => [],
            '7bit'        => [],
            'fromRoot'    => [],
            'unknownUser' => [],
        ];
        $to = null;
        foreach (explode("\n", $content) as $line) {
            if (!$line) {
                continue;
            }
            $found = false;
            /**
             * Parse date.
             */
            $date = substr($line, 0, 15);
            $data['date'][$date][] = $line;

            /**
             * Parse code.
             */
            $codeStart = strpos($line, ':', 15) + 2;
            $codeEnd = strpos($line, ':', $codeStart);
            $code = substr($line, $codeStart, $codeEnd - $codeStart);
            $data['code'][$code][] = $line;

            /**
             * Parse msgId.
             */
            if (($msgIdStart = strpos($line, 'msgid=<')) && ($swiftStart = strpos($line, '@'))) {
                $start = $msgIdStart + strlen('msgid=<');
                $length = $swiftStart - $msgIdStart - strlen('msgid=<');
                $msgId = substr($line, $start, $length);
                $data['msgId'][$msgId][] = $line;
                $data['code2msgId'][$code][] = $msgId;
                $data['msgId2code'][$msgId][] = $code;
                $found = true;
            }

            /**
             * Parse to.
             */
            if (($toStart = strpos($line, 'to=<'))) {
                $toStart += strlen('to=<');
                $toEnd = strpos($line, '>,', $toStart);
                $length = $toEnd - $toStart;
                $to = substr($line, $toStart, $length);
            }

            /**
             * Parse Stat
             */
            $stat = null;
            if (($statStart = strpos($line, ', stat='))) {
                $statStart += strlen(', stat=');
                $stat = substr($line, $statStart);
                if (in_array($stat, ['Sent', 'Sent (ok dirdel)', 'Sent (Queued!)', 'Sent (Ok.)'])) {
                    $data['stat']['sent'][] = $to . ' - ' . $line;
                } elseif (strpos($stat, 'Sent') === 0 && strpos($stat, 'Message accepted for delivery')) {
                    $data['stat']['sent'][] = $to . ' - ' . $line;
                } elseif (strpos($stat, 'Sent (Requested mail action okay, completed: id=') === 0) {
                    $data['stat']['sent'][] = $to . ' - ' . $line;
                } elseif (strpos($stat, 'Sent (<') === 0 && strpos($stat, '> Mail accepted)')) {
                    $data['stat']['sent'][] = $to . ' - ' . $line;
                } elseif (strpos($stat, 'Sent (') === 0 && strpos($stat, ' accepted message ')) {
                    $data['stat']['sent'][] = $to . ' - ' . $line;
                } elseif (strpos($stat, 'Sent') === 0 && strpos($stat, ' (OK ') && strpos($stat, ' - gsmtp)')) {
                    $data['stat']['sent'][] = $to . ' - ' . $line;
                } elseif (strpos($stat, 'Sent (Ok: queued on ') === 0 && strpos($stat, ' as ')) {
                    $data['stat']['sent'][] = $to . ' - ' . $line;
                } elseif (strpos($stat, 'Sent (OK id=') === 0) {
                    $data['stat']['sent'][] = $to . ' - ' . $line;
                } elseif (strpos($stat, 'Sent (ok ') === 0) {
                    $data['stat']['sent'][] = $to . ' - ' . $line;
                } elseif (strpos($stat, 'Sent (ok:  Message ') === 0) {
                    $data['stat']['sent'][] = $to . ' - ' . $line;
                } elseif (strpos($stat, 'Sent') === 0 && strpos($stat, 'Queued mail for delivery)')) {
                    $data['stat']['sent'][] = $to . ' - ' . $line;
                } elseif (strpos($stat, 'Sent (2.0.0 Ok: queued as ') === 0) {
                    $data['stat']['sent'][] = $to . ' - ' . $line;
                } elseif (strpos($stat, 'Sent (2.0.0 Ok: queued as ') === 0) {
                    $data['stat']['sent'][] = $to . ' - ' . $line;
                } elseif (strpos($stat, 'Sent (Ok: queued as ') === 0) {
                    $data['stat']['sent'][] = $to . ' - ' . $line;
                } elseif (strpos($stat, 'Sent (Message Queued (') === 0) {
                    $data['stat']['sent'][] = $to . ' - ' . $line;
                } elseif (strpos($stat, 'Deferred: ') === 0) {
                    $data['stat']['unavailable'][] = $to . ' - ' . $line;
                } elseif (strpos($stat, 'Host unknown (Name server: ') === 0) {
                    $data['stat']['unavailable'][] = $to . ' - ' . $line;
                } elseif (strpos($stat, 'Deferred') === 0 && strpos($stat, 'greylist')) {
                    $data['stat']['graylist'][] = $to . ' - ' . $line;
                } elseif (strpos($stat, ': sender notify: Cannot send message for ') === 0) {
                    $data['stat']['graylist'][] = $to . ' - ' . $line;
                } elseif (strpos($stat, 'Service unavailable') === 0) {
                    $data['stat']['unavailable'][] = $to . ' - ' . $line;
                } elseif (in_array($stat, ['User unknown', 'Unknown'])) {
                    $data['stat']['unknownUser'][] = $to . ' - ' . $line;
                } elseif (strpos($stat, 'Please try again later') > 0
                          || strpos($stat, 'Please try again later') === 0
                ) {
                    $data['stat']['later'][] = $to . ' - ' . $line;
                } else {
                    d("Stat", $line, $stat);
                }
                $found = true;
            } elseif (($statStart = strpos($line, 'DSN: '))) {
                $statStart += strlen('DSN: ');
                $stat = substr($line, $statStart);
                if (strpos($stat, 'User unknown') === 0) {
                    $data['stat']['unknownUser'][] = $to . ' - ' . $line;
                } elseif (strpos($stat, 'Service unavailable') === 0) {
                    $data['stat']['unavailable'][] = $to . ' - ' . $line;
                } elseif (strpos($stat, 'Host unknown (Name server: ') === 0) {
                    $data['stat']['unavailable'][] = $to . ' - ' . $line;
                } else {
                    d("Stat2", $line, $stat);
                }
                $found = true;
            } elseif (($statStart = strpos($line, 'Milter: data, reject='))) {
                $statStart += strlen('Milter: data, reject=');
                $stat = substr($line, $statStart);
                if (strpos($stat, 'Please try again later')) {
                    $data['stat']['later'][] = $to . ' - ' . $line;
                } else {
                    d("Stat3", $line, $stat);
                }
                $found = true;
            } elseif (strpos($line, ': sender notify: Warning: could not send message')) {
                $data['stat']['delayed'][] = $to . ' - ' . $line;
                $found = true;
            } elseif (strpos($line, ': sender notify: could not send message for ')) {
                $data['stat']['delayed'][] = $to . ' - ' . $line;
                $found = true;
            } elseif (strpos($stat, ': timeout waiting for input from ') === 0) {
                $data['stat']['unavailable'][] = $to . ' - ' . $line;
                $found = true;
            } elseif (strpos($stat, 'STARTTLS: write error=syscall error') === 0) {
                $data['stat']['unavailable'][] = $to . ' - ' . $line;
                $found = true;
            } elseif (strpos($stat, 'STARTTLS: read error=timeout') === 0) {
                $data['stat']['unavailable'][] = $to . ' - ' . $line;
                $found = true;
            }

            /**
             * Failed server verification (SSL)
             */
            if (strpos($line, ', version=TLSv1.2, verify=FAIL, cipher=')) {
                $data['verifyFail'][$code][] = $line;
                $found = true;
            } elseif (strpos($line, ', version=TLSv1, verify=FAIL, cipher=')) {
                $data['verifyFail'][$code][] = $line;
                $found = true;
            }

            /**
             * Dkim
             */
            if (strpos($line, 'header: DKIM-Signature:')) {
                $data['dkim'][$code][] = $line;
                $found = true;
            }

            /**
             * From=root emails.
             */
            if (strpos($line, ': from=root, ')) {
                $data['fromRoot'][] = $line;
                $found = true;
            }

            /**
             * 7bit
             */
            if (strpos($line, 'bodytype=7BIT, ')) {
                $data['7bit'][] = $line;
                $found = true;
            }

            /**
             * System
             */
            if (strpos($line, 'restarting /usr/sbin/sendmail-mta')) {
                $data['service'][] = $line;
                $found = true;
            } elseif (strpos($line, 'starting daemon')) {
                $data['service'][] = $line;
                $found = true;
            } elseif (strpos($line, 'alias database')) {
                $data['service'][] = $line;
                $found = true;
            } elseif (strpos($line, ': /etc/mail/aliases:')) {
                $data['service'][] = $line;
                $found = true;
            }

            /**
             * System
             */
            if (strpos($line, 'opendkim')) {
                $data['opendkim'][] = $line;
                $found = true;
            }

            if (!$found && $line) {
                d("Unknown", $line);
            }
        }

        return view(
            'mail/parseLog',
            [
                'data' => $data,
            ]
        );
    }

}