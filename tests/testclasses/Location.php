<?php declare(strict_types=1);

namespace emailboilerplateforatkdata\tests\testclasses;

use Atk4\Data\Model;

class Location extends Model
{

    public $table = 'location';

    protected function init(): void
    {
        parent::init();

        $this->addField('name');
        $this->addField('address', ['type' => 'text']);

        $this->hasMany(Event::class, ['model' => [Event::class]]);
    }
}