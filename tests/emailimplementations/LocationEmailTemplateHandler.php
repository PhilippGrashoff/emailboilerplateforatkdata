<?php declare(strict_types=1);

namespace predefinedemailsforatk\tests\emailimplementations;

use Atk4\Ui\HtmlTemplate;
use predefinedemailsforatk\EmailTemplate;
use predefinedemailsforatk\tests\testclasses\Location;

class LocationEmailTemplateHandler extends DefaultEmailTemplateHandler
{
    //we check if the a custom template for the location of an event is in database
    protected function customLoadTemplateForEntity(): ?HtmlTemplate
    {
        if (!$this->predefinedEmail->entity->get('location_id')) {
            return null;
        }
        $emailTemplate = new EmailTemplate($this->predefinedEmail->persistence);
        $emailTemplate->addCondition('model_class', '=', Location::class);
        $emailTemplate->addCondition('model_id', '=', $this->predefinedEmail->entity->get('location_id'));
        $emailTemplate->tryLoadBy('ident', (new \ReflectionClass($this->predefinedEmail))->getName());
        if (!$emailTemplate->loaded()) {
            return null;
        }
        $htmlTemplate = new $this->htmlTemplateClass($emailTemplate->get('value'));
        return $htmlTemplate;
    }
}