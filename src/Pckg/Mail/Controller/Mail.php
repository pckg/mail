<?php namespace Pckg\Mail\Controller;

use Derive\Offers\Entity\Offers;
use Derive\Orders\Entity\Orders;
use Derive\Orders\Entity\OrdersUsers;
use Derive\Orders\Entity\Users;
use Derive\User\Service\Mail\User;
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
                    $orderUser = (new OrdersUsers())->where('id', $recipient['id']);
                    $receiver = new User($orderUser->one()->user);
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

}