<?php

namespace App\Service;

use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class FlashMessageHelper implements FlashMessageHelperInterface
{
    private RequestStack $requestStack;

    public function __construct(RequestStack $requestStack){
        $this->requestStack = $requestStack;
    }

    public function addFormErrorsAsFlash(FormInterface $form) : void
    {
        $errors = $form->getErrors(true);
        foreach($errors as $error) {

            $flashBag = $this->requestStack->getSession()->getFlashBag();
            $flashBag->add('error',  $error->getMessage());
        }
    }
}