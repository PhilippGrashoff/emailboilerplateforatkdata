<?php declare(strict_types=1);

namespace emailboilerplateforatkdata\tests;

use emailboilerplateforatkdata\BaseEmail;
use emailboilerplateforatkdata\EmailTemplate;
use emailboilerplateforatkdata\tests\testclasses\EventInvitation;
use emailboilerplateforatkdata\tests\testclasses\ExtendedEmailTemplateHandler;
use traitsforatkdata\TestCase;


class EmailTemplateHandlerTest extends TestCase
{
    protected $sqlitePersistenceModels = [
        EmailTemplate::class,
        BaseEmail::class,
    ];

    public function testLoadDefaultTemplateFromFile(): void
    {
        $eventIntivation = new EventInvitation($this->getSqliteTestPersistence(), ['loadInitialValues' => false]);
        $handler = new ExtendedEmailTemplateHandler($eventIntivation);
        $template = $handler->getEmailTemplate();

        self::assertSame(
            '',
            $template->renderToHtml()
        );
    }
}
