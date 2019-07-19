<?php


namespace App\Dispatch\Console;


abstract class AbstractConsole
{
    public static $command;

    public static function getCommand()
    {
        return static::$command;
    }

    abstract public function handle(? array $argv);

    public function __call($name, $arguments)
    {

    }

    public static function __callStatic($name, $arguments)
    {
        return self::$name($arguments);
    }
}