<?php declare(strict_types=1);

namespace predefinedemailsforatk\tests\emailimplementations;

use Atk4\Data\Model;
use Atk4\Ui\HtmlTemplate;
use predefinedemailsforatk\BasePredefinedEmail;
use predefinedemailsforatk\EmailRecipient;
use predefinedemailsforatk\SentEmail;
use predefinedemailsforatk\tests\testclasses\Event;
use predefinedemailsforatk\tests\testclasses\Location;

class EventSummaryForLocation extends BasePredefinedEmail
{
    public string $defaultTemplateFile = 'event_summary_for_location.html';
    protected string $modelClass = Location::class;
    protected string $emailTemplateHandlerClass = DefaultEmailTemplateHandler::class;
    protected Location $location;

    protected HtmlTemplate $eventSubTemplate;

    public function getModel(): ?Model
    {
        return $this->location;
    }

    protected function processMessageTemplateOnLoad(): void
    {
        $this->eventSubTemplate = $this->messageTemplate->cloneRegion('Event');
        $this->messageTemplate->del('Event');
        if (!$this->location) {
            return;
        }
        $this->messageTemplate->set('location_name', $this->location->get('name'));
        $this->messageTemplate->set('postfix_per_recipient', '{$postfix_per_recipient}');
        foreach ($this->location->ref(Event::class) as $event) {
            //Method of custom HtmlTemplate class
            $this->eventSubTemplate->setTagsFromModel($this->location, [], 'event_');
            $this->messageTemplate->dangerouslyAppendHtml('Event', $this->eventSubTemplate->renderToHtml());
        }
    }

    //senseless code, just there to test functionality
    protected function processMessageTemplatePerRecipient(
        HtmlTemplate $messageTemplate,
        EmailRecipient $recipient
    ): void {
        if ($recipient->get('firstname') === 'Hans') {
            $messageTemplate->set('postfix_per_recipient', ' for Hans');
        }
    }

    //senseless code, just there to test functionality
    protected function processSubjectTemplatePerRecipient(
        HtmlTemplate $subjectTemplate,
        EmailRecipient $recipient
    ): void {
        if ($recipient->get('firstname') === 'Hans') {
            $subjectTemplate->set('postfix_per_recipient', ' for Hans');
        }
    }

    protected function onSuccessfulSend(): void
    {
        $sentEmail = new SentEmail($this->persistence, ['parentObject' => $this->location]);
        $sentEmail->set('value', __CLASS__);
        $sentEmail->save();
    }
}
