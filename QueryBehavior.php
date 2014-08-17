<?php

namespace mdm\behaviors\ar;

use yii\db\ActiveQuery;

/**
 * Description of QueryBehavior
 *
 * @property ActiveQuery $owner
 * @author Misbahul D Munir (mdmunir) <misbahuldmunir@gmail.com>
 */
class QueryBehavior extends \yii\base\Behavior
{

    public function events()
    {
        return [
            ActiveQuery::EVENT_INIT => 'applyDefaultScope'
        ];
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

    public function applyDefaultScope()
    {
        if ($this->hasMethod('defaultScope')) {
            call_user_func([$this->owner->modelClass, $name], $this->owner);
        }
    }
}