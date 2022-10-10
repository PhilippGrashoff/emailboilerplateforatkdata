<?php declare(strict_types=1);

namespace emailboilerplateforatkdata\tests\testclasses;

use emailboilerplateforatkdata\PHPMailer;

class FakePhpMailer extends PHPMailer
{

    public function send()
    {
        return true;
    }
}