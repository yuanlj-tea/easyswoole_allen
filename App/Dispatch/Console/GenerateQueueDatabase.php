<?php


namespace App\Dispatch\Console;


use App\Utility\Pool\MysqlObject;
use App\Utility\Pool\MysqlPool;

class GenerateQueueDatabase extends AbstractConsole
{
    public static $command = 'gen:queue:database';

    public static $desc = '生成queue驱动表';

    public function handle(?array $argv)
    {
        $dropJobsSql = "DROP table if exists `jobs`;";
        $sql = "
CREATE TABLE `jobs` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) COLLATE utf8mb4_general_ci NOT NULL COMMENT '对列名',
  `content` text COLLATE utf8mb4_general_ci NOT NULL COMMENT '队列内容,json数据',
  `add_time` datetime NOT NULL COMMENT '添加时间',
  PRIMARY KEY (`id`),
  KEY `queue` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";

        $dropFailedJobsSql = "DROP table if exists `failed_jobs`;";
        $failedSQL = "
CREATE TABLE `failed_jobs` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) COLLATE utf8mb4_general_ci NOT NULL COMMENT '对列名',
  `content` text COLLATE utf8mb4_general_ci NOT NULL COMMENT '队列内容,json数据',
  `add_time` datetime NOT NULL COMMENT '添加时间',
  `try_times` int default null COMMENT '尝试次数',
  PRIMARY KEY (`id`),
  KEY `queue` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";

        try {
            MysqlPool::invoke(function (MysqlObject $db) use ($sql, $failedSQL, $dropJobsSql, $dropFailedJobsSql) {
                if ($db->rawQuery($dropJobsSql)) {
                    echo "删除jobs表成功\n";
                }
                if ($db->rawQuery($dropFailedJobsSql)) {
                    echo "删除failed_jobs表成功\n";
                }
                if ($db->rawQuery($sql)) {
                    echo "生成jobs表成功\n";
                }
                if ($db->rawQuery($failedSQL)) {
                    echo "生成failed_jobs表成功\n";
                }
            });
            exit("ok\n");
        } catch (\Exception $e) {
            $this->error(sprintf("[FILE] %s || [LINE] %s || [MSG] %s", $e->getFile(), $e->getLine(), $e->getMessage()));
            die;
        }
    }

}