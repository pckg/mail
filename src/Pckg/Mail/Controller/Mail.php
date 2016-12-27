<?php namespace Pckg\Mail\Controller;

use Derive\Offers\Entity\Offers;
use Derive\Orders\Entity\Orders;
use Derive\Orders\Entity\OrdersUsers;
use Derive\Orders\Entity\Users;
use Derive\User\Service\Mail\User;
use Exception;
use Gnp\Mail\Record\Mail as MailRecord;
use Pckg\Collection;
use Pckg\Framework\Helper\Traits;

class Mail
{

    use Traits;

    public function getPrepareAction()
    {
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
        $recipients = new Collection($this->post('recipients'));
        $attachments = new Collection($this->post('attachments'));
        $template = $this->post('mail');

        $recipients->each(
            function($recipient) use ($attachments, $template, $mail) {
                $data = [];
                /**
                 * Handle fetches.
                 */
                $order = null;
                if ($recipient['type'] == 'user') {
                    $user = (new Users())->where('id', $recipient['id'])->one();
                    $receiver = new User($user);
                    $data['fetch']['user'][Users::class] = $user->id;

                } else if ($recipient['type'] == 'orderUser') {
                    $orderUser = (new OrdersUsers())->where('id', $recipient['id'])->one();
                    $receiver = new User($orderUser->user);
                    $data['fetch']['orderUser'][OrdersUsers::class] = $orderUser->id;
                    $data['fetch']['order'][Orders::class] = $orderUser->order_id;
                    $data['fetch']['offer'][Offers::class] = $orderUser->order->offer_id;
                    $data['fetch']['user'][Users::class] = $orderUser->user_id;
                    $order = $orderUser->order;

                } else if ($recipient['type'] == 'order') {
                    $order = (new Orders())->where('id', $recipient['id'])->one();
                    $receiver = new User($order->user);
                    $data['fetch']['order'][Orders::class] = $order->id;
                    $data['fetch']['user'][Users::class] = $order->user_id;
                    $data['fetch']['offer'][Offers::class] = $order->offer_id;

                } else {
                    throw new Exception("Unknown recipient type");

                }

                if (!$receiver) {
                    throw new Exception("No receiver");
                }

                /**
                 * Handle attachments.
                 */
                $queue = null;
                foreach (['estimate', 'bill', 'voucher'] as $document) {
                    if ($attachments->has($document)) {
                        /**
                         * If document isn't generated yet, generate it first.
                         */
                        if (!$order->{$document . '_url'}) {
                            $queue = queue()->create(
                                $document . ':generate',
                                [
                                    'orders' => $order->id,
                                ]
                            )->after($queue);
                        }
                        $data['attach'][$document] = __('document.' . $document . '.title', ['order' => $order]);
                    }
                }

                /**
                 * Set subject and content, they will be parsed later ...
                 */
                $data['subject'] = $template['subject'];
                $data['content'] = $template['content'];

                /**
                 * Put them to queue.
                 */
                queue()->create(
                    'mail:send',
                    [
                        'user' => $receiver,
                        'data' => $data,
                    ]
                )->after($queue);
            }
        );

        return $this->response()->respondWithSuccess();
    }

    public function getTemplateAction(MailRecord $mail)
    {
        return [
            'mail' => $mail,
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
                if (in_array($stat, ['Sent', 'Sent (ok dirdel)', 'Sent (Queued!)'])) {
                    $data['stat']['sent'][] = $to . ' - ' . $line;

                } elseif (strpos($stat, 'Sent') === 0 && strpos($stat, 'Message accepted for delivery')) {
                    $data['stat']['sent'][] = $to . ' - ' . $line;

                } elseif (strpos($stat, 'Sent (<') === 0 && strpos($stat, '> Mail accepted)')) {
                    $data['stat']['sent'][] = $to . ' - ' . $line;

                } elseif (strpos($stat, 'Sent') === 0 && strpos($stat, ' (OK ') && strpos($stat, ' - gsmtp)')) {
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

                } elseif (strpos($stat, 'Sent (Requested mail action okay, completed: id=') === 0) {
                    $data['stat']['sent'][] = $to . ' - ' . $line;

                } elseif (strpos($stat, 'Deferred: Connection timed out with ') === 0) {
                    $data['stat']['unavailable'][] = $to . ' - ' . $line;

                } elseif (strpos($stat, 'Host unknown (Name server: ') === 0) {
                    $data['stat']['unavailable'][] = $to . ' - ' . $line;

                } elseif (strpos($stat, 'Deferred') === 0 && strpos($stat, 'greylist')) {
                    $data['stat']['graylist'][] = $to . ' - ' . $line;

                } elseif (strpos($stat, 'Service unavailable') === 0) {
                    $data['stat']['unavailable'][] = $to . ' - ' . $line;

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