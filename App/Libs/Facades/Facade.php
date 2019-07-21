<?php


namespace App\Libs\Facades;


abstract class Facade
{
    private static $accessor = [];

    abstract protected static function getFacadeAccessor();

    /**
     * 子类重写该方法用于传入子类构造函数的参数
     * @return array
     */
    protected static function initArgs()
    {
        return [];
    }

    public static function __callStatic($className, $params)
    {
        $cl = static::getFacadeAccessor();
        if (!isset(self::$accessor[$cl])) {
            self::$accessor[$cl] = new $cl(...static::initArgs());
        }
        return self::$accessor[$cl]->$className(...$params);
    }

    public function clear($class)
    {
        unset(self::$accessor[$class]);
    }
}