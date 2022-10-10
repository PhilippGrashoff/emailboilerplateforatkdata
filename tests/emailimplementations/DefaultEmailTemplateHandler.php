<?php declare(strict_types=1);

namespace emailboilerplateforatkdata\tests\emailimplementations;

use atkuiextendedtemplate\ExtendedHtmlTemplate;
use emailboilerplateforatkdata\BaseEmailTemplateHandler;

class DefaultEmailTemplateHandler extends BaseEmailTemplateHandler
{

    protected string $htmlTemplateClass = ExtendedHtmlTemplate::class;

    //overwrite in custom implementations to easily define where default template files can be found
    protected function getTemplateFilePath(): string
    {
        return dirname(__DIR__) . '/testtemplatefiles/' . $this->predefinedEmail->defaultTemplateFile;
    }

    public function getHeaderTemplateString(): string
    {
        return '<html><head></head><body><div id="header"></div><div id="content">';
    }

    public function getFooterTemplateString(): string
    {
        return '<div id="footer"></div></div></body></html>';
    }
}