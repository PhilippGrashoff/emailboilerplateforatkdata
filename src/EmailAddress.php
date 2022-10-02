<?php declare(strict_types=1);

namespace emailboilerplateforatkdata;

use Atk4\Data\Model;

class EmailAddress extends Model
{
    protected function init(): void
    {
        parent::init();
        $this->addField('email_address');
    }
}