<?php


namespace App\Libs\Facades;


class Room extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \App\WebSocket\Room::class;
    }
}