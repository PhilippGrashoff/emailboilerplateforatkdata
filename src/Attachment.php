<?php declare(strict_types=1);

namespace emailboilerplateforatkdata;

use secondarymodelforatk\SecondaryModel;

class Attachment extends SecondaryModel
{

    public $table = 'email_attachment';

    protected function init(): void
    {
        parent::init();
        $this->addField('file_path');
        $this->addField('file_name');
    }
}