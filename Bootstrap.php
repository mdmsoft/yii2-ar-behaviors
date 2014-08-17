<?php

namespace mdm\behaviors\ar;

/**
 * Description of Bootstrap
 *
 * @author Misbahul D Munir (mdmunir) <misbahuldmunir@gmail.com>
 */
class Bootstrap implements \yii\base\BootstrapInterface
{

    /**
     *
     * @param \yii\base\Application $app
     */
    public function bootstrap($app)
    {
        if (\yii\helpers\ArrayHelper::getValue($app->params, 'mdm.behaviors.ar.scope', true)) {
            \Yii::$container->set('yii\db\ActiveQuery', [
                'as scope' => 'mdm\behaviors\ar\QueryBehavior'
            ]);
        }
    }
}
