<?php

namespace App\Data\Time;

final class SendClockPointQrMailData
{
    public function __construct(
        public string $email,
    ) {}
}
