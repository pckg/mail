<?php namespace Pckg\Mail\Console;

use Exception;
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
             );
    }

    public function handle(Mail $mailService)
    {
        $template = $this->option('template');
        $user = $this->option('user');
        $data = (array)json_decode($this->option('data'), true);
        $realData = $data['data'] ?: [];

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
         */
        $user = unserialize(base64_decode($user));

        /**
         * Create mail template, body, subject, receiver.
         */
        $mailService->template($template, $realData)
                    ->to($user->getEmail(), $user->getFullName());

        /**
         * Add attachments.
         */
        if (isset($data['attach'])) {
            foreach ($data['attach'] as $key => $name) {
                if ($key == 'estimate') {
                    $mailService->attach(
                        $realData['order']->getData('estimate_url'),
                        null,
                        $name . '.pdf',
                        ''
                    );
                }
            }
        }

        /**
         * Send email.
         */
        if (!$mailService->send()) {
            throw new Exception('Mail not sent!');
        }

        $this->output('Mail sent!');
    }

}