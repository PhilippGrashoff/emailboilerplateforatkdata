<?php declare(strict_types=1);

namespace emailboilerplateforatkdata\tests\emailimplementations;

use Atk4\Ui\HtmlTemplate;
use atkuiextendedtemplate\ExtendedHtmlTemplate;
use emailboilerplateforatkdata\EmailTemplate;
use emailboilerplateforatkdata\EmailTemplateHandler;
use emailboilerplateforatkdata\tests\testclasses\Location;

class DefaultEmailTemplateHandler extends EmailTemplateHandler
{

    protected string $htmlTemplateClass = ExtendedHtmlTemplate::class;

    //overwrite in custom implementations to easily define where default template files can be found
    protected function getTemplateFilePath(): string
    {
        return dirname(__DIR__) . '/testtemplatefiles/' . $this->baseEmail->defaultTemplateFile;
    }
}