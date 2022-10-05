<?php declare(strict_types=1);

namespace emailboilerplateforatkdata;

use secondarymodelforatk\SecondaryModel;

class EmailRecipient extends SecondaryModel
{

    public $table = 'email_recipient';

    protected function init(): void
    {
        parent::init();
        $this->addField('email_address');
        $this->addField('firstname');
        $this->addField('lastname');
    }
}