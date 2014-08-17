<?php

namespace mdm\behaviors\ar;

use Yii;
use yii\helpers\ArrayHelper;

/**
 * Description of RelationBehavior
 *
 * @property \yii\db\ActiveRecord $owner Description
 * @author Misbahul D Munir (mdmunir) <misbahuldmunir@gmail.com>
 */
class RelationBehavior extends \yii\base\Behavior
{

    /**
     * 
     * @param string $relations
     * @param array $data
     * @param array $options
     * @return boolean Description
     */
    public function saveRelation($relations, $data, $options = [])
    {
        $model = $this->owner;
        if ($model->load($data)) {
            $error = !$model->save();
            if (!is_array($relations)) {
                $relations = preg_split('/\s*,\s*/', trim($relations), -1, PREG_SPLIT_NO_EMPTY);
            }
            foreach ($relations as $relationName) {
                $error = $this->doSaveRelation($model, $relationName, $data, $options) || $error;
            }
            return $error ? -1 : 1;
        } else {
            return false;
        }
    }

    protected function doSaveRelation($model, $relationName, $data, $options)
    {
        $relation = $model->getRelation($relationName);

        // link of relation
        $links = [];
        foreach ($relation->link as $from => $to) {
            $links[$from] = $model[$to];
        }

        /* @var $class \yii\db\ActiveRecord */
        $class = $relation->modelClass;
        $multiple = $relation->multiple;
        if ($multiple) {
            $children = $relation->all();
        } else {
            $children = $relation->one();
        }

        $formName = (new $class)->formName();
        $postDetails = ArrayHelper::getValue($data, $formName, []);

        /* @var $detail \yii\db\ActiveRecord */
        $error = false;
        if ($multiple) {
            $population = [];
            foreach ($postDetails as $index => $dataDetail) {
                if (isset($options['extra'])) {
                    $dataDetail = array_merge($options['extra'], $dataDetail);
                }
                $dataDetail = array_merge($dataDetail, $links);

                // primary keys of detail
                $detailPks = [];
                $pks = $class::primaryKey();
                if (count($pks) === 1) {
                    $detailPks = isset($dataDetail[$pks[0]]) ? $dataDetail[$pks[0]] : null;
                } else {
                    foreach ($pks as $pkName) {
                        $detailPks[$pkName] = isset($dataDetail[$pkName]) ? $dataDetail[$pkName] : null;
                    }
                }

                $detail = null;
                // get from current relation
                if (empty($relation->indexBy)) {
                    foreach ($children as $i => $child) {
                        if ($child->getPrimaryKey() === $detailPks) {
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

                $detail->load($dataDetail, '');

                if (isset($options['beforeValidate'])) {
                    call_user_func($options['beforeValidate'], $detail, $index);
                }
                $error = !$detail->validate() || $error;
                $population[$index] = $detail;
            }
        } else {
            $population = $children === null ? new $class : $children;
            $dataDetail = $postDetails;
            if (isset($options['extra'])) {
                $dataDetail = array_merge($options['extra'], $dataDetail);
            }
            $dataDetail = array_merge($dataDetail, $links);
            $population->load($dataDetail, '');
            if (isset($options['beforeValidate'])) {
                call_user_func($options['beforeValidate'], $population, null);
            }
            $error = !$population->validate() || $error;
        }

        if (!$error) {
            // delete current children before inserting new
            if ($multiple) {
                $linkFilter = [];
                $columns = array_flip($class::primaryKey());
                foreach ($relation->link as $from => $to) {
                    $linkFilter[$from] = $model->$to;
                    if (isset($columns[$from])) {
                        unset($columns[$from]);
                    }
                }
                $values = [];
                if (!empty($columns)) {
                    $columns = array_keys($columns);
                    foreach ($children as $child) {
                        $value = [];
                        foreach ($columns as $column) {
                            $value[$column] = $child[$column];
                        }
                        $values[] = $value;
                    }
                    if (!empty($values)) {
                        $class::deleteAll(['and', $linkFilter, ['in', $columns, $values]]);
                    }
                } else {
                    $class::deleteAll($linkFilter);
                }
                foreach ($population as $index => $detail) {
                    if (isset($options['beforeSave'])) {
                        call_user_func($options['beforeSave'], $detail, $index);
                    }
                    $detail->save(false);
                    if (isset($options['afterSave'])) {
                        call_user_func($options['afterSave'], $detail, $index);
                    }
                }
            } else {
                if (isset($options['beforeSave'])) {
                    call_user_func($options['beforeSave'], $population, null);
                }
                $population->save(false);
                if (isset($options['afterSave'])) {
                    call_user_func($options['afterSave'], $population, null);
                }
            }
        }

        $model->populateRelation($relationName, $population);
        return $error;
    }
}
