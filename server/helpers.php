<?php


function escape($input, $urldecode = 0)
{
    if (is_array($input)) {
        foreach ($input as $k => $v) {
            $input[$k] = escape($v, $urldecode);
        }
    } else {
        $input = trim($input);
        if ($urldecode == 1) {
            $input = str_replace(array('+'), array('{addplus}'), $input);
            $input = urldecode($input);
            $input = str_replace(array('{addplus}'), array('+'), $input);
        }

        if (strnatcasecmp(PHP_VERSION, '5.4.0') >= 0) {
            $input = addslashes($input);
        } else {

            if (!get_magic_quotes_gpc()) {
                $input = addslashes($input);
            }
        }
    }

    if (substr($input, -1, 1) == '\\') $input = $input . "'";//$input=substr($input,0,strlen($input)-1);
    return $input;
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

/**
 * 变量友好化打印输出
 * @param variable $param 可变参数
 * @return void
 * @version php>=5.6
 * @example dump($a,$b,$c,$e,[.1]) 支持多变量，使用英文逗号符号分隔，默认方式 print_r，查看数据类型传入 .1
 */
function pp(...$param)
{
    echo is_cli() ? "\n" : '<pre>';

    if (end($param) === .1) {
        array_splice($param, -1, 1);

        foreach ($param as $k => $v) {
            echo $k > 0 ? '<hr>' : '';

            ob_start();
            var_dump($v);

            echo preg_replace('/]=>\s+/', '] => <label>', ob_get_clean());
        }
    } else {
        foreach ($param as $k => $v) {
            echo $k > 0 ? '<hr>' : '', print_r($v, true); // echo 逗号速度快 https://segmentfault.com/a/1190000004679782
        }
    }
    echo is_cli() ? "\n" : '</pre>';
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

function gen_uid()
{
    do {
        $uid = str_replace('.', '0', uniqid(rand(0, 999999999), true));
    } while (strlen($uid) != 32);
    return $uid;
}