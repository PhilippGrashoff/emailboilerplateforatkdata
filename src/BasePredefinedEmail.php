<?php declare(strict_types=1);

namespace emailboilerplateforatkdata;

use Atk4\Data\Exception;
use Atk4\Data\Model;
use Atk4\Ui\Form\Control\Dropdown;
use Atk4\Ui\HtmlTemplate;
use traitsforatkdata\UserException;

abstract class BasePredefinedEmail extends Model
{

    public $table = 'predefined_email';

    //A title: what is this email for
    public string $title = '';
    //like title, but more descriptive: What is this email for
    public string $description = '';

    protected string $modelClassName = '';
    protected $entityId = null;
    public ?Model $entity = null;

    //the template to load to get initial subject and message
    public string $defaultTemplateFile = '';
    protected HtmlTemplate $messageTemplate;
    protected HtmlTemplate $subjectTemplate;
    protected bool $addHeaderAndFooter = true;
    public HtmlTemplate $headerTemplate;
    public HtmlTemplate $footerTemplate;
    //can it have multiple email templates, e.g. per Activity?
    protected bool $canHaveMultipleTemplates = false;

    //PHPMailer instance which takes care of the actual sending
    private PHPMailer $phpMailer;

    protected string $emailTemplateHandlerClassName = BaseEmailTemplateHandler::class;
    protected BaseEmailTemplateHandler $emailTemplateHandler;

    protected function init(): void
    {
        parent::init();
        $this->addField(
            'subject_template'
        );
        $this->addField(
            'message_template',
            ['type' => 'text']
        );

        $this->hasOne(
            'email_account_id',
            [
                'model' => [EmailAccount::class],
                'type' => 'integer',
                'ui' => ['form' => [Dropdown::class, 'empty' => '...']]
            ]
        );

        $this->containsMany(EmailRecipient::class, ['model' => [EmailRecipient::class]]);
        $this->containsMany(Attachment::class, ['model' => [Attachment::class]]);

        $className = $this->emailTemplateHandlerClassName;
        $this->emailTemplateHandler = new $className($this);
    }

    public function loadInitialValues()
    {
        $this->setModel();
        $this->loadInitialRecipients();
        $this->loadInitialAttachments();
        $this->loadTemplates();
    }

    protected function loadTemplates(): void
    {
        $this->messageTemplate = $this->emailTemplateHandler->getEmailTemplate();

        $this->messageTemplate->trySet('recipient_firstname', '{$recipient_firstname}');
        $this->messageTemplate->trySet('recipient_lastname', '{$recipient_lastname}');
        $this->messageTemplate->trySet('recipient_email', '{$recipient_email}');

        $this->processMessageTemplateOnLoad();
        $this->loadSubjectFromTemplate();
        $this->processSubjectTemplateOnLoad();

        $this->set('subject_template', $this->subjectTemplate->renderToHtml());
        $this->set('message_template', $this->messageTemplate->renderToHtml());
    }

    protected function loadSubjectFromTemplate(): void
    {
        //get subject from Template if available
        if ($this->messageTemplate->hasTag('Subject')) {
            $this->subjectTemplate = $this->messageTemplate->cloneRegion('Subject');
            $this->messageTemplate->del('Subject');
        } else {
            $this->$this->subjectTemplate = new HtmlTemplate('');
        }
    }

    protected function setModel(): void
    {
        if ($this->entity && $this->entity->loaded()) {
            return;
        }

        if ($this->entityId) {
            $this->entity = new $this->modelClassName($this->persistence);
            $this->entity->load($this->entityId);
            return;
        }

        throw new Exception('Either a loaded model or an ID to load needs to be passed to ' . __FUNCTION__);
    }

    public function addRecipient(string $emailAddress, string $firstname = '', string $lastname = ''): EmailRecipient
    {
        $emailAddress = trim($emailAddress);
        if (!filter_var($emailAddress, FILTER_VALIDATE_EMAIL)) {
            throw new UserException('The email address ' . $emailAddress . ' is not a valid.');
        }
        $emailRecipient = $this->ref(EmailRecipient::class);
        //some bug in ContainsMany. Switch this in-performant code to tryLoadBy() instead of iterating all with atk 3.x
        foreach ($emailRecipient as $er) {
            if ($er->get('email_address') === $emailAddress) {
                return $er;
            }
        }

        $emailRecipient->set('email_address', $emailAddress);
        $emailRecipient->set('firstname', $firstname);
        $emailRecipient->set('lastname', $lastname);
        $emailRecipient->save();
        return $emailRecipient;
    }

    public function removeRecipient($id): bool
    {
        foreach ($this->ref(EmailRecipient::class) as $emailRecipient) {
            if ($emailRecipient->getId() == $id) {
                $emailRecipient->delete();
                return true;
            }
        }

        return false;
    }

    public function addAttachment(string $filePath): Attachment
    {
        $attachment = $this->ref(Attachment::class);
        //some bug in ContainsMany. Switch this in-performant code to tryLoadBy() instead of iterating all with atk 3.x
        foreach ($attachment as $a) {
            if ($a->get('file_path') === $filePath) {
                return $a;
            }
        }
        $attachment->set('file_path', $filePath);
        $attachment->save();

        return $attachment;
    }

    public function removeAttachment($id): bool
    {
        foreach ($this->ref(Attachment::class) as $emailRecipient) {
            if ($emailRecipient->getId() == $id) {
                $emailRecipient->delete();
                return true;
            }
        }

        return false;
    }


    /**
     * sends the message to each recipient in the list
     *
     * @return bool   true if at least one send was successful, false otherwise
     */
    public function send(): bool
    {
        //superimportant, due to awful behaviour of ref() function we need to make
        //sure $this is loaded
        if (!$this->loaded()) {
            $this->save();
        }

        if (!$this->phpMailer instanceof PHPMailer) {
            $this->phpMailer = new PHPMailer($this->app);
        }

        $this->phpMailer->emailAccount = $this->get('email_account_id') ?? $this->getDefaultEmailAccountId();

        //create a template from message so tags set in message like
        //{$firstname} can be filled
        $mt = new Template();
        $mt->loadTemplateFromString((string)$this->get('message'));

        $st = new Template();
        $st->loadTemplateFromString((string)$this->get('subject'));

        //add Attachments
        if ($this->get('attachments')) {
            $a_files = new File($this->persistence);
            $a_files->addCondition('id', 'in', $this->get('attachments'));
            foreach ($a_files as $a) {
                $this->phpMailer->addAttachment($a->getFullFilePath());
            }
        }

        //if email is sent to several recipients, keep SMTP connection open
        if (intval($this->ref('email_recipient')->action('count')->getOne()) > 1) {
            $this->phpMailer->SMTPKeepAlive = true;
        }

        $successful_send = false;
        //single send for each recipient
        foreach ($this->ref('email_recipient') as $r) {
            //clone message and subject so changes per recipient wont affect
            //other recipients
            $message_template = clone $mt;
            $subject_template = clone $st;

            //try to put the emailrecipient fields in template
            $message_template->trySet('recipient_firstname', $r->get('firstname'));
            $message_template->trySet('recipient_lastname', $r->get('lastname'));
            $message_template->trySet('recipient_email', $r->get('email'));

            $subject_template->trySet('recipient_firstname', $r->get('firstname'));
            $subject_template->trySet('recipient_lastname', $r->get('lastname'));
            $subject_template->trySet('recipient_email', $r->get('email'));

            //add ability to further alter subject and message per Recipient
            if (is_callable($this->processSubjectPerRecipient)) {
                call_user_func($this->processSubjectPerRecipient, $r, $subject_template);
            }
            if (is_callable($this->processMessagePerRecipient)) {
                call_user_func($this->processMessagePerRecipient, $r, $message_template);
            }

            $this->phpMailer->Subject = $subject_template->render();
            $this->phpMailer->Body = $this->header . $message_template->render() . $this->footer;
            $this->phpMailer->AltBody = $this->phpMailer->html2text($this->phpMailer->Body);
            $this->phpMailer->addAddress($r->get('email'), $r->get('firstname') . ' ' . $r->get('lastname'));

            //Send Email
            if (!$this->phpMailer->send()) {
                if ($this->addUserMessageOnSend) {
                    $this->app->addUserMessage(
                        'Die Email ' . $this->phpMailer->Subject . ' konnte nicht an  ' . $r->get(
                            'email'
                        ) . ' gesendet werden.',
                        'error'
                    );
                }
            } else {
                $successful_send = true;
                if ($this->addUserMessageOnSend) {
                    $this->app->addUserMessage(
                        'Die Email ' . $this->phpMailer->Subject . ' wurde erfolgreich an ' . $r->get(
                            'email'
                        ) . ' versendet.',
                        'success'
                    );
                }
                //add Email to IMAP Sent Folder
                $this->phpMailer->addSentEmailByIMAP();
            }

            //clear recipient after each Email
            $this->phpMailer->clearAddresses();
        }

        if ($successful_send && is_callable($this->onSuccess)) {
            call_user_func($this->onSuccess, $this->model);
        }

        $this->delete();

        return $successful_send;
    }

    /**
     * can be implemented in descendants. Can be used to set a standard Email Account to send from when more than one is available
     */
    public function getDefaultEmailAccountId()
    {
        $ea = new EmailAccount($this->persistence);
        $ea->addCondition('id', '>', -1);
        $ea->tryLoadAny();
        if ($ea->loaded()) {
            return $ea->get('id');
        }
        return null;
    }

    /**
     * The following methods can be implemented in child classes to create a custom behaviour.
     * Check the test files for sample usages.
     */
    protected function loadHeaderTemplate(): void
    {
    }

    protected function loadFooterTemplate(): void
    {
    }

    protected function processSubjectTemplatePerRecipient(): void
    {
    }

    protected function processMessageTemplatePerRecipient(): void
    {
    }

    protected function processMessageTemplateOnLoad(): void
    {
    }

    protected function processSubjectTemplateOnLoad(): void
    {
    }

    protected function onSuccessfulSend(): void
    {
    }

    protected function loadInitialRecipients(): void
    {
    }

    protected function loadInitialAttachments(): void
    {
    }
}
