<?php


namespace App\Dispatch\Consume;


abstract class Consume
{
    abstract public function consume($argv, ...$params);
}