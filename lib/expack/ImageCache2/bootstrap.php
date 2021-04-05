<?php
/**
 * rep2expack - ImageCache2 初期化スクリプト
 */

// {{{ GLOBALS

$GLOBALS['_P2_GETIMAGE_CACHE'] = array();

// }}}
// {{{ constants

define('P2_IMAGECACHE_OK',     0);
define('P2_IMAGECACHE_ABORN',  1);
define('P2_IMAGECACHE_BROKEN', 2);
define('P2_IMAGECACHE_LARGE',  3);
define('P2_IMAGECACHE_VIRUS',  4);

define('P2_IMAGECACHE_BLACKLIST_NOMORE', 0);
define('P2_IMAGECACHE_BLACKLIST_ABORN',  1);
define('P2_IMAGECACHE_BLACKLIST_VIRUS',  2);

// }}}
// {{{ ic2_loadconfig()

/**
 * ユーザ設定読み込み関数
 *
 * @param void
 * @return array
 */
function ic2_loadconfig()
{
    static $ini = null;

    if (is_null($ini)) {
        $_conf = $GLOBALS['_conf'];
        include P2_CONFIG_DIR . '/conf_ic2.inc.php';

        $ini = array();
        $_ic2conf = preg_grep('/^expack\\.ic2\\.\\w+\\.\\w+$/', array_keys($_conf));
        foreach ($_ic2conf as $key) {
            $p = explode('.', $key);
            $cat = ucfirst($p[2]);
            $name = $p[3];
            if (!isset($ini[$cat])) {
                $ini[$cat] = array();
            }
            $ini[$cat][$name] = $_conf[$key];
        }

        // PDO_DataObjectの設定
        PDO_DataObject::config([
            'database' => $ini['General']['dsn'],
            'quote_identifiers' => true,
            'proxy'=>true,
            'debug'=>false
        ]);
    }

    return $ini;
}

// }}}
// {{{ ic2_findexec()

/**
 * 実行ファイル検索関数
 *
 * $search_pathから実行ファイル$commandを検索する
 * 見つかればパスをエスケープして返す（$escapeが偽ならそのまま返す）
 * 見つからなければfalseを返す
 *
 * @param string $command
 * @param string $search_path
 * @param bool $escape
 * @return string
 */
function ic2_findexec($command, $search_path = '', $escape = true)
{
    // Windowsか、その他のOSか
    if (P2_OS_WINDOWS) {
        if (strtolower(strrchr($command, '.')) != '.exe') {
            $command .= '.exe';
        }
        $check = function_exists('is_executable') ? 'is_executable' : 'file_exists';
    } else {
        $check = 'is_executable';
    }

    // $search_pathが空のときは環境変数PATHから検索する
    if ($search_path == '') {
        $search_dirs = explode(PATH_SEPARATOR, getenv('PATH'));
    } else {
        $search_dirs = explode(PATH_SEPARATOR, $search_path);
    }

    // 検索
    foreach ($search_dirs as $path) {
        $path = realpath($path);
        if ($path === false || !is_dir($path)) {
            continue;
        }
        if ($check($path . DIRECTORY_SEPARATOR . $command)) {
            return ($escape ? escapeshellarg($command) : $command);
        }
    }

    // 見つからなかった
    return false;
}

// }}}
// {{{ ic2_load_class()

/**
 * クラスローダー
 *
 * @string $name
 * @return void
 */
function ic2_load_class($name)
{
    if (strncmp($name, 'ImageCache2', 11) === 0) {
        include P2EX_LIB_DIR . '/' . str_replace('_', '/', $name) . '.php';
    } elseif (strncmp($name, 'Thumbnailer', 11) === 0) {
        include P2_LIB_DIR . '/' . str_replace('_', '/', $name) . '.php';
    }
}

// }}}

spl_autoload_register('ic2_load_class');

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
