<?php


namespace App\Libs;


class Common
{
    /*
     * 递归遍历文件夹下的所有内容,返回所有非文件夹的文件
     * $foldername  要遍历的文件夹名
     */
    public static function getFiles($folderpath, $isClear = 0)
    {
        static $arr = array();
        if ($isClear) {
            $arr = array();   //创建一个空数组,用来存放当前打开的文件夹里的文件夹名
        }
        $folder = opendir($folderpath);     //打开文件夹
        while ($f = readdir($folder)) {         //读取打开的文件夹
            if ($f == '.' || $f == '..') {
                continue;
            }
            if (!is_dir($folderpath . '/' . $f)) {
                //$arr[] = $f;
                $arr[] = $folderpath . '/' . $f;
            }
            if (is_dir($folderpath . '/' . $f)) {
                self::getFiles($folderpath . '/' . $f);
            }
        }
        return $arr;
    }
}