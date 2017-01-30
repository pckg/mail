<?php namespace Pckg\Mail\Console;

use Exception;
use Gnp\Mail\Entity\Mails;
use Gnp\Mail\Record\MailsSent;
use Pckg\Collection;
use Pckg\Database\Repository;
use Pckg\Framework\Console\Command;
use Pckg\Mail\Service\Mail;
use Pckg\Mail\Service\Mail\Adapter\Recipient;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class SendMail extends Command
{

    protected function configure()
    {
        $this->setName('mail:send')
             ->setDescription('Send an email')
             ->addOptions(
                 [
                     'template' => 'Template slug',
                     'user'     => 'User id',
                     'data'     => 'Template data',
                 ],
                 InputOption::VALUE_REQUIRED
             )
             ->addOptions(
                 [
                     'template-required' => '',
                 ]
             )
             ->addOptions(
                 [
                     'dump' => 'Dump instead of send?',
                 ],
                 InputOption::VALUE_OPTIONAL
             );
    }

    public function handle(Mail $mailService)
    {
        $template = $this->option('template');
        $user = $this->option('user');
        $dump = $this->option('dump');
        $data = (array)json_decode($this->option('data'), true);
        $realData = [];
        if (!empty($data['data'])) {
            $realData = $data['data'];
        }

        /**
         * Fetch required data.
         */
        if (isset($data['fetch'])) {
            foreach ($data['fetch'] as $key => $config) {
                foreach ($config as $entity => $id) {
                    $realData[$key] = (new $entity)->where('id', $id)->oneOrFail();
                    break;
                }
            }
        }

        /**
         * Get user.
         *
         * @var Recipient
         *
         * @T00D00 - support for user id
         */
        $user = unserialize(base64_decode($user));

        if (is_object($user) && strpos($user->getEmail(), '@gnp.si')) {
            $this->output('Skipping ' . $user->getEmail());

            return;
        }

        /**
         * Create recipient.
         */
        if (is_object($user)) {
            $email = $user->getEmail();
            $fullName = $user->getFullName();
        } elseif (!$dump) {
            throw new Exception("Recipient not set");
        }

        /**
         * Create mail template, body, subject.
         */
        if ($template) {
            $mailService->template($template, $realData);
        } else {
            $mailService->subjectAndContent(
                $data['subject'] ?? '',
                $data['content'] ?? '',
                $realData
            );
        }

        /**
         * Set email receiver.
         */
        if (!$dump) {
            $mailService->to($email, $fullName);
        }

        /**
         * Add attachments.
         */
        if (isset($data['attach'])) {
            foreach ($data['attach'] as $key => $name) {
                if ($key == 'estimate') {
                    /**
                     * Estimate is generated each time it's attached.
                     */
                    $realData['order']->generateEstimate();

                    /**
                     * Attach estimate.
                     */
                    $mailService->attach(
                        $realData['order']->getAbsoluteEstimateUrlAttribute(),
                        null,
                        $name . '.pdf'
                    );
                }
            }
        }

        /**
         * Send email.
         */
        if ($dump) {
            $path = path('tmp') . 'mails' . path('ds') . date('YmdHis') . '-' . sha1(microtime()) . '.html';
            file_put_contents($path, $mailService->mail()->getBody());
            $this->output('Dumped: ' . $path);

            return;
        } elseif (!$mailService->send()) {
            throw new Exception('Mail not sent!');
        }

        /**
         * Save log.
         */
        $mailTemplate = null;
        if ($template) {
            $mailTemplate = (new Mails())->where('identifier', $template)->one();
        }
        $mail = $mailService->mail();
        MailsSent::create(
            [
                'mail_id'  => $mailTemplate
                    ? $mailTemplate->id
                    : null,
                'subject'  => $mail->getSubject(),
                'content'  => $mail->getBody(),
                'from'     => (new Collection($mail->getFrom()))->map(
                    function($name, $mail) {
                        return $name . ' <' . $mail . '>';
                    }
                )->implode(', ', ' and '),
                'to'       => (new Collection($mail->getTo()))->map(
                    function($name, $mail) {
                        return $name . ' <' . $mail . '>';
                    }
                )->implode(', ', ' and '),
                'datetime' => date('Y-m-d H:i:s'),
            ]
        );

        $this->output('Mail sent!');
    }

}