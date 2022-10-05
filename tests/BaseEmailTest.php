<?php declare(strict_types=1);

namespace emailboilerplateforatkdata\tests;

use emailboilerplateforatkdata\BaseEmail;
use emailboilerplateforatkdata\EmailAccount;
use emailboilerplateforatkdata\EmailAddress;
use emailboilerplateforatkdata\EmailTemplate;
use traitsforatkdata\TestCase;


class BaseEmailTest extends TestCase
{
    private $app;
    private $persistence;

    protected $sqlitePersistenceModels = [
        EmailAccount::class,
        EmailTemplate::class,
        EmailAddress::class,
        BaseEmail::class,
    ];

    public function setUp(): void
    {
        parent::setUp();
        $this->persistence = $this->getSqliteTestPersistence();
        $this->app = new App(['nologin'], ['always_run' => false]);
        $this->app->db = $this->persistence;
        $this->persistence->app = $this->app;
        $this->_addStandardEmailAccount($this->persistence);
    }

    public function testAddRecipient()
    {
        $base_email = new BaseEmail($this->persistence);
        $base_email->save();

        //pass a Guide, should have an email set
        $user = new User($this->persistence);
        $user->set('username', 'Lala');
        $user->set('firstname', 'Lala');
        $user->set('lastname', 'Dusu');
        $user->save();
        $user->addSecondaryModelRecord(Email::class, 'test1@easyoutdooroffice.com');
        self::assertTrue($base_email->addRecipient($user));
        self::assertEquals(1, $base_email->ref('email_recipient')->action('count')->getOne());

        //adding the same guide again shouldnt change anything
        self::assertFalse($base_email->addRecipient($user));
        self::assertEquals(1, $base_email->ref('email_recipient')->action('count')->getOne());

        //pass a non-loaded Guide
        $user = new User($this->persistence);
        self::assertFalse($base_email->addRecipient($user));

        //pass a Guide without an existing Email
        $user = new User($this->persistence);
        $user->set('username', 'LALA');
        $user->save();
        self::assertFalse($base_email->addRecipient($user));

        //pass an email id
        $user = new User($this->persistence);
        $user->set('username', 'other');
        $user->save();
        $e = $user->addSecondaryModelRecord(Email::class, 'test3@easyoutdooroffice.com');
        self::assertTrue($base_email->addRecipient($e->get('id')));
        self::assertEquals(2, $base_email->ref('email_recipient')->action('count')->getOne());

        //pass a non existing email id
        self::assertFalse($base_email->addRecipient(111111));

        //pass existing email id that does not belong to any parent model
        $e = new Email($this->persistence);
        $e->set('value', 'test1@easyoutdooroffice.com');
        $e->save();
        self::assertFalse($base_email->addRecipient($e->get('id')));


        //pass a valid Email
        self::assertTrue($base_email->addRecipient('philipp@spame.de'));
        self::assertEquals(3, $base_email->ref('email_recipient')->action('count')->getOne());

        //pass an invalid email
        self::assertFalse($base_email->addRecipient('hannsedfsgs'));

        //now remove all
        foreach ($base_email->ref('email_recipient') as $rec) {
            self::assertTrue($base_email->removeRecipient($rec->get('id')));
        }
        self::assertEquals(0, $base_email->ref('email_recipient')->action('count')->getOne());

        //remove some non_existing EmailRecipient
        self::assertFalse($base_email->removeRecipient('11111'));

        //test adding not the first, but some other email
        $user = new User($this->persistence);
        $user->set('username', 'evenanother');
        $user->save();
        $user->addSecondaryModelRecord(Email::class, 'test1@easyoutdooroffice.com');
        $test2_id = $user->addSecondaryModelRecord(Email::class, 'test2@easyoutdooroffice.com');
        self::assertTrue($base_email->addRecipient($user, $test2_id->get('id')));
        //now there should be a single recipient and its email should be test2...
        foreach ($base_email->ref('email_recipient') as $rec) {
            self::assertEquals($rec->get('email'), 'test2@easyoutdooroffice.com');
        }
    }

    public function testSend()
    {
        //no recipients, should return false
        $base_email = new BaseEmail($this->persistence);
        self::assertFalse($base_email->send());

        //one recipient, should return true
        $base_email = new BaseEmail($this->persistence);
        $base_email->set('subject', 'Hello from PHPUnit');
        $base_email->set('message', 'Hello from PHPUnit');
        self::assertTrue($base_email->addRecipient('test2@easyoutdooroffice.com'));
        self::assertTrue($base_email->send());
    }

    public function testloadInitialValues()
    {
        $base_email = new BaseEmail($this->persistence);
        $base_email->loadInitialValues();
        self::assertTrue(true);
    }

    public function testAttachments()
    {
        $base_email = new BaseEmail($this->persistence);
        $base_email->save();
        $file = $this->createTestFile('test.jpg', $this->persistence);
        $base_email->addAttachment($file->get('id'));
        self::assertEquals(1, count($base_email->get('attachments')));

        $base_email->removeAttachment($file->get('id'));
        self::assertEquals(0, count($base_email->get('attachments')));
    }

    public function testSendAttachments()
    {
        $base_email = new BaseEmail($this->persistence);
        $base_email->save();
        $file = $this->createTestFile('test.jpg', $this->persistence);
        $base_email->addAttachment($file->get('id'));
        self::assertTrue($base_email->addRecipient('test1@easyoutdooroffice.com'));
        self::assertTrue($base_email->send());
    }

    public function testInitialTemplateLoading()
    {
        $base_email = new BaseEmail($this->persistence, ['template' => 'testemailtemplate.html']);
        $base_email->loadInitialValues();
        self::assertEquals($base_email->get('subject'), 'TestBetreff');
        self::assertTrue(strpos($base_email->get('message'), 'TestInhalt') !== false);
    }

    public function testInitialTemplateLoadingByString()
    {
        $base_email = new BaseEmail($this->persistence, ['template' => '{Subject}Hellow{/Subject}Magada']);
        $base_email->loadInitialValues();
        self::assertEquals($base_email->get('subject'), 'Hellow');
        self::assertTrue(strpos($base_email->get('message'), 'Magada') !== false);
    }

    public function testLoadSignatureByUserSignature()
    {
        $user = new User($this->persistence);
        $user->set('username', 'LALA');
        $user->set('signature', 'TestSignature');
        $user->save();
        $this->app->auth->user = $user;
        $base_email = new BaseEmail(
            $this->persistence,
            ['template' => '{Subject}Hellow{/Subject}Magada{Signature}{/Signature}Lala']
        );
        $base_email->loadInitialValues();
        self::assertTrue(strpos($base_email->get('message'), 'TestSignature') !== false);
    }

    public function testloadSignatureBySetting()
    {
        $this->app->addSetting('STD_EMAIL_SIGNATURE', 'TestSigSetting');
        $base_email = new BaseEmail(
            $this->persistence,
            ['template' => '{Subject}Hellow{/Subject}Magada{Signature}{/Signature}']
        );
        $base_email->loadInitialValues();
        self::assertTrue(strpos($base_email->get('message'), 'TestSigSetting') !== false);
    }

    public function testSMTPKeepAlive()
    {
        $base_email = new BaseEmail(
            $this->persistence,
            ['template' => '{Subject}TestMoreThanOneRecipient{/Subject}TestMoreThanOneRecipient{Signature}{/Signature}']
        );
        $base_email->loadInitialValues();
        $base_email->save();
        self::assertTrue($base_email->addRecipient('test1@easyoutdooroffice.com'));
        self::assertTrue($base_email->addRecipient('test2@easyoutdooroffice.com'));
        $base_email->send();
    }

    public function testProcessSubjectAndMessagePerRecipient()
    {
        $base_email = new EditPerRecipientEmail(
            $this->persistence,
            ['template' => '{Subject}BlaDu{$testsubject}{/Subject}BlaDu{$testbody}']
        );
        $base_email->loadInitialValues();
        $base_email->processSubjectPerRecipient = function ($recipient, $template) {
            $template->set('testsubject', 'HARALD');
        };
        $base_email->processMessagePerRecipient = function ($recipient, $template) {
            $template->set('testbody', 'MARTOR');
        };
        $base_email->addRecipient('test1@easyoutdooroffice.com');
        self::assertTrue($base_email->send());
        self::assertTrue(strpos($base_email->phpMailer->getSentMIMEMessage(), 'HARALD') !== false);
        self::assertTrue(strpos($base_email->phpMailer->getSentMIMEMessage(), 'MARTOR') !== false);
    }

    public function testProcessMessageFunction()
    {
        $base_email = new BaseEmail(
            $this->persistence,
            ['template' => '{Subject}BlaDu{$testsubject}{/Subject}BlaDu{$testbody}']
        );
        $base_email->processMessageTemplate = function ($template, $model) {
            $template->set('testbody', 'HALLELUJA');
        };
        $base_email->processSubjectTemplate = function ($template, $model) {
            $template->set('testsubject', 'HALLELUJA');
        };
        $base_email->loadInitialValues();
        self::assertTrue(strpos($base_email->get('message'), 'HALLELUJA') !== false);
        self::assertTrue(strpos($base_email->get('subject'), 'HALLELUJA') !== false);
    }

    public function testOnSuccessFunction()
    {
        $base_email = new BaseEmail(
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

    /**
     * F***ing ref() function on non-loaded models!.
     * Make sure non-saved BaseEmail does not accidently
     * load any EmailRecipients
     */
    public function testNonLoadedBaseEmailHasNoRefEmailRecipients()
    {
        //first create a baseEmail and some EmailRecipients
        $be1 = new BaseEmail($this->persistence);
        $be1->save();
        //this baseEmail should not be sent. $be2->ref('email_recipient') will reference
        //the 2 EmailRecipients above as $be2->loaded() = false. BaseEmail needs to check this!
        $be2 = new BaseEmail($this->persistence);
        self::assertFalse($be2->send());
    }

    public function testEmailSendFail()
    {
        $be = new BaseEmail($this->persistence);
        $be->phpMailer = new class($this->app) extends PHPMailer {
            public function send(): bool
            {
                return false;
            }
        };
        $be->addRecipient('test2@easyoutdooroffice.com');
        $be->set('subject', __FUNCTION__);
        $be->save();
        $messages = $this->app->userMessages;
        self::assertFalse($be->send());
        //should add message to app
        $new_messages = $this->app->userMessages;
        self::assertEquals(count($messages) + 1, count($new_messages));
    }

    public function testGetModelVars()
    {
        $be = new BaseEmail($this->persistence);
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
        $be = new BaseEmail($this->persistence);
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
        $be = new BaseEmail($this->persistence);
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
        $be = new BaseEmail($this->persistence);
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

        $be = new BaseEmail($this->persistence);
        $be->addRecipient('test3@easyoutdooroffice.com');
        $be->set('email_account_id', $ea->get('id'));
        $be->set('subject', __FUNCTION__);

        self::assertTrue($be->send());
        self::assertEquals('TESTSENDERNAME', $be->phpMailer->FromName);
    }

    public function testGetDefaultEmailAccountId()
    {
        $persistence = $this->getSqliteTestPersistence();
        $be = new BaseEmail($persistence);
        self::assertNull($be->getDefaultEmailAccountId());
        $this->_addStandardEmailAccount($persistence);
        self::assertNotEmpty($be->getDefaultEmailAccountId());
    }

    public function testGetAllImplementations()
    {
        $res = (new BaseEmail($this->persistence))->getAllImplementations(
            [
                FILE_BASE_PATH . 'tests/TestClasses/BaseEmailTestClasses' => '\\PMRAtk\tests\\TestClasses\\BaseEmailTestClasses\\'
            ]
        );

        self::assertCount(2, $res);
        self::assertTrue($res['\\' . SomeBaseEmailImplementation::class] instanceof SomeBaseEmailImplementation);
    }

    public function testPassEmailTemplateId()
    {
        $et = new EmailTemplate($this->persistence);
        $et->set('value', '{Subject}LALADU{/Subject}Hammergut');
        $et->save();
        $be = new SomeBaseEmailImplementation($this->persistence, ['emailTemplateId' => $et->get('id')]);
        $be->loadInitialValues();
        self::assertEquals('LALADU', $be->get('subject'));
        self::assertEquals('Hammergut', $be->get('message'));
    }


    /**
     * TODO
     */
    /*public function testSignatureUsesLineBreaks() {
    }

    /**/
}
