<?php declare(strict_types=1);

namespace emailboilerplateforatkdata;

use Atk4\Data\Exception;
use Atk4\Ui\HtmlTemplate;

class EmailTemplateLoader
{

    public static function getEmailTemplate(BaseEmail $baseEmail): HtmlTemplate
    {

    }

    public function loadEmailTemplate(string $name, bool $raw_template = false, array $customFromModels = [])
    {
        $template = new Template();
        $template->app = $this;
        //try to load From EmailTemplate per Model
        $et = $this->_getCustomEmailTemplateFromModel($name, $customFromModels);
        //else try to load from DB
        if (!$et) {
            $et = new EmailTemplate($this->db);
            $et->addCondition('model_class', '=', null);
            $et->addCondition('model_id', '=', null);
            $et->tryLoadBy('ident', $name);
        }

        if ($et->loaded()) {
            if ($raw_template) {
                return $et->get('value');
            } else {
                $template->loadTemplateFromString((string)$et->get('value'));
                return $template;
            }
        }

        //now try to load from file
        $fileName = FILE_BASE_PATH . $this->emailTemplateDir . '/' . $name;
        if (file_exists($fileName)) {
            if ($raw_template) {
                return file_get_contents($fileName);
            } elseif ($t = $template->tryLoad($fileName)) {
                return $t;
            }
        }

        throw new Exception('Can not find email template file: ' . $name);
    }

    protected function _getCustomEmailTemplateFromModel(string $name, array $customFromModels): ?EmailTemplate
    {
        foreach ($customFromModels as $model) {
            if (!$model->loaded()) {
                throw new Exception('Model needs to be loaded in ' . __FUNCTION__);
            }
            $et = new EmailTemplate($this->db);
            $et->addCondition('model_class', get_class($model));
            $et->addCondition('model_id', $model->get('id'));
            $et->tryLoadBy('ident', $name);
            if ($et->loaded()) {
                return clone $et;
            }
        }

        return null;
    }

    public function saveEmailTemplate(
        string $ident,
        string $value,
        string $model_class = '',
        $model_id = null
    ): EmailTemplate {
        $emailTemplate = new EmailTemplate($this->db);
        if ($model_class && $model_id) {
            $emailTemplate->addCondition('model_class', $model_class);
            $emailTemplate->addCondition('model_id', $model_id);
        }
        $emailTemplate->tryLoadBy('ident', $ident);
        if (!$emailTemplate->loaded()) {
            $emailTemplate->set('ident', $ident);
        }
        $emailTemplate->set('value', $value);
        if ($model_class && $model_id) {
            $emailTemplate->set('model_class', $model_class);
            $emailTemplate->set('model_id', $model_id);
        }
        $emailTemplate->save();

        return $emailTemplate;
    }


}
