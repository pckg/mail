<?php namespace Pckg\Mail\Console;

use Derive\Orders\Entity\Users;
use Derive\User\Service\Mail\User;
use Exception;
use Pckg\Framework\Console\Command;
use Pckg\Mail\Service\Mail;
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
                 InputOption::VALUE_NONE
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

        if (is_numeric($user)) {
            /**
             * Receive user from database.
             */
            $user = new User((new Users())->where('id', $user)->oneOrFail());
        } else {
            /**
             * Object was passed.
             */
            $user = unserialize(base64_decode($user));
        }

        /**
         * Skip dummy email.
         */
        if (is_object($user) && is_string($user->getEmail()) && strpos($user->getEmail(), '@gnp.si')) {
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
            $locale = $user->getLocale();
            if (isset($realData['order'])) {
                $locale = $realData['order']->getLocale();
            }
            runInLocale(
                function() use ($template, $mailService, $realData) {
                    $mailService->template($template, $realData);
                },
                $locale
            );
        }

        if (!$template || (isset($data['subject']) && isset($data['content']))) {
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
        $eventData = array_merge($realData, [
            'attachments' => $data['attach'],
            'mailService' => $mailService,
            'template'    => $template,
        ]);
        if (isset($data['attach'])) {
            trigger(SendMail::class . '.processAttachments', $eventData);
        }

        /**
         * Check for errors.
         */
        $checks = [$mailService->mail()->getBody(), $mailService->mail()->getSubject()];
        $excStr = 'an exception has been thrown during the rendering of a template';
        $onLineStr = 'at line';
        foreach ($checks as $check) {
            $lower = strtolower($check);
            if (strpos($lower, $excStr)) {
                throw new Exception('Error parsing template, exception: ' . strbetween($check, $excStr, $onLineStr));
            } else if (strpos($lower, '__string_template__')) {
                throw new Exception('Error parsing template, found __string_template__');
            }
        }

        /**
         * Send email.
         */
        if ($dump) {
            $path = path('tmp') . 'maildump_' . date('YmdHis') . '_' . sha1(microtime()) . '.html';
            file_put_contents($path, $mailService->mail()->getBody());
            $this->output('Dumped: ' . $path);

            return;
        } elseif (!$mailService->send()) {
            throw new Exception('Mail not sent!');
        }

        /**
         * Save log.
         */
        trigger(SendMail::class . '.mailSent', $eventData);

        $this->output('Mail sent!');
    }

}