<?php

namespace mdm\behaviors\ar;

use Yii;
use yii\helpers\ArrayHelper;

/**
 * RelatedBehavior
 * Use to save relation 
 *
 * @property \yii\db\ActiveRecord $owner
 * @property array $relatedErrors
 * 
 * @author Misbahul D Munir <misbahuldmunir@gmail.com>
 * @since 1.0
 */
class RelatedBehavior extends \yii\base\Behavior
{
    /**
     * @var array 
     */
    public $extraData;

    /**
     * @var Closure Execute before relation validate
     * 
     * ```php
     * function($model, $index){
     *     // for hasOne relation, value or $index is null
     * }
     * ```
     */
    public $beforeRValidate;

    /**
     * @var Closure Execute before relation save
     * @see [[$beforeRValidate]]
     * If function return `false`, save will be canceled
     */
    public $beforeRSave;

    /**
     * @var Closure Execute after relation save
     * @see [[$beforeRValidate]]
     */
    public $afterRSave;

    /**
     * @var boolean If true clear related error
     */
    public $clearError = true;

    /**
     * @var array 
     */
    protected $_relatedErrors = [];

    /**
     * Save related model(s) provided by `$data`.
     * @param  string $relationName
     * @param  array $data
     * @param  boolean $saved if false, related model only be validated without saved.
     * @param  boolean|string $scope
     * @param  string   $scenario
     * @return boolean true if success
     */
    public function saveRelated($relationName, $data, $saved = true, $scope = null, $scenario = null)
    {
        return $this->doSaveRelated($relationName, $data, $saved, $scope, $scenario);
    }

    /**
     * @see [[saveRelated()]]
     */
    protected function doSaveRelated($relationName, $data, $save, $scope, $scenario)
    {
        $model = $this->owner;
        $relation = $model->getRelation($relationName);

        // link of relation
        $links = [];
        foreach ($relation->link as $from => $to) {
            $links[$from] = $model[$to];
        }

        /* @var $class \yii\db\ActiveRecord */
        $class = $relation->modelClass;
        $multiple = $relation->multiple;
        
        /* @var $children \yii\db\ActiveRecord[] */
        $children = $model->$relationName;
        $uniqueKeys = array_flip($class::primaryKey());
        foreach ($relation->link as $from => $to) {
            unset($uniqueKeys[$from]);
        }
        $uniqueKeys = array_keys($uniqueKeys);

        if ($scope === null) {
            $postDetails = ArrayHelper::getValue($data, (new $class)->formName(), []);
        } elseif ($scope === false) {
            $postDetails = $data;
        } else {
            $postDetails = ArrayHelper::getValue($data, $scope, []);
        }

        if ($this->clearError) {
            $this->_relatedErrors[$relationName] = [];
        }
        /* @var $detail \yii\db\ActiveRecord */
        $error = false;
        if ($multiple) {
            $population = [];
            foreach ($postDetails as $index => $dataDetail) {
                if ($this->extraData !== null) {
                    $dataDetail = array_merge($this->extraData, $dataDetail);
                }
                $dataDetail = array_merge($dataDetail, $links);

                $detail = null;
                // get from current relation
                // if has child with same primary key, use this
                if (empty($relation->indexBy)) {
                    foreach ($children as $i => $child) {
                        if ($this->checkEqual($child, $dataDetail, $uniqueKeys)) {
                            $detail = $child;
                            unset($children[$i]);
                            break;
                        }
                    }
                } elseif (isset($children[$index])) {
                    $detail = $children[$index];
                    unset($children[$index]);
                }
                if ($detail === null) {
                    $detail = new $class;
                }
                if ($scenario !== null) {
                    $detail->setScenario($scenario);
                }
                $detail->load($dataDetail, '');

                if (isset($this->beforeRValidate)) {
                    call_user_func($this->beforeRValidate, $detail, $index);
                }
                if (!$detail->validate()) {
                    $this->_relatedErrors[$relationName][$index] = $detail->getFirstErrors();
                    $error = true;
                }
                $population[$index] = $detail;
            }
//            \yii\helpers\VarDumper::dump($population, 10, true);
//            die();
        } else {
            /* @var $population \yii\db\ActiveRecord */
            $population = $children === null ? new $class : $children;
            $dataDetail = $postDetails;
            if (isset($this->extraData)) {
                $dataDetail = array_merge($this->extraData, $dataDetail);
            }
            $dataDetail = array_merge($dataDetail, $links);
            if ($scenario !== null) {
                $population->setScenario($scenario);
            }
            $population->load($dataDetail, '');
            if (isset($this->beforeRValidate)) {
                call_user_func($this->beforeRValidate, $population, null);
            }
            if (!$population->validate()) {
                $this->_relatedErrors[$relationName] = $population->getFirstErrors();
                $error = true;
            }
        }

        if (!$error && $save) {
            if ($multiple) {
                // delete current children before inserting new
                $linkFilter = [];
                foreach ($relation->link as $from => $to) {
                    $linkFilter[$from] = $model->$to;
                }
                $values = [];
                if (!empty($uniqueKeys)) {
                    foreach ($children as $child) {
                        $value = [];
                        foreach ($uniqueKeys as $column) {
                            $value[$column] = $child[$column];
                        }
                        $values[] = $value;
                    }
                    if (!empty($values)) {
                        foreach ($class::find()->andWhere(['and', $linkFilter, ['in', $uniqueKeys, $values]])->all() as $related) {
                            $related->delete();
                        }
                    }
                } else {
                    foreach ($class::find()->andWhere($linkFilter)->all() as $related) {
                        $related->delete();
                    }
                }
                foreach ($population as $index => $detail) {
                    if (!isset($this->beforeRSave) || call_user_func($this->beforeRSave, $detail, $index) !== false) {
                        if (!$detail->save(false)) {
                            $error = true;
                            break;
                        }
                        if (isset($this->afterRSave)) {
                            call_user_func($this->afterRSave, $detail, $index);
                        }
                    }
                }
            } else {
                if (!isset($this->beforeRSave) || call_user_func($this->beforeRSave, $population, null) !== false) {
                    if ($population->save(false)) {
                        if (isset($this->beforeRSave)) {
                            call_user_func($this->beforeRSave, $population, null);
                        }
                    } else {
                        $error = true;
                    }
                }
            }
        }

        $model->populateRelation($relationName, $population);

        return !$error && $save;
    }

    /**
     * Check if relation has error.
     * @param  string  $relationName
     * @return boolean
     */
    public function hasRelatedErrors($relationName = null)
    {
        if ($relationName === null) {
            foreach ($this->_relatedErrors as $errors) {
                if (!empty($errors)) {
                    return true;
                }
            }
            return false;
        } else {
            return !empty($this->_relatedErrors[$relationName]);
        }
    }

    /**
     * Get related error(s)
     * @param string|null $relationName
     * @return array
     */
    public function getRelatedErrors($relationName = null)
    {
        if ($relationName === null) {
            return $this->_relatedErrors;
        } else {
            return isset($this->_relatedErrors[$relationName]) ? $this->_relatedErrors[$relationName] : [];
        }
    }

    protected function checkEqual($model1, $model2, $keys)
    {
        foreach ($keys as $key) {
            if ($model1[$key] != $model2[$key]) {
                return false;
            }
        }
        return true;
    }
}