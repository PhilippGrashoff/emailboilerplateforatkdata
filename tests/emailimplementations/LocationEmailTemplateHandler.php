<?php declare(strict_types=1);

namespace predefinedemailsforatk\tests\emailimplementations;

use Atk4\Ui\HtmlTemplate;
use predefinedemailsforatk\EmailTemplate;
use predefinedemailsforatk\tests\testclasses\Location;

class LocationEmailTemplateHandler extends DefaultEmailTemplateHandler
{
    //we check if the a custom template for the location of an event is in database
    protected function tryLoadTemplateForEntity(): ?HtmlTemplate
    {
        if (!$this->predefinedEmail->getModel()->get('location_id')) {
            return null;
        }
        $emailTemplate = new EmailTemplate($this->predefinedEmail->persistence);
        $emailTemplate->addCondition('model_class', '=', Location::class);
        $emailTemplate->addCondition('model_id', '=', $this->predefinedEmail->getModel()->get('location_id'));
        $emailTemplate->tryLoadBy('ident', (new \ReflectionClass($this->predefinedEmail))->getName());
        if (!$emailTemplate->loaded()) {
            return null;
        }
        return new $this->htmlTemplateClass($emailTemplate->get('value'));
    }
}