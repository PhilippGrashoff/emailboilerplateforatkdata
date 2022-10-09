<?php declare(strict_types=1);

namespace emailboilerplateforatkdata\tests;

use Atk4\Data\Field\Email;
use emailboilerplateforatkdata\Attachment;
use emailboilerplateforatkdata\BasePredefinedEmail;
use emailboilerplateforatkdata\EmailAccount;
use emailboilerplateforatkdata\EmailRecipient;
use emailboilerplateforatkdata\EmailTemplate;
use emailboilerplateforatkdata\tests\emailimplementations\EventInvitation;
use emailboilerplateforatkdata\tests\emailimplementations\EventSummaryForLocation;
use emailboilerplateforatkdata\tests\testclasses\Event;
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


    public function testUseHeaderAndFooterFromFile(): void
    {
    }

    public function testUseHeaderAndFooterFromPersistence(): void
    {
    }

    public function testDoNotAddHeaderAndFooter(): void
    {
    }

    public function testProcessSubjectAndMessagePerRecipient()
    {
        $emailAccount = new EmailAccount($this->persistence);
        $emailAccount->save();
        $location = new Location($this->persistence);
        $location->save();
        $eventInvitation = new EventSummaryForLocation($this->persistence, ['entity' => $location]);
        $eventInvitation->loadInitialValues();
        $eventInvitation->addRecipient('test1@easyoutdooroffice.com', 'Peter', 'Maier');
        $eventInvitation->send();
        self::assertStringNotContainsString('Hans', $eventInvitation->phpMailer->Body);
        self::assertStringNotContainsString('Hans', $eventInvitation->phpMailer->Subject);

        $eventInvitation = new EventSummaryForLocation($this->persistence,['entity' => $location]);
        $eventInvitation->loadInitialValues();
        $eventInvitation->addRecipient('test1@easyoutdooroffice.com', 'Hans', 'Maier');
        $eventInvitation->send();
        self::assertStringContainsString('Hans', $eventInvitation->phpMailer->Body);
        self::assertStringContainsString('Hans', $eventInvitation->phpMailer->Subject);
    }


    public function testOnSuccessFunction()
    {
        $base_email = new EventSummaryForLocation(
            $this->persistence,
            ['template' => '{Subject}BlaDu{$testsubject}{/Subject}BlaDu{$testbody}']
        );
        $base_email->loadInitialValues();
        $base_email->model = new User($this->persistence);
        $base_email->onSuccess = function ($model) {
            $model->set('name', 'PIPI');
        };
        $base_email->addRecipient('test1@easyoutdooroffice.com');
        self::assertTrue($base_email->send());
        self::assertEquals('PIPI', $base_email->model->get('name'));
    }


    public function testGetModelVars()
    {
        $be = new BasePredefinedEmail($this->persistence);
        $res = $be->getModelVars(new User($this->persistence));
        self::assertEquals(
            [
                'firstname' => 'Vorname',
                'lastname' => 'Nachname',
                'username' => 'Benutzername',
                'signature' => 'Signatur',
                'role' => 'Benutzerrolle'
            ],
            $res
        );
    }

    public function testGetModelVarsPrefix()
    {
        $be = new BasePredefinedEmail($this->persistence);
        $res = $be->getModelVars(new User($this->persistence), 'user_');
        self::assertEquals(
            [
                'user_firstname' => 'Vorname',
                'user_lastname' => 'Nachname',
                'user_username' => 'Benutzername',
                'user_signature' => 'Signatur',
                'user_role' => 'Benutzerrolle'
            ],
            $res
        );
    }

    public function testgetModelVarsUsesGetFieldsForEmailTemplate()
    {
        $be = new BasePredefinedEmail($this->persistence);
        $class = new class() extends User {
            public function getFieldsForEmailTemplate(): array
            {
                return [
                    'firstname',
                    'lastname'
                ];
            }
        };
        $res = $be->getModelVars(new $class($this->persistence),);
        self::assertEquals(
            [
                'firstname' => 'Vorname',
                'lastname' => 'Nachname',
            ],
            $res
        );
    }

    public function testgetTemplateEditVars()
    {
        $be = new BasePredefinedEmail($this->persistence);
        $be->model = new User($this->persistence);
        self::assertEquals(
            [
                'Benutzer' =>
                    [
                        'user_firstname' => 'Vorname',
                        'user_lastname' => 'Nachname',
                        'user_username' => 'Benutzername',
                        'user_signature' => 'Signatur',
                        'user_role' => 'Benutzerrolle'
                    ]
            ],
            $be->getTemplateEditVars()
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

    public function testSMTPKeepAlive()
    {
        $base_email = new EventInvitation(
            $this->persistence,
            ['template' => '{Subject}TestMoreThanOneRecipient{/Subject}TestMoreThanOneRecipient{Signature}{/Signature}']
        );
        $base_email->loadInitialValues();
        $base_email->save();
        self::assertTrue($base_email->addRecipient('test1@easyoutdooroffice.com'));
        self::assertTrue($base_email->addRecipient('test2@easyoutdooroffice.com'));
        $base_email->send();
    }
}
