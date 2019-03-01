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