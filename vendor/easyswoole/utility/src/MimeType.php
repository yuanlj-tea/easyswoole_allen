<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2019-01-10
 * Time: 20:30
 */

namespace EasySwoole\Utility;


class MimeType
{
    /*
     * 常见的拓展
     */
    const EXTENSION_MAP = [
        'audio/wav' => '.wav',
        'audio/x-ms-wma' => '.wma',
        'video/x-ms-wmv' => '.wmv',
        'video/mp4' => '.mp4',
        'audio/mpeg' => '.mp3',
        'audio/amr' => '.amr',
        'application/vnd.rn-realmedia' => '.rm',
        'audio/mid' => '.mid',
        'image/bmp' => '.bmp',
        'image/gif' => '.gif',
        'image/png' => '.png',
        'image/tiff' => '.tiff',
        'image/jpeg' => '.jpg',
        'application/pdf' => '.pdf',
        'application/msword' => '.doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => '.docx',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.template' => '.dotx',
        'application/vnd.ms-word.document.macroEnabled.12' => '.docm',
        'application/vnd.ms-word.template.macroEnabled.12' => '.dotm',
        'application/vnd.ms-excel' => '.xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => '.xlsx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.template' => '.xltx',
        'application/vnd.ms-excel.sheet.macroEnabled.12' => '.xlsm',
        'application/vnd.ms-excel.template.macroEnabled.12' => '.xltm',
        'application/vnd.ms-excel.addin.macroEnabled.12' => '.xlam',
        'application/vnd.ms-excel.sheet.binary.macroEnabled.12' => '.xlsb',
        'application/vnd.ms-powerpoint' => '.ppt',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => '.pptx',
        'application/vnd.openxmlformats-officedocument.presentationml.template' => '.potx',
        'application/vnd.openxmlformats-officedocument.presentationml.slideshow' => '.ppsx',
        'application/vnd.ms-powerpoint.addin.macroEnabled.12' => '.ppam',
    ];

    /*
     * 从一个文件流拿信息
     */
    static function getMimeTypeFromStream(?string $stream)
    {
        $fileInfo = new \finfo(FILEINFO_MIME);
        return strstr($fileInfo->buffer($stream), ';', true);
    }

    static function getExtFromStream(?string $stream):?string
    {
        //优先用map方式
        $mineInfo = self::getFromStream($stream);
        if(isset(self::EXTENSION_MAP[$mineInfo])){
            return self::EXTENSION_MAP[$mineInfo];
        }
        return null;
    }

    static function getExtByMimeType(string $mineInfo)
    {
        if(isset(self::EXTENSION_MAP[$mineInfo])){
            return self::EXTENSION_MAP[$mineInfo];
        }
        return null;
    }

    static function getMimeTypeByExt(string $ext)
    {
        if(strpos($ext,'.') === false){
            $ext = '.'.$ext;
        }
        return array_search($ext,self::EXTENSION_MAP);
    }
}