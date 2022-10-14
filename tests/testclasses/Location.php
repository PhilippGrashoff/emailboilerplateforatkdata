<?php declare(strict_types=1);

namespace predefinedemailsforatk\tests\testclasses;

use Atk4\Data\Model;
use predefinedemailsforatk\SentEmail;
use secondarymodelforatk\SecondaryModelRelationTrait;

class Location extends Model
{

    use SecondaryModelRelationTrait;

    public $table = 'location';

    protected function init(): void
    {
        parent::init();

        $this->addField('name');
        $this->addField('address', ['type' => 'text']);

        $this->hasMany(Event::class, ['model' => [Event::class]]);
        $this->addSecondaryModelHasMany(SentEmail::class);
    }
}