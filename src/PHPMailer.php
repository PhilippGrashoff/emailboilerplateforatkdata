<?php declare(strict_types=1);

namespace predefinedemailsforatk;

use Atk4\Data\Persistence;
use Throwable;

class PHPMailer extends \PHPMailer\PHPMailer\PHPMailer
{

    //the EmailAccount to send from. If not set, use first one
    protected EmailAccount $emailAccount;
    protected Persistence $persistence;

    public bool $addImapDebugInfo = false;
    public array $imapErrors = [];
    public bool $appendedByIMAP = false;


    public function __construct(Persistence $persistence)
    {
        $this->persistence = $persistence;
        $this->CharSet = 'utf-8';
        //set SMTP sending
        $this->isSMTP();
        $this->SMTPDebug = 0;
        $this->SMTPAuth = true;

        parent::__construct();
    }

    public function setEmailAccount($emailAccountId): void
    {
        $this->emailAccount = new EmailAccount($this->persistence);
        $this->emailAccount->load($emailAccountId);
        $this->copySettingsFromEmailAccount();
    }

    protected function copySettingsFromEmailAccount(): void
    {
        $this->Host = $this->emailAccount->get('smtp_host');
        $this->Port = $this->emailAccount->get('smtp_port');
        $this->Username = $this->emailAccount->get('user');
        $this->Password = $this->emailAccount->get('password');
        $this->setFrom($this->emailAccount->get('email_address'), $this->emailAccount->get('sender_name'));
        if ($this->emailAccount->get('allow_self_signed_ssl')) {
            $this->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ];
        }
    }

    /**
     * add Email to IMAP if set
     * TODO: Find some nice Lib for this
     */
    public function addSentEmailByIMAP(): bool
    {
        if (
            !$this->emailAccount->get('imap_host')
            || !$this->emailAccount->get('imap_port')
        ) {
            $this->appendedByIMAP = false;
            return $this->appendedByIMAP;
        }
        $imap_mailbox = $this->getImapPath() . $this->emailAccount->get('imap_sent_folder');

        try {
            $imapStream = imap_open(
                $imap_mailbox,
                $this->emailAccount->get('user'),
                $this->emailAccount->get('password')
            );
            $this->appendedByIMAP = imap_append($imapStream, $imap_mailbox, $this->getSentMIMEMessage());
            if ($this->addImapDebugInfo) {
                $imapErrors = imap_errors();
                $imapNotices = imap_alerts();
                if ($imapErrors) {
                    $this->imapErrors = $imapErrors;
                }
                if ($imapNotices) {
                    $this->imapErrors = array_merge($this->imapErrors, $imapNotices);
                }
                $mailboxes = imap_list(
                    $imapStream,
                    $this->getImapPath(),
                    '*'
                );
                if (is_array($mailboxes)) {
                    $this->imapErrors[] = 'Vorhandene Mailboxen: ' . implode(', ', $mailboxes);
                }
            }
            imap_close($imapStream);
        } catch (Throwable $e) {
            $this->appendedByIMAP = false;
        }

        return $this->appendedByIMAP;
    }

    protected function getImapPath(): string
    {
        return '{' . $this->emailAccount->get('imap_host') . ':' . $this->emailAccount->get('imap_port')
            . ($this->emailAccount->get('imap_port') == 993 ? '/imap/ssl}' : '}');
    }
}