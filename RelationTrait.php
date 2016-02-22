<?php

namespace mdm\behaviors\ar;

use yii\db\ActiveRecord;
use yii\helpers\ArrayHelper;

/**
 * Description of RelationTrait
 *
 * @property array $relatedErrors
 * 
 * @author Misbahul D Munir <misbahuldmunir@gmail.com>
 * @since 1.0
 */
trait RelationTrait
{
    /**
     * @var array
     */
    private $_old_relations = [];

    /**
     * @var array
     */
    private $_original_relations = [];

    /**
     * @var array
     */
    private $_process_relation = [];

    /**
     * @var array
     */
    private $_relatedErrors = [];

    public function afterValidate()
    {
        $this->doAfterValidate();
        parent::afterValidate();
    }

    public function afterSave($insert, $changedAttributes)
    {
        $this->doAfterSave();
        parent::afterSave($insert, $changedAttributes);
    }

    public function getRelatedErrors()
    {
        return $this->_relatedErrors;
    }

    /**
     * Populate relation
     * @param string $name
     * @param array||ActiveRecord||ActiveRecord[] $values
     * @return boolean
     */
    public function loadRelated($name, $values)
    {
        $relation = $this->getRelation($name, false);
        if ($relation === null) {
            return false;
        }
        $class = $relation->modelClass;
        $multiple = $relation->multiple;
        $link = $relation->link;
        $uniqueKeys = array_flip($class::primaryKey());
        foreach (array_keys($link) as $from) {
            unset($uniqueKeys[$from]);
        }
        $uniqueKeys = array_keys($uniqueKeys);
        if (isset($this->_original_relations[$name])) {
            $children = $this->_original_relations[$name];
        } else {
            $this->_original_relations[$name] = $children = $this->$name;
        }

        if ($multiple) {
            $newChildren = [];
            $values = $values? : [];
            foreach ($values as $index => $value) {
                // get from current relation
                // if has child with same primary key, use this
                /* @var $newChild \yii\db\ActiveRecord */
                $newChild = null;
                if (empty($relation->indexBy)) {
                    foreach ($children as $i => $child) {
                        if ($this->childIsEqual($child, $value, $uniqueKeys)) {
                            if ($value instanceof $class) {
                                $newChild = $value;
                                $newChild->isNewRecord = $child->isNewRecord;
                                $newChild->oldAttributes = $child->oldAttributes;
                            } else {
                                $newChild = $child;
                            }
                            unset($children[$i]);
                            break;
                        }
                    }
                } elseif (isset($children[$index])) {
                    $child = $children[$index];
                    if ($value instanceof $class) {
                        $newChild = $value;
                        $newChild->isNewRecord = $child->isNewRecord;
                        $newChild->oldAttributes = $child->oldAttributes;
                    } else {
                        $newChild = $child;
                    }
                    unset($children[$index]);
                }
                if ($newChild === null) {
                    $newChild = $value instanceof $class ? $value : new $class;
                }
                if (isset($this->relatedScenarios, $this->relatedScenarios[$name])) {
                    $newChild->scenario = $this->relatedScenarios[$name];
                }
                if (!$value instanceof $class) {
                    $newChild->load($value, '');
                }
                foreach ($link as $from => $to) {
                    $newChild->$from = $this->$to;
                }
                $newChildren[$index] = $newChild;
            }
            $this->_old_relations[$name] = $children;
            $this->populateRelation($name, $newChildren);
            $this->_process_relation[$name] = true;
        } else {
            $newChild = null;
            if ($children === null) {
                if ($values !== null) {
                    $newChild = $values instanceof $class ? $values : new $class;
                    $this->_process_relation[$name] = true;
                }
            } else {
                if ($values !== null) {
                    $newChild = $values instanceof $class ? $values : $children;
                    if ($values instanceof $class) {
                        $newChild = $values;
                        $newChild->oldAttributes = $children->oldAttributes;
                        $newChild->isNewRecord = $children->isNewRecord;
                    } else {
                        $newChild = $children;
                    }
                } else {
                    $this->_old_relations[$name] = [$children];
                }
                $this->_process_relation[$name] = true;
            }
            if ($newChild !== null) {
                if (isset($this->relatedScenarios[$name])) {
                    $newChild->scenario = $this->relatedScenarios[$name];
                }
                if (!$values instanceof $class) {
                    $newChild->load($values, '');
                }
                foreach ($link as $from => $to) {
                    $newChild->$from = $this->$to;
                }
            }
            $this->populateRelation($name, $newChild);
        }
        return true;
    }

    /**
     * Handler for event afterValidate
     */
    protected function doAfterValidate()
    {
        $handleValidate = method_exists($this, 'beforeRValidate');
        /* @var $child ActiveRecord */
        foreach ($this->_process_relation as $name => $process) {
            if (!$process) {
                continue;
            }
            if (!isset($this->clearError) || $this->clearError) {
                $this->_relatedErrors[$name] = [];
            }
            $error = false;
            $relation = $this->getRelation($name);
            $children = $this->$name;
            if ($relation->multiple) {
                foreach ($children as $index => $child) {
                    if ($handleValidate) {
                        $this->beforeRValidate($child, $index, $name);
                    }
                    if (!$child->validate()) {
                        $errors = $this->_relatedErrors[$name][$index] = $child->getFirstErrors();
                        $this->addError($name, "{$index}: " . reset($errors));
                        $error = true;
                    }
                }
            } else {
                if ($handleValidate) {
                    $this->beforeRValidate($children, null, $name);
                }
                if (!$children->validate()) {
                    $errors = $this->_relatedErrors[$name] = $child->getFirstErrors();
                    $this->addError($name, reset($errors));
                    $error = true;
                }
            }
            if ($error) {
                $this->addError($name, 'Related error');
            }
        }
    }

    /**
     * Handler for event afterSave
     */
    protected function doAfterSave()
    {
        $handleBefore = method_exists($this, 'beforeRSave');
        $handleAfter = method_exists($this, 'afterRSave');
        $deleteUnsaved = isset($this->deleteUnsaved) ? $this->deleteUnsaved : true;

        foreach ($this->_process_relation as $name => $process) {
            if (!$process) {
                continue;
            }
            $delUnsaved = is_array($deleteUnsaved) ? (isset($deleteUnsaved[$name]) ? $deleteUnsaved[$name] : true) : $deleteUnsaved;
            // delete old related
            /* @var $child ActiveRecord */
            if (isset($this->_old_relations[$name])) {
                foreach ($this->_old_relations[$name] as $child) {
                    $child->delete();
                }
                unset($this->_old_relations[$name]);
            }
            // save new relation
            $relation = $this->getRelation($name);
            $link = $relation->link;
            $children = $this->$name;
            if ($relation->multiple) {
                foreach ($children as $index => $child) {
                    foreach ($link as $from => $to) {
                        $child->$from = $this->$to;
                    }
                    if ($handleBefore === false || $this->beforeRSave($child, $index, $name) !== false) {
                        $child->save(false);
                        if ($handleAfter) {
                            $this->afterRSave($child, $index, $name);
                        }
                    } elseif ($delUnsaved && !$child->getIsNewRecord()) {
                        $child->delete();
                    }
                }
            } else {
                /* @var $children \yii\db\ActiveRecord */
                if ($children !== null) {
                    foreach ($link as $from => $to) {
                        $children->$from = $this->$to;
                    }
                    if ($handleBefore === false || $this->beforeRSave($children, null, $name) !== false) {
                        $children->save(false);
                        if ($handleAfter) {
                            $this->afterRSave($children, null, $name);
                        } elseif ($delUnsaved && !$children->getIsNewRecord()) {
                            $child->delete();
                        }
                    }
                }
            }
            unset($this->_process_relation[$name], $this->_original_relations[$name]);
        }
    }

    /**
     * Check is boot of model is equal
     * @param \yii\db\ActiveRecord|array $model1
     * @param \yii\db\ActiveRecord|array $model2
     * @param array $keys
     * @return boolean
     */
    protected function childIsEqual($model1, $model2, $keys)
    {
        if (method_exists($this, 'isEqual')) {
            return $this->isEqual($model1, $model2, $keys);
        }
        if (empty($keys)) {
            return false;
        }
        foreach ($keys as $key) {
            if (ArrayHelper::getValue($model1, $key) != ArrayHelper::getValue($model2, $key)) {
                return false;
            }
        }
        return true;
    }
}
