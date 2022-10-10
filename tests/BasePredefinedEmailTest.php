<?php declare(strict_types=1);

namespace emailboilerplateforatkdata\tests;

use emailboilerplateforatkdata\Attachment;
use emailboilerplateforatkdata\BasePredefinedEmail;
use emailboilerplateforatkdata\EmailAccount;
use emailboilerplateforatkdata\EmailRecipient;
use emailboilerplateforatkdata\EmailTemplate;
use emailboilerplateforatkdata\SentEmail;
use emailboilerplateforatkdata\tests\emailimplementations\EventSummaryForLocation;
use emailboilerplateforatkdata\tests\testclasses\Event;
use emailboilerplateforatkdata\tests\testclasses\FakePhpMailer;
use emailboilerplateforatkdata\tests\testclasses\Location;
use traitsforatkdata\TestCase;
use traitsforatkdata\UserException;

class BasePredefinedEmailTest extends TestCase
{
    private $persistence;

    protected $sqlitePersistenceModels = [
        Event::class,
        Location::class,
        EmailAccount::class,
        EmailTemplate::class,
        EventSummaryForLocation::class,
        SentEmail::class
    ];

    public function setUp(): void
    {
        parent::setUp();
        $this->persistence = $this->getSqliteTestPersistence();
        //$this->_addStandardEmailAccount($this->persistence);
    }

    public function testAddRecipientOnlyAddSameEmailAddressOnce()
    {
        $email = new EventSummaryForLocation($this->persistence);
        $email->save();

        $email->addRecipient('somefake@email.de', 'Max', 'Mustermann');
        self::assertSame(
            1,
            (int)$email->ref(EmailRecipient::class)->action('count')->getOne()
        );
        $email->addRecipient('somefake@email.de', 'Marina', 'Musterfrau');
        self::assertSame(
            1,
            (int)$email->ref(EmailRecipient::class)->action('count')->getOne()
        );
    }

    public function testAddRecipientInvalidEmailFormatTrowsException()
    {
        $email = new EventSummaryForLocation($this->persistence);
        $email->save();

        self::expectException(UserException::class);
        $email->addRecipient('someinvalid@email', 'Max', 'Mustermann');
    }

    public function testAddAndRemoveRecipient(): void
    {
        $email = new EventSummaryForLocation($this->persistence);
        $email->save();

        $recipient1 = $email->addRecipient('somefake@email.de', 'Max', 'Mustermann');
        $recipient2 = $email->addRecipient('someotherfake@email.de', 'Marina', 'Musterfrau');
        self::assertSame(
            2,
            (int)$email->ref(EmailRecipient::class)->action('count')->getOne()
        );

        $email->removeRecipient($recipient1->getId());
        self::assertSame(
            1,
            (int)$email->ref(EmailRecipient::class)->action('count')->getOne()
        );

        $email->removeRecipient($recipient2->getId());
        self::assertSame(
            0,
            (int)$email->ref(EmailRecipient::class)->action('count')->getOne()
        );
    }

    public function testAddSameAttachmentOnlyOnce(): void
    {
        $email = new EventSummaryForLocation($this->persistence);
        $email->save();

        $email->addAttachment(__DIR__ . '/testtemplatefiles/event_invitation.html');
        $email->addAttachment(__DIR__ . '/testtemplatefiles/event_invitation.html');
        self::assertSame(
            1,
            (int)$email->ref(Attachment::class)->action('count')->getOne()
        );
    }

    public function testAddAndRemoveAttachment(): void
    {
        $email = new EventSummaryForLocation($this->persistence);
        $email->save();

        $attachment1 = $email->addAttachment(__DIR__ . '/testtemplatefiles/event_invitation.html');
        $attachment2 = $email->addAttachment(__DIR__ . '/testtemplatefiles/event_summary_for_location.html');
        self::assertSame(
            2,
            (int)$email->ref(Attachment::class)->action('count')->getOne()
        );

        $email->removeAttachment($attachment1->getId());
        self::assertSame(
            1,
            (int)$email->ref(Attachment::class)->action('count')->getOne()
        );

        $email->removeAttachment($attachment2->getId());
        self::assertSame(
            0,
            (int)$email->ref(Attachment::class)->action('count')->getOne()
        );
    }

    public function testAddHeaderAndFooter(): void
    {
        $emailAccount = new EmailAccount($this->persistence);
        $emailAccount->save();
        $location = new Location($this->persistence);
        $location->save();

        $eventSummaryForLocation = new EventSummaryForLocation($this->persistence, ['entity' => $location]);
        $eventSummaryForLocation->loadInitialValues();
        $eventSummaryForLocation->addRecipient('sometest@sometest.com', 'Peter', 'Maier');
        $eventSummaryForLocation->send();
        self::assertStringContainsString('<div id="header">', $eventSummaryForLocation->phpMailer->Body);
        self::assertStringContainsString('<div id="footer">', $eventSummaryForLocation->phpMailer->Body);

        $eventSummaryForLocation = new EventSummaryForLocation(
            $this->persistence,
            [
                'entity' => $location,
                'addHeaderAndFooter' => false
            ]
        );
        $eventSummaryForLocation->loadInitialValues();
        $eventSummaryForLocation->addRecipient('sometest@sometest.com', 'Peter', 'Maier');
        $eventSummaryForLocation->send();
        self::assertStringNotContainsString('<div id="header">', $eventSummaryForLocation->phpMailer->Body);
        self::assertStringNotContainsString('<div id="footer">', $eventSummaryForLocation->phpMailer->Body);
    }

    public function testProcessSubjectAndMessagePerRecipient()
    {
        $emailAccount = new EmailAccount($this->persistence);
        $emailAccount->save();
        $location = new Location($this->persistence);
        $location->save();
        $eventSummaryForLocation = new EventSummaryForLocation($this->persistence, ['entity' => $location]);
        $eventSummaryForLocation->loadInitialValues();
        $eventSummaryForLocation->addRecipient('sometest1@sometest.com', 'Peter', 'Maier');
        $eventSummaryForLocation->send();
        self::assertStringNotContainsString('Hans', $eventSummaryForLocation->phpMailer->Body);
        self::assertStringNotContainsString('Hans', $eventSummaryForLocation->phpMailer->Subject);

        $eventSummaryForLocation = new EventSummaryForLocation($this->persistence, ['entity' => $location]);
        $eventSummaryForLocation->loadInitialValues();
        $eventSummaryForLocation->addRecipient('sometest2@sometest.com', 'Hans', 'Maier');
        $eventSummaryForLocation->send();
        self::assertStringContainsString('Hans', $eventSummaryForLocation->phpMailer->Body);
        self::assertStringContainsString('Hans', $eventSummaryForLocation->phpMailer->Subject);
    }

    public function testOnSuccessfulSend()
    {
        $emailAccount = new EmailAccount($this->persistence);
        $emailAccount->save();
        $location = new Location($this->persistence);
        $location->save();
        $eventSummaryForLocation = new EventSummaryForLocation(
            $this->persistence,
            [
                'entity' => $location,
                'phpMailerClass' => FakePhpMailer::class
            ]
        );
        $eventSummaryForLocation->loadInitialValues();
        $eventSummaryForLocation->addRecipient('sometest2@sometest.com');
        $eventSummaryForLocation->send();
        self::assertSame(
            1,
            (int)$location->ref(SentEmail::class)->action('count')->getOne()
        );
        self::assertSame(
            EventSummaryForLocation::class,
            $location->ref(SentEmail::class)->loadAny()->get('value')
        );
    }

    public function testSendFromOtherEmailAccount()
    {
        $ea = new EmailAccount($this->persistence);
        $ea->set('name', STD_EMAIL);
        $ea->set('sender_name', 'TESTSENDERNAME');
        $ea->set('user', EMAIL_USERNAME);
        $ea->set('password', EMAIL_PASSWORD);
        $ea->set('smtp_host', EMAIL_HOST);
        $ea->set('smtp_port', EMAIL_PORT);
        $ea->set('imap_host', IMAP_HOST);
        $ea->set('imap_port', IMAP_PORT);
        $ea->set('imap_sent_folder', IMAP_SENT_FOLDER);
        $ea->save();

        $be = new BasePredefinedEmail($this->persistence);
        $be->addRecipient('test3@easyoutdooroffice.com');
        $be->set('email_account_id', $ea->get('id'));
        $be->set('subject', __FUNCTION__);

        self::assertTrue($be->send());
        self::assertEquals('TESTSENDERNAME', $be->phpMailer->FromName);
    }

    public function testGetDefaultEmailAccountId()
    {
        $persistence = $this->getSqliteTestPersistence();
        $be = new BasePredefinedEmail($persistence);
        self::assertNull($be->getDefaultEmailAccountId());
        $this->_addStandardEmailAccount($persistence);
        self::assertNotEmpty($be->getDefaultEmailAccountId());
    }
}
