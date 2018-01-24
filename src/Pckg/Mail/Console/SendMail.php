<?php namespace Pckg\Mail\Console;

use Exception;
use Gnp\Mail\Record\MailsSent;
use Pckg\Framework\Console\Command;
use Pckg\Mail\Service\Mail;
use Pckg\Mailo\Swift\Transport\MailoTransport;
use Symfony\Component\Console\Input\InputOption;
use Throwable;

class SendMail extends Command
{

    protected $realData = [];

    protected $eventData = [];

    protected function configure()
    {
        $this->setName('mail:send')
             ->setDescription('Send an email')
             ->addOptions(
                 [
                     'template' => 'Template slug',
                     'user'     => 'User id',
                     'data'     => 'Template data',
                     'content'  => 'Mail content',
                     'subject'  => 'Mail subject',
                     'campaign' => 'Campaign',
                     'queue'    => 'Queue',
                 ], InputOption::VALUE_REQUIRED)
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

    public function emulate(Mail $mailService, $template, $campaign, $queue, $user, $data, $dump, $subject, $content)
    {
        if (!is_array($data)) {
            $data = (array)json_decode($data, true);
        }
        $realData = [];

        if (!empty($data['data'])) {
            $realData = $data['data'];
        }

        $realData = $mailService->readDataFetch($data, $realData);

        $user = $mailService->prepareUser($user);

        /**
         * Skip dummy email.
         */
        if ($mailService->isDummy($user)) {
            $this->output('Skipping ' . $user->getEmail());

            return;
        }

        /**
         * Create recipient.
         */
        $email = null;
        $fullName = null;
        if (is_object($user)) {
            $email = $user->getEmail();
            $fullName = $user->getFullName();
        } elseif (!$dump) {
            throw new Exception("Recipient not set");
        }

        $mailService->checkTemplate($template, $user, $data, $realData);

        /**
         * Subject and content were manually set.
         */
        if ($subject && $content) {
            $mailService->subjectAndContent($subject, $content, $realData);
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
        $mailsSent = class_exists(MailsSent::class) ? MailsSent::create() : null;
        $eventData = array_merge($realData, [
            'attachments' => $data['attach'] ?? [],
            'mailService' => $mailService,
            'template'    => $template,
            'mailsSent'   => $mailsSent,
            'transport'   => $mailService->transport(),
        ]);
        if (isset($data['attach'])) {
            trigger(SendMail::class . '.processAttachments', $eventData);
        }

        /**
         * Check for errors.
         */
        try {
            $mailService->exceptionOnError();
        } catch (Throwable $e) {
            throw $e;
        }

        /**
         * Create log.
         */
        trigger(SendMail::class . '.sendingMail', $eventData);

        /**
         * Set mail type, if it exists in transport.
         */
        $transport = $mailService->transport();
        if (isset($realData['type']) && method_exists($transport, 'setMailType')) {
            $transport->setMailType($realData['type'] == 'newsletter'
                                        ? MailoTransport::TYPE_NEWSLETTER
                                        : MailoTransport::TYPE_TRANSACTIONAL);
        }

        /**
         * Set mail type, if it exists in transport.
         */
        if ($campaign && method_exists($transport, 'setCampaign')) {
            $transport->setCampaign($campaign);
        }

        /**
         * Set mail type, if it exists in transport.
         */
        if ($queue && method_exists($transport, 'setQueue')) {
            $transport->setQueue($queue);
        }

        $this->realData = $realData;
        $this->eventData = $eventData;

        return $eventData;
    }

    public function handle(Mail $mailService)
    {
        $template = $this->option('template');
        $campaign = $this->option('campaign');
        $queue = $this->option('queue');
        $user = $this->option('user');
        $dump = $this->option('dump');
        $data = $this->option('data');
        $subject = $this->option('subject');
        $content = $this->option('content');

        $this->emulate($mailService, $template, $campaign, $queue, $user, $data, $dump, $subject, $content);

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
        trigger(SendMail::class . '.mailSent', $this->eventData);

        $triggers = $data['trigger'] ?? [];
        foreach ($triggers as $event => $load) {
            trigger($event, $this->realData[$load]);
        }

        $this->output('Mail sent!');

        return $this->eventData;
    }

}