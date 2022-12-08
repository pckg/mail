<?php

namespace Pckg\Mail\Service\Mail;

interface HandlerInterface
{
    public function send($template, $receiver, $data = []);
}
