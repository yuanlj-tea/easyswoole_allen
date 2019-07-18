<?php


namespace App\Dispatch\Console;


abstract class AbstractConsole
{
    public static $command;

    public function getCommand()
    {
        return static::$command;
    }

    abstract public function handle();

}