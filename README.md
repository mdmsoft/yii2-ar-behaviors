Activerecord Behaviors for Yii2
===============================

Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require mdmsoft/yii2-ar-behaviors "*"
```

or add

```
"mdmsoft/yii2-ar-behaviors": "*"
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