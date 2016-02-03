<?php

namespace mdm\behaviors\ar;

/**
 * Description of RelationUniqueValidator
 *
 * @author Misbahul D Munir <misbahuldmunir@gmail.com>
 * @since 1.0
 */
class RelationUniqueValidator extends \yii\validators\Validator
{
    public $targetAttributes;
    public $checkNull = false;

    /**
     * @inheritdoc
     */
    public function validateAttribute($model, $attribute)
    {
        if ($this->targetAttributes === null) {
            $relation = $model->getRelation($attribute);
            $class = $relation->modelClass;
            $targetAttributes = $class::primaryKey();
        } else {
            $targetAttributes = $this->targetAttributes;
        }

        $values = [];
        $related = $model->$attribute;
        if (!empty($related)) {

            if (is_array($targetAttributes)) {
                foreach ($related as $child) {
                    $m = [];
                    foreach ($targetAttributes as $attr) {
                        $m[$attr] = $child[$attr];
                    }
                    $m = md5(serialize($m));
                    if (isset($values[$m])) {
                        $model->addError($attribute, 'Relation not unique');
                        return;
                    }
                    $values[$m] = true;
                }
            } else {
                foreach ($related as $child) {
                    $m = $child[$targetAttributes];
                    if ((isset($m) || $this->checkNull) && isset($values[$m])) {
                        $model->addError($attribute, 'Relation not unique');
                        return;
                    }
                    $values[$m] = true;
                }
            }
        }
    }
}
