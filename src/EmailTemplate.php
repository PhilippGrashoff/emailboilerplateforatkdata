<?php declare(strict_types=1);

namespace emailboilerplateforatkdata;

use secondarymodelforatk\SecondaryModel;

class EmailTemplate extends SecondaryModel
{

    public $table = 'email_template';


    protected function init(): void
    {
        parent::init();

        $this->addField(
            'ident',
            [
                'type' => 'string',
                'system' => true
            ]
        );

        $this->setOrder('ident');
    }
}
