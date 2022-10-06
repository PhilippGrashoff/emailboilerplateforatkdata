<?php declare(strict_types=1);

namespace emailboilerplateforatkdata;

use Atk4\Data\Exception;
use Atk4\Data\Model;
use Atk4\Ui\Form\Control\Dropdown;
use Atk4\Ui\HtmlTemplate;

abstract class BaseEmail extends Model
{

    public $table = 'base_email';

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
    protected string $emailAddressClassName = EmailAddress::class;

    protected string $emailTemplateHandlerClassName = EmailTemplateHandler::class;
    protected EmailTemplateHandler $emailTemplateHandler;

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

    protected function loadInitialValues()
    {
        $this->setModel();
        $this->loadInitialRecipients();
        $this->loadInitialAttachments();
        $this->loadTemplates();
    }

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

    //implement in child classes if needed
    protected function loadInitialRecipients(): void
    {
    }

    //implement in child classes if needed
    protected function loadInitialAttachments(): void
    {
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
        $this->set('subject_template', $this->messageTemplate->renderToHtml());
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

    /*
     * adds an object to recipients array.
     *
     * @param mixed class      Either a class, a classname or an email address to add
     * @param int   email_id   Try to load the email with this id if set
     *
     * @return bool            True if something was added, false otherwise
     */
    public function addRecipient($class, $email_id = null)
    {
        $r = null;

        //object passed: get Email from Email Ref
        if ($class instanceof Model && $class->loaded()) {
            if ($email_id === null) {
                $r = $this->_addRecipientObject($class);
            } elseif ($email_id) {
                $r = $this->_addRecipientObject($class, $email_id);
            }
        } //id passed: ID of Email Address, load from there
        elseif (is_numeric($class)) {
            $r = $this->_addRecipientByEmailId(intval($class));
        } //else assume its email as string, not belonging to a stored model
        elseif (is_string($class) && filter_var($class, FILTER_VALIDATE_EMAIL)) {
            $r = $this->ref('email_recipient');
            $r->set('email', $class);
        }

        if (!$r instanceof EmailRecipient) {
            return false;
        }

        //if $this is not saved yet do so, so we can use $this->id for recipient
        if (!$this->get('id')) {
            $this->save();
        }

        //if email already exists, skip
        foreach ($this->ref('email_recipient') as $rec) {
            if ($rec->get('email') == $r->get('email')) {
                return false;
            }
        }

        $r->save();

        return true;
    }


    /*
     * loads model_class, model_id, firstname and lastname from a passed object
     * returns an EmailRecipient object
     */
    protected function _addRecipientObject(Model $object, $email_id = null): ?EmailRecipient
    {
        $r = $this->ref('email_recipient');
        //set firstname and lastname if available
        $r->set('firstname', $object->hasField('firstname') ? $object->get('firstname') : '');
        $r->set('lastname', $object->hasField('lastname') ? $object->get('lastname') : '');
        $r->set('model_class', get_class($object));
        $r->set('model_id', $object->get($object->id_field));

        //go for first email if no email_id was specified
        if (
            $email_id == null
            && method_exists($object, 'getFirstSecondaryModelRecord')
        ) {
            $emailObject = $object->getFirstSecondaryModelRecord($this->emailAddressClassName);
            if (
                $emailObject
                && filter_var($emailObject->get('value'), FILTER_VALIDATE_EMAIL)
            ) {
                $r->set('email', $emailObject->get('value'));
                return clone $r;
            }
        } //else go for specified email id
        elseif ($email_id) {
            $emailObject = new $this->emailAddressClassName($this->persistence);
            $emailObject->tryLoad($email_id);
            if ($emailObject->loaded()) {
                $r->set('email', $emailObject->get('value'));
                return clone $r;
            }
        }

        return null;
    }


    /*
     * add a recipient by a specified Email id
     */
    protected function _addRecipientByEmailId(int $id): ?EmailRecipient
    {
        $e = new Email($this->persistence);
        $e->tryLoad($id);
        if (!$e->loaded()) {
            return null;
        }

        if ($parent = $e->getParentObject()) {
            return $this->_addRecipientObject($parent);
        }

        return null;
    }


    /*
     * Removes an object from recipient array
     */
    public function removeRecipient($id): bool
    {
        foreach ($this->ref('email_recipient') as $r) {
            if ($r->get('id') == $id) {
                $r->delete();
                return true;
            }
        }

        return false;
    }


    /*
     *  adds a file object to the attachment array.
     *
     * @param object
     */
    public function addAttachment($id)
    {
        $a = $this->get('attachments');
        $a[] = $id;
        $this->set('attachments', $a);
    }


    /*
     * removes an attachment from the attachment array
     *
     * @param int
     */
    public function removeAttachment($id)
    {
        $a = $this->get('attachments');
        if (in_array($id, $a)) {
            unset($a[array_search($id, $a)]);
        }

        $this->set('attachments', $a);
    }


    /*
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
}
