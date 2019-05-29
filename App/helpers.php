<?php
/**
 * Created by PhpStorm.
 * User: yuanlj
 * Date: 2019/3/1
 * Time: 16:53
 */

if (!function_exists('p')) {
    function p($param, $flag = 0)
    {
        echo '<pre>';
        print_r($param);
        echo '</pre>';
        if ($flag) {
            die;
        }
    }
}

/**
 * 变量友好化打印输出
 * @param variable $param 可变参数
 * @return void
 * @version php>=5.6
 * @example dump($a,$b,$c,$e,[.1]) 支持多变量，使用英文逗号符号分隔，默认方式 print_r，查看数据类型传入 .1
 */
function pp(...$param)
{
    echo "\n";

    if (end($param) === .1) {
        array_splice($param, -1, 1);

        foreach ($param as $k => $v) {
            echo $k > 0 ? "\n" : "";

            ob_start();
            var_dump($v);

            echo preg_replace('/]=>\s+/', '] => <label>', ob_get_clean());
        }
    } else {
        foreach ($param as $k => $v) {
            echo $k > 0 ? "\n" : "", print_r($v, true);
        }
    }
    echo "\n";
}

if (!function_exists('is_cli')) {
    /*
    判断当前的运行环境是否是cli模式
    */
    function is_cli()
    {
        return preg_match("/cli/i", php_sapi_name()) ? true : false;
    }
}

if (!function_exists('gen_uid')) {
    function gen_uid()
    {
        do {
            $uid = str_replace('.', '0', uniqid(rand(0, 999999999), true));
        } while (strlen($uid) != 32);
        return $uid;
    }
}

/**
 * PHP大数组下，避免Mysql逐条执行，可以分批执行，提高代码效率
 */
if (!function_exists('insert_batch')) {
    function insert_batch($table, $keys, $values, $type = 'INSERT')
    {
        $tempArray = array();
        foreach ($values as $value) {
            $tempArray[] = implode('\', \'', $value);
        }
        return $type . ' INTO `' . $table . '` (`' . implode('`, `', $keys) . '`) VALUES (\'' . implode('\'), (\'', $tempArray) . '\')';
    }
}

if (!function_exists('http_get')) {
    /**
     * http get 请求
     * @param string
     */
    function http_get($url)
    {

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Connection: close"));
        //curl_setopt($ch, CURLOPT_USERAGENT, "(web server)");
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $res = curl_exec($ch);
        if (curl_errno($ch) || empty($res)) {
            $str = date('Y-m-d H:i:s') . ':' .
                $url . '--' .
                curl_errno($ch) . '--' .
                json_encode($res) . '--get--' .
                curl_error($ch) . '--' .
                json_encode(curl_getinfo($ch));
            file_put_contents('/tmp/curl_error.log', $str . PHP_EOL, FILE_APPEND);
        }
        curl_close($ch);

        return $res;
    }
}

/**
 * 获取本机IP
 * @return null|ip
 */
function getLocalIp()
{
    exec('ifconfig', $arr);
    $ip = null;
    foreach ($arr as $str) {
        if (preg_match('/\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}/', $str, $rs)) {
            if ($rs[0] != '127.0.0.1') {
                $ip = $rs[0];
                break;
            }
        }
    }
    return $ip;
}