<?php declare(strict_types=1);

namespace emailboilerplateforatkdata\tests\testclasses;

use emailboilerplateforatkdata\EmailTemplateHandler;

class ExtendedEmailTemplateHandler extends EmailTemplateHandler
{

    //overwrite in custom implementations to easily define where default template files can be found
    protected function getTemplateFilePath(): string
    {
        return dirname(__DIR__) . '/testtemplatefiles/' . $this->baseEmail->defaultTemplateFile;
    }
}