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
 * @param variable  $param  可变参数
 * @example dump($a,$b,$c,$e,[.1]) 支持多变量，使用英文逗号符号分隔，默认方式 print_r，查看数据类型传入 .1
 * @version php>=5.6
 * @return void
 */
function pp(...$param){
    echo is_cli() ? "\n" : '<pre>';

    if(end($param) === .1){
        array_splice($param, -1, 1);

        foreach($param as $k => $v){
            echo $k>0 ? '<hr>' : '';

            ob_start();
            var_dump($v);

            echo preg_replace('/]=>\s+/', '] => <label>', ob_get_clean());
        }
    }else{
        foreach($param as $k => $v){
            echo $k>0 ? '<hr>' : '', print_r($v, true); // echo 逗号速度快 https://segmentfault.com/a/1190000004679782
        }
    }
    echo is_cli() ? "\n" : '</pre>';
}

if(!function_exists('is_cli')){
    /*
    判断当前的运行环境是否是cli模式
    */
    function is_cli()
    {
        return preg_match("/cli/i", php_sapi_name()) ? true : false;
    }
}