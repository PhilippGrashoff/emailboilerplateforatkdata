<?php declare(strict_types=1);

namespace predefinedemailsforatk;

use secondarymodelforatk\SecondaryModel;

class Attachment extends SecondaryModel
{

    public $table = 'email_attachment';

    protected function init(): void
    {
        parent::init();
        $this->addField('file_path');
    }
}