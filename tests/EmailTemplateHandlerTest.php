<?php declare(strict_types=1);

namespace emailboilerplateforatkdata\tests;

use Atk4\Data\Persistence;
use emailboilerplateforatkdata\EmailAccount;
use emailboilerplateforatkdata\EmailTemplate;
use emailboilerplateforatkdata\tests\testclasses\Event;
use emailboilerplateforatkdata\tests\testclasses\EventInvitation;
use emailboilerplateforatkdata\tests\testclasses\ExtendedEmailTemplateHandler;
use emailboilerplateforatkdata\tests\testclasses\Location;
use emailboilerplateforatkdata\tests\testclasses\TestDataResult;
use traitsforatkdata\TestCase;


class EmailTemplateHandlerTest extends TestCase
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

    public function testLoadDefaultTemplateFromFile(): void
    {
        $testData = $this->setupTestEventsAndLocations();
        $eventIntivation = new EventInvitation($this->persistence, ['entityId' => $testData->event1->getId()]);
        $handler = new ExtendedEmailTemplateHandler($eventIntivation);
        $template = $handler->getEmailTemplate();

        self::assertSame(
            '',
            $template->renderToHtml()
        );
    }

    protected function setupTestEventsAndLocations(): TestDataResult
    {
        $location1 = new Location($this->persistence);
        $location1->set('name', 'Assembly Hall');
        $location1->set('address', 'SomeStreet 14 12345 Somewhere');
        $location1->save();
        $location2 = new Location($this->persistence);
        $location2->set('name', 'Harrys House');
        $location2->set('address', 'Someway 433 98765 Whereever');
        $location2->save();

        $event1 = new Event($this->persistence);
        $event1->set('name', 'Birthday Jenny');
        $event1->set('date', '2023-10-04');
        $event1->set('location_id', $location1->getId());
        $event1->save();

        $event2 = new Event($this->persistence);
        $event2->set('name', 'Company Christmas Party');
        $event2->set('date', '2023-12-07');
        $event2->set('location_id', $location1->getId());
        $event2->save();

        $event3 = new Event($this->persistence);
        $event3->set('name', 'Meeting with School Friends');
        $event3->set('date', '2023-09-25');
        $event3->set('location_id', $location2->getId());
        $event3->save();

        $testDataResult = new TestDataResult();
        $testDataResult->event1 = $event1;
        $testDataResult->event2 = $event2;
        $testDataResult->event3 = $event3;

        return $testDataResult;
    }
}
