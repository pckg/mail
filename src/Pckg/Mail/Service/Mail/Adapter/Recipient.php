<?php

namespace Pckg\Mail\Service\Mail\Adapter;

interface Recipient
{
    public function getFullName();

    public function getEmail();

    public function getLocale();

    public function getLanguage();
}
