<?php


namespace App\Dispatch\Console;


abstract class AbstractConsole
{
    public static $command='';

    public static $desc='';

    public static function getCommand()
    {
        return static::$command;
    }

    public static function getDesc()
    {
        return static::$desc;
    }

    abstract public function handle(? array $argv);

    public function displayItem($name, $value)
    {
        return "\e[32m" . str_pad($name, 30, ' ', STR_PAD_RIGHT) . " : " . "\e[34m" . $value . "\e[0m";
    }

    public function info($info)
    {
        pp(sprintf("\033[0m%s", $info));
    }

    public function error($info)
    {
        pp(sprintf("\033[41m%s", $info));
    }
}