<?php

namespace mdm\behaviors\ar;

use yii\db\ActiveQuery;

/**
 * Description of QueryBehavior
 *
 * @property ActiveQuery $owner
 * 
 * @author Misbahul D Munir <misbahuldmunir@gmail.com>
 * @since 1.0
 */
class QueryScopeBehavior extends \yii\base\Behavior
{

    public function attach($owner)
    {
        parent::attach($owner);
        if ($this->hasMethod('defaultScope')) {
            call_user_func([$owner->modelClass, 'defaultScope'], $owner);
        }
    }

    public function __call($name, $params)
    {
        array_unshift($params, $this->owner);
        call_user_func_array([$this->owner->modelClass, $name], $params);

        return $this->owner;
    }

    public function hasMethod($name)
    {
        if ($this->owner->modelClass && method_exists($this->owner->modelClass, $name)) {
            $ref = new \ReflectionMethod($this->owner->modelClass, $name);

            return $ref->isStatic() && count($ref->getParameters()) >= 1;
        }

        return false;
    }
}