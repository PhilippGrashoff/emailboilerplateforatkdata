<?php declare(strict_types=1);

namespace emailboilerplateforatkdata;

use Atk4\Data\Persistence;
use Atk4\Ui\HtmlTemplate;
use DirectoryIterator;
use Throwable;

class EmailTemplateLoader
{

    public static function getEmailTemplate(BaseEmail $baseEmail): HtmlTemplate
    {
        //try load Template for the individual $baseEmail->model entity
        $result = self::tryLoadTemplateForEntity($baseEmail);
        if ($result) {
            return $result;
        }
        //try load existing Template from persistence
        $result = self::tryLoadTemplateFromPersistence($baseEmail);
        if ($result) {
            return $result;
        }
        //load default template from file if other methods did not return anything
        return self::loadDefaultTemplateFromFile($baseEmail);
    }

    protected static function tryLoadTemplateForEntity(BaseEmail $baseEmail): ?HtmlTemplate
    {
        //no model or not loaded?
        if (!$baseEmail->model || !$baseEmail->model->loaded()) {
            return null;
        }

        return self::customLoadTemplateForEntity($baseEmail);
    }

    //overwrite for custom implementations
    protected static function customLoadTemplateForEntity(BaseEmail $baseEmail): ?HtmlTemplate
    {
        return null;
    }

    protected static function tryLoadTemplateFromPersistence(BaseEmail $baseEmail): ?HtmlTemplate
    {
        $emailTemplate = new EmailTemplate($baseEmail->persistence);
        $emailTemplate->addCondition('model_class', '=', null);
        $emailTemplate->addCondition('model_id', '=', null);
        $emailTemplate->tryLoadBy('ident', (new \ReflectionClass($baseEmail))->getName());
        if (!$emailTemplate->loaded()) {
            return null;
        }
        $htmlTemplate = new HtmlTemplate($emailTemplate->get('value'));
        return $htmlTemplate;
    }

    protected static function loadDefaultTemplateFromFile(BaseEmail $baseEmail): HtmlTemplate
    {
        //now try to load from file
        $fileName = self::getTemplateFilePath($baseEmail);
        //throws Exception if file can not be found
        $htmlTemplate = new HtmlTemplate();
        $htmlTemplate->loadFromFile($fileName);
        return $htmlTemplate;
    }
    protected static function loadRawDefaultTemplateFromFile(BaseEmail $baseEmail): string
    {
        //now try to load from file
        $fileName = self::getTemplateFilePath($baseEmail);
        return file_get_contents($fileName);
    }

    //overwrite in custom implementations to easily define where default templates can be found
    protected static function getTemplateFilePath(BaseEmail $baseEmail): string
    {
        return $baseEmail->defaultTemplateFile;
    }

    public static function createEmailTemplateEntities(array $dirs, Persistence $persistence): void
    {
        foreach (self:: getAllBaseEmailImplementations($dirs, $persistence) as $className => $baseEmail) {
            $emailTemplate = new EmailTemplate($persistence);
            $emailTemplate->addCondition('model_class', null);
            $emailTemplate->addCondition('model_id', null);
            $emailTemplate->tryLoadBy('ident', (new \ReflectionClass($baseEmail))->getName());
            if (!$emailTemplate->loaded()) {
                $emailTemplate->set('ident', $baseEmail->template);
                $emailTemplate->set('value', self::loadRawDefaultTemplateFromFile($baseEmail));
                $emailTemplate->save();
            }
        }
    }

    /**
     * return an instance of each found implementation of BaseEmail in the given folder(s)
     * parameter array: key is the dir to check for classes, value is the namespace
     */
    protected static function getAllBaseEmailImplementations(array $dirs, Persistence $persistence): array
    {
        $result = [];

        foreach ($dirs as $dir => $namespace) {
            foreach (new DirectoryIterator($dir) as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }
                $className = $namespace . $file->getBasename('.php');
                if (!class_exists($className)) {
                    continue;
                }
                try {
                    $instance = new $className($persistence);
                } catch (Throwable $e) {
                    continue;
                }
                if (!$instance instanceof BaseEmail) {
                    continue;
                }

                $result[$className] = clone $instance;
            }
        }

        return $result;
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
