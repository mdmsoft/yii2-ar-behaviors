Activerecord Behaviors for Yii2
===============================

Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require mdmsoft/yii2-ar-behaviors "~1.0"
```

or add

```
"mdmsoft/yii2-ar-behaviors": "~1.0"
```

to the require section of your `composer.json` file.

Query Scope
----------
`QueryScopeBehavior` will automatically attached to `ActiveQuery` via `Yii::$container`. You can use this behavior to
create query scope from `ActiveRecord`.

```php
class Sales extends ActiveRecord
{
    ...
    public static function defaultScope($query)
    {
        $query->andWhere(['status' => self::STATUS_OPEN]);
    }

    public static function bigOrder($query, $ammount=100)
    {
        $query->andWhere(['>','ammount',$ammount]);
    }
}

----
// get all opened sales
Sales::find()->all(); // apply defaultScope

// opened sales and order bigger than 200
Sales::find()->bigOrder(200)->all();

```

ExtendedBehavior
----------------
Extend `Activerecord` with out inheriting :grinning: .
This behavior use to merge two table and treated as one ActiveRecord.

Example:
We have model `CustomerDetail`

```php
/**
 * @property integer $id
 * @property string $full_name
 * @property string $organisation
 * @property string $address1
 * @property string $address2
 */
class CustomerDetail extends ActiveRecord
{
    
}
```

and model `Customer`

```php
/**
 * @property integer $id
 * @property string $name
 * @property string $email
 */
class Customer extends ActiveRecord
{
    
    public function behaviors()
    {
        return [
            [
                'class' => 'mdm\behaviors\ar\ExtendedBehavior',
                'relationClass' => CustomerDetail::className(),
                'relationKey' => ['id' => 'id'],
            ],
        ];
    }
}
```

After that, we can access `CustomerDetail` property from `Customer` as their own property

```php
$model = new Customer();

$model-name = 'Doflamingo';
$model->organisation = 'Donquixote Family';
$model->address = 'North Blue';

$model->save(); // it will save this model and related model
```

RelationBehavior
----------------
Use to save model and its relation.

```php
class Order extends ActiveRecord
{
    public function getItems()
    {
        return $this->hasMany(Item::className(),['order_id'=>'id']);
    }

    public function behaviors()
    {
        return [
            [
                'class' => 'mdm\behaviors\ar\RelationBehavior',
                'beforeRSave' => function($item){
                    return $item->qty != 0;
                }
            ],
        ];
    }
}
```

usage

```php
$model = new Order();

if($model->load(Yii::$app->request->post()){
    $model->items = Yii::$app->request->post('Item',[]);
    $model->save();
}
```

RelationTrait
-------------
Similar with RelationBehavior

```php
class Order extends ActiveRecord
{
    use \mdm\behavior\ar\RelationTrait;

    public function getItems()
    {
        return $this->hasMany(Item::className(),['order_id'=>'id']);
    }

    public function setItems($value)
    {
        $this->loadRelated('items', $value);
    }

    public function beforeRSave($item)
    {
        return $item->qty != 0;
    }
    
}
```

usage

```php
$model = new Order();

if($model->load(Yii::$app->request->post()){
    $model->items = Yii::$app->request->post('Item',[]);
    $model->save();
}
```
