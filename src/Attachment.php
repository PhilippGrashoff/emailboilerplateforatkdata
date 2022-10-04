<?php declare(strict_types=1);

namespace emailboilerplateforatkdata;

use secondarymodelforatk\SecondaryModel;

class Attachment extends SecondaryModel
{

    public $table = 'email_recipient';


    protected function init(): void
    {
        parent::init();
        $this->addFields(
            [
                ['email', 'type' => 'string'],
                ['firstname', 'type' => 'string'],
                ['lastname', 'type' => 'string'],
            ]
        );
    }
}