<?php declare(strict_types=1);

namespace emailboilerplateforatkdata;

use Atk4\Data\Model;
use DateTime;
use secondarymodelforatk\SecondaryModel;

class SentEmail extends SecondaryModel
{

    public $table = 'sent_email';

    protected function init(): void
    {
        parent::init();

        $this->addField(
            'sent_date',
            [
                'type' => 'datetime',
                'persist_timezone' => 'Europe/Berlin'
            ]
        );

        $this->setOrder(['sent_date' => 'desc']);

        $this->onHook(
            Model::HOOK_BEFORE_SAVE,
            function (self $model, $isUpdate) {
                if (!$isUpdate) {
                    $model->set('sent_date', new DateTime());
                }
            }
        );
    }
}
