<?php

namespace mdm\behaviors\ar;

use yii\base\Event;

/**
 * Description of RelationEvent
 *
 * @author Misbahul D Munir <misbahuldmunir@gmail.com>
 * @since 1.4
 */
class RelationEvent extends Event
{
    const BEFORE_VALIDATE = 'beforeRelationValidate';
    const BEFORE_SAVE = 'beforeRelationSave';
    const AFTER_SAVE = 'afterRelationSave';

    public $isValid = true;
    public $child;
    public $relation;
    public $index;
}
