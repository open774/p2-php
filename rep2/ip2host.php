<?php

require_once __DIR__ . '/../init.php';

$lock_file = $_conf['tmp_dir'].'/ip2host.lock';
$cache_file = $_conf['tmp_dir'].'/ip2host.txt';
$ip_cache = array();
$ip_cache_size = 0;
$aborn = 1;

if (!$ip_cache_size = filter_input(INPUT_GET, 'cache_size')) {
    $ip_cache_size = -1;
} else {
    // cache_sizeが設定されていたらファイルキャッシュモードへ
    if ($fp_lock = lock($lock_file)) {
        if (file_exists($cache_file)) {
            if ($fp = fopen($cache_file, "r")) {
                while ($line = fgetcsv($fp)) {
                    $ip_cache[$line[0]] = $line[1];
                }
                fclose($fp);
            }
        }
        fclose($fp_lock);
    } else {
        $ip_cache_size = -1;
    }
}

$action = filter_input(INPUT_GET, 'action');
if ($action === 'GetHost') {
    if (!$ip = filter_input(INPUT_GET, 'ip')) {
        return;
    }

    if ($ip_cache_size > 0) {
        if (!empty($ip_cache) && array_key_exists($ip, $ip_cache)) {
            //echo 'cache ';
            $host = $ip_cache[$ip];
        } else {
            //  キャッシュの上限を超えたら古いものから10個消す
            while (count($ip_cache) > $ip_cache_size) {
                for ($i = 0; $i < 10; $i++) {
                    array_shift($ip_cache);
                }
            }
            //  キャッシュの上限を超えたら全部消す場合はこっち
            //if (count($ip_cache) > $ip_cache_size) {
            //    $ip_cache = array();
            //}

            $host = gethostbyaddr($ip);
            $ip_cache[$ip] = $host;
  
            if ($fp_lock = lock($lock_file)) {
                if ($fp = fopen($cache_file, "w")) {
                    foreach ($ip_cache as $key => $value) {
                        fwrite($fp, $key.','.$value."\n");
                    }
                    fclose($fp);
                }
                fclose($fp_lock);
            }
        }
    } else {
        $host = gethostbyaddr($ip);
    }
} else if ($action === 'AbornHost') {
    if (!$host = filter_input(INPUT_GET, 'host')) {
        return;
	}
} else {
    return;
}
if (!$bbs = filter_input(INPUT_GET, 'bbs')) {
    return;
}

if (!$title = filter_input(INPUT_GET, 'title')) {
    return;
}

if (!$aborn = filter_input(INPUT_GET, 'aborn')) {
    $aborn = 0;
}

if ($aborn && ngAbornCheck('aborn_name', $host, $bbs, UrlSafeBase64::decode($title)) !== false) {
    if ($action === 'GetHost') {
        echo $host.',';
    }
    echo 'aborn';
} else {
    echo $host;
}

// キャッシュファイルの排他ロック用
function lock($lock_file)
{
    if (!$fp = fopen($lock_file, "a")) return false;

    for ($i = 0; $i < 60; $i++) {
        if (flock($fp, LOCK_EX | LOCK_NB)) {
            return $fp;
        } else {
            usleep(500000); // 0.5秒 * 60回遅延
        }
    }
    fclose($fp);

    return false;
}

// {{{ ngAbornCheck()

/**
 * NGあぼーんチェック
 * lib/ShowThread.phpから引っこ抜いてあぼーん判定だけに
 */
function ngAbornCheck($code, $resfield, $bbs, $title, $ic = false)
{
    $ngaborns = NgAbornCtl::loadNgAborns();

    //$GLOBALS['debug'] && $GLOBALS['profiler']->enterSection('ngAbornCheck()');

    if (isset($ngaborns[$code]['data']) && is_array($ngaborns[$code]['data'])) {
        foreach ($ngaborns[$code]['data'] as $k => $v) {
            // 板チェック
            if (isset($v['bbs']) && in_array($bbs, $v['bbs']) == false) {
                continue;
            }

            // タイトルチェック
            if (isset($v['title']) && stripos($title, $v['title']) === false) {
                continue;
            }

            // ワードチェック
            // 正規表現
            if ($v['regex']) {
                $re_method = $v['regex'];
                /*if ($re_method($v['word'], $resfield, $matches)) {
                    $this->ngAbornUpdate($code, $k);
                    //$GLOBALS['debug'] && $GLOBALS['profiler']->leaveSection('ngAbornCheck()');
                    return p2h($matches[0]);
                }*/
                 if ($re_method($v['word'], $resfield)) {
                    //$GLOBALS['debug'] && $GLOBALS['profiler']->leaveSection('ngAbornCheck()');
                    return $v['cond'];
                }
            // 大文字小文字を無視
            } elseif ($ic || $v['ignorecase']) {
                if (stripos($resfield, $v['word']) !== false) {
                    //$GLOBALS['debug'] && $GLOBALS['profiler']->leaveSection('ngAbornCheck()');
                    return $v['cond'];
                }
            // 単純に文字列が含まれるかどうかをチェック
            } else {
                if (strpos($resfield, $v['word']) !== false) {
                    //$GLOBALS['debug'] && $GLOBALS['profiler']->leaveSection('ngAbornCheck()');
                    return $v['cond'];
                }
            }
        }
    }

    //$GLOBALS['debug'] && $GLOBALS['profiler']->leaveSection('ngAbornCheck()');
    return false;
}

// }}}

?>
