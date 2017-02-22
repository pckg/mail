<?php namespace Pckg\Mail\Service;

use Derive\User\Service\Mail\Admin;
use Derive\User\Service\Mail\Site;
use Gnp\Mail\Entity\Mails;
use Pckg\Framework\Exception\NotFound;
use Pckg\Framework\View\Twig;
use Swift_Attachment;
use Swift_Mailer;
use Swift_MailTransport;
use Swift_Message;
use Swift_Mime_SimpleMessage;
use Swift_NullTransport;
use Swift_SendmailTransport;

class Mail
{

    /**
     * @var Swift_Mime_SimpleMessage
     */
    protected $mailer;

    /**
     * @var Swift_Message
     */
    protected $mail;

    public function __construct()
    {
        $this->mailer = new Swift_Mailer($this->getTransport());
        $this->mail = $this->mailer->createMessage();
    }

    public function getTransport()
    {
        $transportClass = config('pckg.mail.swift.transport', Swift_SendmailTransport::class);

        if ($transportClass == Swift_MailTransport::class) {
            return new Swift_MailTransport();

        } else if ($transportClass == Swift_NullTransport::class) {
            return new Swift_NullTransport();

        }

        return new Swift_SendmailTransport('/usr/sbin/sendmail -bs');
    }

    public function from($email, $name = null)
    {
        $this->mail->setFrom($email, $name);

        return $this;
    }

    public function sender($email, $name = null)
    {
        $this->mail->setSender($email, $name);

        return $this;
    }

    public function fromSite()
    {
        $site = new Site();

        $this->from($site->getEmail(), $site->getFullName());
        $this->sender($site->getEmail(), $site->getFullName());
    }

    public function toAdmin()
    {
        $admin = new Admin();
        $site = new Site();
        $emails = $admin->getEmail();

        if (!is_array($emails)) {
            $emails = [$emails];
        }

        foreach ($emails as $email) {
            $this->to($email, $site->getFullName() . ' Administrator');
        }
    }

    public function to($emails, $name = null)
    {
        if (!is_array($emails)) {
            $emails = [$emails => $name ?? $emails];
        }

        foreach ($emails as $key => $value) {
            $this->mail->addTo(
                is_int($key) ? $value : $key,
                is_int($key) && $name ? $name : $value
            );
        }

        return $this;
    }

    public function subject($subject)
    {
        $this->mail->setSubject($subject);

        return $this;

    }

    public function body($body)
    {
        $this->mail->setBody($body, 'text/html');

        return $this;

    }

    public function template($template, $data = [])
    {
        $email = (new Mails())->where('identifier', $template)
                              ->joinFallbackTranslation()
                              ->oneOrFail(
                                  function() use ($template) {
                                      throw new NotFound('Template ' . $template . ' not found');
                                  }
                              );

        $subject = (new Twig(null, $data))->setTemplate($email->subject)->autoparse();
        $content = (new Twig(null, $data))->setTemplate($email->content)->autoparse();

        $body = view(
            'Pckg\Mail:layout',
            array_merge(
                $data,
                [
                    'subject' => $subject,
                    'content' => $content,
                ]
            )
        )->autoparse();

        $this->body($body)
             ->subject($subject);

        $this->fromSite();

        return $this;
    }

    public function subjectAndContent($subject, $content, $data = [])
    {
        $subject = (new Twig(null, $data))->setTemplate($subject)->autoparse();
        $content = (new Twig(null, $data))->setTemplate($content)->autoparse();

        $body = view(
            'Pckg\Mail:layout',
            array_merge(
                $data,
                [
                    'subject' => $subject,
                    'content' => $content,
                ]
            )
        )->autoparse();

        $this->body($body)
             ->subject($subject);

        $this->fromSite();

        return $this;
    }

    public function plainBody($body)
    {
        $this->mail->addPart($body, 'text/plain');

        return $this;

    }

    public function attach($path, $mimeType = null, $name = null)
    {
        $this->mail->attach(Swift_Attachment::fromPath($path, $mimeType)->setFilename($name));

        return $this;
    }

    public function send()
    {
        return $this->mailer->send($this->mail);
    }

    public function mail()
    {
        return $this->mail;
    }

}
