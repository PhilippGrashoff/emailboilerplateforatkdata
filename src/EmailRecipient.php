<?php declare(strict_types=1);

namespace emailboilerplateforatkdata;

use secondarymodelforatk\SecondaryModel;

//use SecondaryModel model_class and model_id fields to reference exisiting user models etc.
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