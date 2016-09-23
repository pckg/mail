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

    public function sender($email, $name = null)
    {
        $this->mail->setSender($email, $name);

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

        $url = 'https://' . context()->getOrDefault('platformName') . '/';
        $subject = (new Twig(null, $data))->setTemplate($email->subject)->autoparse();
        $content = (new Twig(null, $data))->setTemplate($email->content)->autoparse();

        $body = (new Twig(null, $data))->setTemplate(
            '<html>
	<head>
		<title>' . strip_tags($subject) . '</title>
	</head>
	<body style="margin: 0; background: #f2f2f2; font-family: Arial, sans-serif; font-size: 16px; padding:25px;">
		<div style="width: 600px; margin: 0 auto; clear: both;">
			<a href="#" target="_blank"><img src="' . $url . 'img/logotip-pdf.png" /></a>
			
			<span style="display: block; clear: both; height: auto; font-size: 24px; margin: 0 auto; text-transform: uppercase; font-weight:bold;">' . $subject . '</span>
			<span style="display: block; clear: both; height: 1px; background: #c7c7c7; margin:10px 0 20px;"></span>
			' . $content . '
			
			<span style="display: block; clear: both; height: 1px; background: #c7c7c7; margin-top:10px; margin-bottom:20px;"></span>
			' . __('mail_content_footer') . '
		</div>
	</body>
</html>'
        )->autoparse();

        $this->body($body)->subject($subject)->from($email->sender)->sender($email->sender);

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