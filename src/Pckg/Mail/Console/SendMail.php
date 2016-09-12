<?php namespace Pckg\Mail\Console;

use Exception;
use Pckg\Auth\Entity\Users;
use Pckg\Database\Repository;
use Pckg\Framework\Console\Command;
use Pckg\Mail\Service\Mail;
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
                     'platform' => 'Platform',
                 ],
                 InputOption::VALUE_REQUIRED
             );
    }

    public function handle(Mail $mailService)
    {
        $template = $this->option('template');
        $userId = $this->option('user');
        $data = (array)json_decode($this->option('data'));
        $realData = $data['data'] ?? [];

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
         */
        $user = (new Users())->where('id', $userId)->oneOrFail();

        /**
         * Create mail template, body, subject, receiver.
         */
        $mailService->template($template, $realData)
                    ->to($user->email, $user->name . ' ' . $user->surname);

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