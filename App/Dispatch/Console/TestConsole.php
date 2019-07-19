<?php


namespace App\Dispatch\Console;


use App\Utility\Pool\MysqlPool;

class TestConsole extends AbstractConsole
{
    public static $command = 'foo:bar';

    public function handle(? array $argv)
    {
        $db = MysqlPool::defer();
        $ret = $db->insert('user', ['name' => 'zs', 'age' => 18]);
        pp($ret);
    }

}