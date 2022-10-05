<?php declare(strict_types=1);

namespace emailboilerplateforatkdata\tests\testclasses;

use emailboilerplateforatkdata\BaseEmail;

class EventInvitation extends BaseEmail
{
    public string $defaultTemplateFile = 'event_invitation.html';
}
