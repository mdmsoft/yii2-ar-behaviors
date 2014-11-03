<?php

namespace mdm\behaviors\ar;

use Yii;
use yii\helpers\ArrayHelper;

/**
 * Description of Bootstrap
 *
 * @author Misbahul D Munir <misbahuldmunir@gmail.com>
 * @since 1.0
 */
class Bootstrap implements \yii\base\BootstrapInterface
{

    /**
     *
     * @param \yii\base\Application $app
     */
    public function bootstrap($app)
    {
        if (ArrayHelper::getValue($app->params, 'mdm.behaviors.ar.scope', true)) {
            Yii::$container->set('yii\db\ActiveQuery', [
                'as scope' => 'mdm\behaviors\ar\QueryScopeBehavior'
            ]);
        }
    }
}
