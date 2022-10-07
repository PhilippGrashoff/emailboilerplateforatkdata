<?php declare(strict_types=1);

namespace emailboilerplateforatkdata\tests\emailimplementations;

use Atk4\Ui\HtmlTemplate;
use emailboilerplateforatkdata\BasePredefinedEmail;
use emailboilerplateforatkdata\tests\testclasses\Event;
use emailboilerplateforatkdata\tests\testclasses\Location;

class EventSummaryForLocation extends BasePredefinedEmail
{
    public string $defaultTemplateFile = 'event_summary_for_location.html';
    protected string $modelClassName = Location::class;
    protected string $emailTemplateHandlerClassName = DefaultEmailTemplateHandler::class;

    protected HtmlTemplate $eventSubTemplate;

    protected function processMessageTemplateOnLoad(): void
    {
        $this->eventSubTemplate = $this->messageTemplate->cloneRegion('Event');
        $this->messageTemplate->del('Event');
        if (!$this->entity) {
            return;
        }
        $this->messageTemplate->set('location_name', $this->entity->get('name'));
        foreach ($this->entity->ref(Event::class) as $event) {
            //Method of custom HtmlTemplate class
            $this->eventSubTemplate->setTagsFromModel($this->entity, [], 'event_');
            $this->messageTemplate->dangerouslyAppendHtml('Event', $this->eventSubTemplate->renderToHtml());
        }
    }
}
