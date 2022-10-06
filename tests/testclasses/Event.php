<?php declare(strict_types=1);

namespace emailboilerplateforatkdata\tests\testclasses;

use Atk4\Data\Model;

class Event extends Model
{

    public $table = 'event';

    protected function init(): void
    {
        parent::init();

        $this->addField('name');
        $this->addField('date', ['type' => 'date']);

        $this->hasOne('location_id', ['model' => [Location::class]]);
    }
}