<?php declare(strict_types=1);

namespace predefinedemailsforatk;

use Atk4\Data\Model;
use Atk4\Ui\Form\Control\Dropdown;
use traitsforatkdata\EncryptedFieldTrait;

class EmailAccount extends Model
{

    use EncryptedFieldTrait;

    public $table = 'email_account';

    protected string $encryptionKey = '';

    protected function init(): void
    {
        parent::init();
        $this->addField(
            'email_address',
            [
                'type' => 'string',
                'caption' => 'Email-Adresse'
            ]
        );
        $this->addField(
            'sender_name',
            [
                'type' => 'string',
                'caption' => 'Name des Versenders'
            ]
        );
        $this->addField(
            'details',
            [
                'type' => 'text'
            ]
        );
        $this->addField(

            'user',
            [
                'type' => 'string',
                'caption' => 'Benutzername'
            ]
        );
        $this->addField(

            'password',
            [
                'type' => 'string',
                'caption' => 'Passwort'
            ]
        );
        $this->addField(
            'imap_host',
            [
                'type' => 'string',
                'caption' => 'IMAP Host'
            ]
        );
        $this->addField(
            'imap_port',
            [
                'type' => 'string',
                'caption' => 'IMAP Port'
            ]
        );
        $this->addField(
            'imap_sent_folder',
            [
                'type' => 'string',
                'caption' => 'IMAP: Gesendet-Ordner'
            ]
        );
        $this->addField(
            'smtp_host',
            [
                'type' => 'string',
                'caption' => 'SMTP Host'
            ]
        );
        $this->addField(
            'smtp_port',
            [
                'type' => 'string',
                'caption' => 'SMTP Port',
            ]
        );
        $this->addField(
            'allow_self_signed_ssl',
            [
                'type' => 'integer',
                'caption' => 'SSL: Self-signed Zertifikate erlauben',
                'ui' => ['form' => [Dropdown::class, 'values' => [0 => 'Nein', '1' => 'Ja']]]
            ]
        );

        $this->encryptField($this->getField('password'), $this->encryptionKey);
    }
}