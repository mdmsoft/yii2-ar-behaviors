<?php

namespace mdm\behaviors\ar;

/**
 * Description of QueryBehavior
 *
 * @property \yii\db\ActiveQuery $owner
 * @author Misbahul D Munir (mdmunir) <misbahuldmunir@gmail.com>
 */
class QueryBehavior extends \yii\base\Behavior
{

    public function __call($name, $params)
    {
        array_unshift($params, $this->owner);
        call_user_func_array([$this->owner->modelClass, $name], $params);

        return $this->owner;
    }

    public function hasMethod($name)
    {
        if (method_exists($this->owner->modelClass, $name)) {
            $ref = new \ReflectionMethod($this->owner->modelClass, $name);

            return $ref->isStatic() && count($ref->getParameters()) >= 1;
        }

        return false;
    }
}
