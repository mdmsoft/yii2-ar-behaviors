<?php

namespace mdm\behaviors\ar;

use Yii;
use yii\validators\Validator;

/**
 * Description of RelationUniqueValidator
 *
 * @author Misbahul D Munir <misbahuldmunir@gmail.com>
 * @since 1.0
 */
class RelationUniqueValidator extends Validator
{
    /**
     * @var string|array the name of the ActiveRecord attribute that should be used to
     * validate the uniqueness of the current attribute value. If not set, it will use the name
     * of the attribute currently being validated. You may use an array to validate the uniqueness
     * of multiple columns at the same time. The array values are the attributes that will be
     * used to validate the uniqueness, while the array keys are the attributes whose values are to be validated.
     * If the key and the value are the same, you can just specify the value.
     */
    public $targetAttributes;

    /**
     *
     * @var boolean
     */
    public $checkNull = false;

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        if ($this->message === null) {
            $this->message = Yii::t('yii', '{attribute} not unique.');
        }
    }

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
                        $this->addError($model, $attribute, $this->message);
                        return;
                    }
                    $values[$m] = true;
                }
            } else {
                foreach ($related as $child) {
                    $m = $child[$targetAttributes];
                    if ((isset($m) || $this->checkNull) && isset($values[$m])) {
                        $this->addError($model, $attribute, $this->message);
                        return;
                    }
                    $values[$m] = true;
                }
            }
        }
    }
}
