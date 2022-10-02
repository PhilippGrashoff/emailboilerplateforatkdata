<?php declare(strict_types=1);

namespace emailboilerplateforatkdata\tests;


use auditforatk\Audit;
use PMRAtk\App\App;
use PMRAtk\Data\Email\BaseEmail;
use PMRAtk\Data\Email\EmailAccount;
use PMRAtk\Data\Email\EmailTemplate;
use PMRAtk\Data\Email\PHPMailer;
use PMRAtk\tests\phpunit\TestCase;
use settingsforatk\Setting;
use settingsforatk\SettingGroup;


class PHPMailerTest extends TestCase
{

    private $app;
    private $persistence;

    protected $sqlitePersistenceModels = [
        BaseEmail::class,
        EmailAccount::class,
        EmailTemplate::class,
        Audit::class,
        Setting::class,
        SettingGroup::class
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

    public function testAddUUID()
    {
        $tt = new PHPMailer($this->app);
        self::assertFalse($tt->send());
    }

    public function testCustomEmailAccount()
    {
        $pm = new PHPMailer($this->app);

        $ea = new EmailAccount($this->app->db);
        $ea->set('name', 'DUDU');
        $ea->set('sender_name', 'DUDU');
        $ea->set('user', 'DUDU');
        $ea->set('password', 'DUDU');
        $ea->set('smtp_host', 'DUDU');
        $ea->set('smtp_port', 'DUDU');
        $ea->set('imap_host', 'DUDU');
        $ea->set('imap_port', 'DUDU');
        $ea->set('imap_sent_folder', 'DUDU');
        $ea->save();

        $pm = new PHPMailer($this->app, ['emailAccount' => $ea]);
        $this->callProtected($pm, '_setEmailAccount');
        self::assertEquals('DUDU', $pm->Host);
    }

    public function testCustomEmailAccountById()
    {
        $pm = new PHPMailer($this->app);

        $ea = new EmailAccount($this->app->db);
        $ea->set('name', 'DUDU');
        $ea->set('sender_name', 'DUDU');
        $ea->set('user', 'DUDU');
        $ea->set('password', 'DUDU');
        $ea->set('smtp_host', 'DUDU');
        $ea->set('smtp_port', 'DUDU');
        $ea->set('imap_host', 'DUDU');
        $ea->set('imap_port', 'DUDU');
        $ea->set('imap_sent_folder', 'DUDU');
        $ea->save();

        $pm = new PHPMailer($this->app, ['emailAccount' => $ea->get('id')]);
        $this->callProtected($pm, '_setEmailAccount');
        self::assertEquals('DUDU', $pm->Host);
    }

    public function testaddSentEmailByIMAP()
    {
        $ea = (new EmailAccount($this->persistence))->loadAny();
        $imapHost = $ea->get('imap_host');

        //first unset some needed Imap field
        $ea->set('imap_host', '');
        $ea->save();
        $pm = new PHPMailer($this->app, ['emailAccount' => $ea->get('id')]);
        self::assertFalse($pm->addSentEmailByIMAP());

        //now set it to some false value
        $ea->set('imap_host', 'fsdfd');
        $ea->save();
        $pm = new PHPMailer($this->app, ['emailAccount' => $ea->get('id')]);
        self::assertFalse($pm->addSentEmailByIMAP());

        //now back to initial value, should work
        $ea->set('imap_host', $imapHost);
        $ea->save();
        $pm = new PHPMailer($this->app, ['emailAccount' => $ea->get('id')]);
        $pm->addAddress($ea->get('name'));
        $pm->setBody('JJAA');
        $pm->Subject = 'KKAA';
        self::assertTrue($pm->send());
        self::assertTrue($pm->addSentEmailByIMAP());
    }

    public function testAllowSelfSignedSSLCertificate()
    {
        $ea = (new EmailAccount($this->persistence))->loadAny();
        $ea->set('allow_self_signed_ssl', 1);
        $ea->save();
        $ea->reload();
        $pm = new PHPMailer($this->app, ['emailAccount' => $ea->get('id')]);
        $pm->addAddress($ea->get('name'));
        $pm->setBody('ssltest');
        $pm->Subject = 'ssltest';
        self::assertTrue($pm->send());
        self::assertNotEmpty($pm->SMTPOptions);
    }

    public function testExceptionNoEmailAccountAvailable()
    {
        (new EmailAccount($this->persistence))->loadAny()->delete();
        $pm = new PHPMailer($this->app);
        self::expectException(\atk4\core\Exception::class);
        $this->callProtected($pm, '_setEmailAccount');
    }

    public function testIMAPCollectImapDebugInfo()
    {
        //now back to initial value, should work
        $ea = (new EmailAccount($this->persistence))->loadAny();
        $ea->set('imap_sent_folder', 'SomeNonExistantFolder');
        $ea->save();
        $pm = new PHPMailer($this->app, ['emailAccount' => $ea->get('id')]);
        $pm->addAddress($ea->get('name'));
        $pm->addImapDebugInfo = true;
        $pm->setBody('JJAA');
        $pm->Subject = 'KKAA';
        self::assertTrue($pm->send());
        self::assertFalse($pm->addSentEmailByIMAP());
        self::assertTrue(count($pm->imapErrors) > 0);
    }
}
