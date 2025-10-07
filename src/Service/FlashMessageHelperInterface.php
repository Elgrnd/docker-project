<?php

namespace App\Service;

use Symfony\Component\Form\FormInterface;

interface FlashMessageHelperInterface
{
    function addFormErrorsAsFlash(FormInterface $form);

}