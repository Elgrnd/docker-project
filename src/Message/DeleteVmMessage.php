<?php

namespace App\Message;

class DeleteVmMessage
{
    public function __construct(
        public int $userId
    ) {}
}