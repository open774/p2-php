<?php

// {{{ ImageCache2_DataObject_Images

class ImageCache2_DataObject_Images extends ImageCache2_DataObject_Common
{
    public $__table;                        // テーブル名
    public $id;                             // INTEGER not_null primary
    public $uri;                            // CHARACTER VARYING
    public $host;                           // CHARACTER VARYING
    public $name;                           // CHARACTER VARYING
    public $size;                           // INTEGER not_null
    public $md5;                            // CHARACTER not_null
    public $width;                          // SMALLINT not_null
    public $height;                         // SMALLINT not_null
    public $mime;                           // CHARACTER VARYING not_null
    public $time;                           // INTEGER not_null
    public $rank;                           // SMALLINT not_null
    public $memo;                           // TEXT

    // {{{ constants

    const OK     = 0;
    const ABORN  = 1;
    const BROKEN = 2;
    const LARGE  = 3;
    const VIRUS  = 4;

    // }}}
    // {{{ constcurtor

    public function __construct()
    {
        parent::__construct();
        $this->__table = $this->_ini['General']['table'];
    }

    public function keys()
    {
        return array('uri');
    }

    // }}}
    // {{{ uniform()

    // 検索用に文字列をフォーマットする
    public function uniform($str, $enc, $to_lower = true)
    {
        return self::staticUniform($str, $enc, $to_lower);
    }

    // }}}
    // {{{ ic2_isError()

    public function ic2_isError($url)
    {
        // ブラックリストをチェック
        $blacklist = new ImageCache2_DataObject_BlackList();
        if ($blacklist->get($url)) {
            switch ($blacklist->type) {
                case 0:
                    return 'x05'; // No More
                case 1:
                    return 'x01'; // Aborn
                case 2:
                    return 'x04'; // Virus
                default:
                    return 'x06'; // Unknown
            }
        }

        // エラーログをチェック
        if ($this->_ini['Getter']['checkerror']) {
            $errlog = new ImageCache2_DataObject_Errors();
            if ($errlog->get($url)) {
                return $errlog->errcode;
            }
        }

        return false;
    }

    // }}}
    // {{{ staticUniform()

    /**
     * 検索用に文字列をフォーマットする
     */
    public static function staticUniform($str, $enc, $to_lower = true)
    {
        if (!$enc) {
            $enc = mb_detect_encoding($str, 'CP932,UTF-8,CP51932,JIS');
        }
        if (strcasecmp($enc, 'UTF-8') !== 0) {
            $str = mb_convert_encoding($str, 'UTF-8', $enc);
        }
        $str = mb_convert_kana($str, 'KVas', 'UTF-8');
        if ($to_lower) {
            $str = mb_strtolower($str, 'UTF-8');
        }

        return preg_replace('/\s+/u', ' ', trim($str));
    }

    // }}}
}

// }}}

/*
 * Local Variables:
 * mode: php
 * coding: cp932
 * tab-width: 4
 * c-basic-offset: 4
 * indent-tabs-mode: nil
 * End:
 */
// vim: set syn=php fenc=cp932 ai et ts=4 sw=4 sts=4 fdm=marker:
