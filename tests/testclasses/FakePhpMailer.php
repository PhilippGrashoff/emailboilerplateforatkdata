<?php declare(strict_types=1);

namespace predefinedemailsforatk\tests\testclasses;

use predefinedemailsforatk\PHPMailer;

class FakePhpMailer extends PHPMailer
{

    public function send()
    {
        return true;
    }
}