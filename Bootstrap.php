<?php

namespace mdm\behaviors\ar;

use Yii;
use yii\helpers\ArrayHelper;
use yii\validators\Validator;

/**
 * Description of Bootstrap
 *
 * @author Misbahul D Munir <misbahuldmunir@gmail.com>
 * @since 1.0
 */
class Bootstrap implements \yii\base\BootstrapInterface
{

    /**
     * @inheritdoc
     */
    public function bootstrap($app)
    {
        if (ArrayHelper::getValue($app->params, 'mdm.behaviors.ar.scope', true)) {
            Yii::$container->set('yii\db\ActiveQuery', [
                'as scope' => 'mdm\behaviors\ar\QueryScopeBehavior'
            ]);
        }

        Validator::$builtInValidators['relationUnique'] = 'mdm\behaviors\ar\RelationUniqueValidator';
    }
}
