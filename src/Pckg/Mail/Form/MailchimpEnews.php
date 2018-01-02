<?php namespace Pckg\Mail\Form;

use Gnp\Old\Form\GnpDecorator;
use Pckg\Concept\Reflect;
use Pckg\Htmlbuilder\Element\Form;
use Pckg\Htmlbuilder\Element\Form\ResolvesOnRequest;
use Pckg\Htmlbuilder\Element\GnpElementFactory;

class MailchimpEnews extends Form implements ResolvesOnRequest
{

    protected $classes = ['form-horizontal'];

    public function __construct()
    {
        parent::__construct();

        $this->elementFactory = Reflect::create(GnpElementFactory::class);
        foreach ($this->decoratorFactory->create([GnpDecorator::class]) as $decorator) {
            $this->addDecorator($decorator);
        }
    }

    public function initFields()
    {
        $this->addFieldset('fields');
        $this->addEmail('email')
             ->setPlaceholder(__('pckg.mail.email'))
             ->setAttribute('data-validation-engine', 'validate[required,custom[email]]');

        $this->addSubmit('submit')
             ->setValue(__('pckg.mail.mailchimp.send'))
             ->setBig();

        return $this;
    }

}