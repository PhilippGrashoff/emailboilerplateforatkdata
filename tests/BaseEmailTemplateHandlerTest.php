<?php declare(strict_types=1);

namespace predefinedemailsforatk\tests;

use Atk4\Data\Persistence;
use atkuiextendedtemplate\ExtendedHtmlTemplate;
use predefinedemailsforatk\EmailAccount;
use predefinedemailsforatk\EmailTemplate;
use predefinedemailsforatk\tests\emailimplementations\DefaultEmailTemplateHandler;
use predefinedemailsforatk\tests\emailimplementations\EventInvitation;
use predefinedemailsforatk\tests\testclasses\Event;
use predefinedemailsforatk\tests\testclasses\Location;
use predefinedemailsforatk\tests\testclasses\TestDataResult;
use traitsforatkdata\TestCase;


class BaseEmailTemplateHandlerTest extends TestCase
{
    protected Persistence $persistence;

    protected $sqlitePersistenceModels = [
        Event::class,
        Location::class,
        EmailTemplate::class,
        EmailAccount::class,
        EventInvitation::class,
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->persistence = $this->getSqliteTestPersistence();
    }

    public function testUseDefaultTemplateFromFile(): void
    {
        $testData = $this->setupTestEventsAndLocations();
        $eventInvitation = new EventInvitation($this->persistence, ['event' => $testData->event1]);
        $eventInvitation->loadInitialValues();

        self::assertSame(
            '<div>It is so nice to join us for the event Birthday Jenny at Assembly Hall on the 2023-10-04.</div>',
            $eventInvitation->get('message_template')
        );
        self::assertSame(
            'Hello dear {$recipient_firstname}',
            $eventInvitation->get('subject_template')
        );
    }

    public function testUseTemplateFromPersistence(): void
    {
        $testData = $this->setupTestEventsAndLocations();
        $emailTemplate = new EmailTemplate($this->persistence);
        $emailTemplate->set('ident', EventInvitation::class);
        $emailTemplate->set(
            'value',
            '{Subject}Hello my friend {$recipient_firstname}{/Subject}'
            . '<div>Please join us for {$event_name} at {$location_name} on the {$event_date}.</div>'
        );
        $emailTemplate->save();
        $eventInvitation = new EventInvitation($this->persistence, ['event' => $testData->event1]);
        $eventInvitation->loadInitialValues();

        self::assertSame(
            '<div>Please join us for Birthday Jenny at Assembly Hall on the 2023-10-04.</div>',
            $eventInvitation->get('message_template')
        );
        self::assertSame(
            'Hello my friend {$recipient_firstname}',
            $eventInvitation->get('subject_template')
        );
    }

    public function testUseCustomTemplateLoadingPerEntity(): void
    {
        $testData = $this->setupTestEventsAndLocations();
        //Add custom Template for location1
        $emailTemplate = new EmailTemplate($this->persistence);
        $emailTemplate->set('ident', EventInvitation::class);
        $emailTemplate->set('model_class', Location::class);
        $emailTemplate->set('model_id', $testData->location1->getId());
        $emailTemplate->set(
            'value',
            '{Subject}Welcome to {$event_name}, dear {$recipient_firstname}{/Subject}'
            . '<div>It will be a pleasure to meet you for {$event_name} at {$location_name} on the {$event_date}.<br />Please be aware that smoking is forbidden in this location.</div>'
        );

        $emailTemplate->save();
        $eventInvitation = new EventInvitation($this->persistence, ['event' => $testData->event1]);
        $eventInvitation->loadInitialValues();

        self::assertSame(
            '<div>It will be a pleasure to meet you for Birthday Jenny at Assembly Hall on the 2023-10-04.<br />Please be aware that smoking is forbidden in this location.</div>',
            $eventInvitation->get('message_template')
        );
        self::assertSame(
            'Welcome to Birthday Jenny, dear {$recipient_firstname}',
            $eventInvitation->get('subject_template')
        );

        //location2 has no custom template, so default should be used
        $testData->event1->set('location_id', $testData->location2->getId());
        $testData->event1->save();
        $eventInvitation = new EventInvitation($this->persistence, ['event' => $testData->event1]);
        $eventInvitation->loadInitialValues();

        self::assertSame(
            '<div>It is so nice to join us for the event Birthday Jenny at Harrys House on the 2023-10-04.</div>',
            $eventInvitation->get('message_template')
        );
        self::assertSame(
            'Hello dear {$recipient_firstname}',
            $eventInvitation->get('subject_template')
        );
    }

    public function testCustomHtmlTemplateClassIsUsed(): void
    {
        $testData = $this->setupTestEventsAndLocations();
        $eventInvitation = new EventInvitation($this->persistence, ['event' => $testData->event1]);
        $handler = new DefaultEmailTemplateHandler($eventInvitation);
        $template = $handler->loadEmailTemplateForPredefinedEmail();
        self::assertInstanceOf(ExtendedHtmlTemplate::class, $template);
    }

    public function testCreateEmailTemplateRecords(): void
    {
        self::assertSame(
            0,
            (int)(new EmailTemplate($this->persistence))->action('count')->getOne()
        );
        $handler = new DefaultEmailTemplateHandler();
        $handler->createEmailTemplateEntities(
            __DIR__ . '/emailimplementations',
            $this->persistence
        );
        self::assertSame(
            2,
            (int)(new EmailTemplate($this->persistence))->action('count')->getOne()
        );
    }


    protected function setupTestEventsAndLocations(): TestDataResult
    {
        $testDataResult = new TestDataResult();
        $testDataResult->location1 = new Location($this->persistence);
        $testDataResult->location1->set('name', 'Assembly Hall');
        $testDataResult->location1->set('address', 'SomeStreet 14 12345 Somewhere');
        $testDataResult->location1->save();

        $testDataResult->location2 = new Location($this->persistence);
        $testDataResult->location2->set('name', 'Harrys House');
        $testDataResult->location2->set('address', 'Someway 433 98765 Whereever');
        $testDataResult->location2->save();

        $testDataResult->event1 = new Event($this->persistence);
        $testDataResult->event1->set('name', 'Birthday Jenny');
        $testDataResult->event1->set('date', '2023-10-04');
        $testDataResult->event1->set('location_id', $testDataResult->location1->getId());
        $testDataResult->event1->save();

        $testDataResult->event2 = new Event($this->persistence);
        $testDataResult->event2->set('name', 'Company Christmas Party');
        $testDataResult->event2->set('date', '2023-12-07');
        $testDataResult->event2->set('location_id', $testDataResult->location1->getId());
        $testDataResult->event2->save();

        $testDataResult->event3 = new Event($this->persistence);
        $testDataResult->event3->set('name', 'Meeting with School Friends');
        $testDataResult->event3->set('date', '2023-09-25');
        $testDataResult->event3->set('location_id', $testDataResult->location2->getId());
        $testDataResult->event3->save();

        return $testDataResult;
    }
}
