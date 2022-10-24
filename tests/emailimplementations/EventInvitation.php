<?php declare(strict_types=1);

namespace predefinedemailsforatk\tests\emailimplementations;

use Atk4\Data\Model;
use predefinedemailsforatk\BasePredefinedEmail;
use predefinedemailsforatk\tests\testclasses\Event;

class EventInvitation extends BasePredefinedEmail
{
    public string $defaultTemplateFile = 'event_invitation.html';
    protected string $modelClass = Event::class;
    protected string $emailTemplateHandlerClass = LocationEmailTemplateHandler::class;
    protected Event $event;

    public function getModel(): ?Model
    {
        return $this->event;
    }

    protected function processMessageTemplateOnLoad(): void
    {
        if (!$this->event) {
            return;
        }
        $this->messageTemplate->set('event_name', $this->event->get('name'));
        $this->messageTemplate->set('event_date', $this->event->get('date')->format('Y-m-d'));
        if ($this->event->get('location_id')) {
            $this->messageTemplate->set('location_name', $this->event->ref('location_id')->get('name'));
        }
    }
}
