<?php namespace Pckg\Mail\Service;

use Gnp\Mail\Entity\Mails;
use Pckg\Framework\View\Twig;
use Swift_Attachment;
use Swift_Mailer;
use Swift_MailTransport;
use Swift_Message;
use Swift_Mime_SimpleMessage;

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
        $transport = new Swift_MailTransport();
        $this->mailer = new Swift_Mailer($transport);
        $this->mail = $this->mailer->createMessage();
    }

    public function from($email, $name = null)
    {
        $this->mail->setFrom($email, $name);

        return $this;
    }

    public function to($email, $name = null)
    {
        $this->mail->addTo($email, $name);

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
                              ->oneOrFail();

        $body = (new Twig(null, $data))->setTemplate($email->content)->autoparse();
        $subject = (new Twig(null, $data))->setTemplate($email->subject)->autoparse();

        $this->body($body)->subject($subject)->from($email->sender);

        return $this;
    }

    public function plainBody($body)
    {
        $this->mail->addPart($body, 'text/plain');

        return $this;

    }

    public function attach($path, $mimeType = null, $name = null, $root = 'root')
    {
        $dir = $root ? path($root) : '';
        $this->mail->attach(Swift_Attachment::fromPath($dir . $path, $mimeType)->setFilename($name));

        return $this;
    }

    public function send()
    {
        return $this->mailer->send($this->mail);
    }

}