<?php

namespace Pckg\Mail\Form;

use Pckg\Concept\Reflect;
use Pckg\Htmlbuilder\Element\Form;
use Pckg\Htmlbuilder\Element\Form\ResolvesOnRequest;

class MailchimpEnews extends Form implements ResolvesOnRequest
{

    protected $classes = ['form-horizontal'];

    public function initFields()
    {
        $this->addFieldset('fields');
        $this->addEmail('email')
             ->setPlaceholder(__('frontend.common.email'))
             ->setAttribute('data-validation-engine', 'validate[required,custom[email]]');

        $this->addSubmit('submit')
             ->setValue(__('frontend.btn.send'))
             ->setBig();

        return $this;
    }
}
