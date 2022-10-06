<?php declare(strict_types=1);

namespace emailboilerplateforatkdata\tests\testclasses;

use Atk4\Ui\HtmlTemplate;
use emailboilerplateforatkdata\EmailTemplate;
use emailboilerplateforatkdata\EmailTemplateHandler;

class ExtendedEmailTemplateHandler extends EmailTemplateHandler
{

    //overwrite in custom implementations to easily define where default template files can be found
    protected function getTemplateFilePath(): string
    {
        return dirname(__DIR__) . '/testtemplatefiles/' . $this->baseEmail->defaultTemplateFile;
    }

    //we check if the a custom template for the location of an event is in database
    protected function customLoadTemplateForEntity(): ?HtmlTemplate
    {
        if (!$this->baseEmail->entity->get('location_id')) {
            return null;
        }
        $emailTemplate = new EmailTemplate($this->baseEmail->persistence);
        $emailTemplate->addCondition('model_class', '=', Location::class);
        $emailTemplate->addCondition('model_id', '=', $this->baseEmail->entity->get('location_id'));
        $emailTemplate->tryLoadBy('ident', (new \ReflectionClass($this->baseEmail))->getName());
        if (!$emailTemplate->loaded()) {
            return null;
        }
        $htmlTemplate = new HtmlTemplate($emailTemplate->get('value'));
        return $htmlTemplate;
    }

}