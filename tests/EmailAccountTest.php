<?php declare(strict_types=1);

namespace emailboilerplateforatkdata\tests;

use auditforatk\Audit;
use PMRAtk\Data\Email\EmailAccount;
use PMRAtk\tests\TestClasses\BaseEmailTestClasses\EmailAccountNoDecrypt;
use traitsforatkdata\TestCase;


class EmailAccountTest extends TestCase {

    protected $sqlitePersistenceModels = [
        EmailAccount::class,
        Audit::class
    ];

    public function testHooks() {
        $persistence = $this->getSqliteTestPersistence();
        $ea = new EmailAccount($persistence);
        $ea->set('user',      'some1');
        $ea->set('password',  'some2');
        $ea->set('imap_host', 'some3');
        $ea->set('imap_port', 'some4');
        $ea->set('smtp_host', 'some5');
        $ea->set('smtp_port', 'some6');
        $ea->save();

        //check if its encrypted by using normal setting
        $setting = new EmailAccountNoDecrypt($persistence);
        $setting->load($ea->get('id'));
        //if encrypted, it shouldnt be unserializable
        self::expectException(\ErrorException::class);
        @unserialize($setting->get('credentials'));
    }
}
