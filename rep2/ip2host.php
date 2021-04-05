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
    // cache_size���ݒ肳��Ă�����t�@�C���L���b�V�����[�h��
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
            //  �L���b�V���̏���𒴂�����Â����̂���10����
            while (count($ip_cache) > $ip_cache_size) {
                for ($i = 0; $i < 10; $i++) {
                    array_shift($ip_cache);
                }
            }
            //  �L���b�V���̏���𒴂�����S�������ꍇ�͂�����
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

// �L���b�V���t�@�C���̔r�����b�N�p
function lock($lock_file)
{
    if (!$fp = fopen($lock_file, "a")) return false;

    for ($i = 0; $i < 60; $i++) {
        if (flock($fp, LOCK_EX | LOCK_NB)) {
            return $fp;
        } else {
            usleep(500000); // 0.5�b * 60��x��
        }
    }
    fclose($fp);

    return false;
}

// {{{ ngAbornCheck()

/**
 * NG���ځ[��`�F�b�N
 * lib/ShowThread.php��������������Ă��ځ[�񔻒肾����
 */
function ngAbornCheck($code, $resfield, $bbs, $title, $ic = false)
{
    $ngaborns = NgAbornCtl::loadNgAborns();

    //$GLOBALS['debug'] && $GLOBALS['profiler']->enterSection('ngAbornCheck()');

    if (isset($ngaborns[$code]['data']) && is_array($ngaborns[$code]['data'])) {
        foreach ($ngaborns[$code]['data'] as $k => $v) {
            // �`�F�b�N
            if (isset($v['bbs']) && in_array($bbs, $v['bbs']) == false) {
                continue;
            }

            // �^�C�g���`�F�b�N
            if (isset($v['title']) && stripos($title, $v['title']) === false) {
                continue;
            }

            // ���[�h�`�F�b�N
            // ���K�\��
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
            // �啶���������𖳎�
            } elseif ($ic || $v['ignorecase']) {
                if (stripos($resfield, $v['word']) !== false) {
                    //$GLOBALS['debug'] && $GLOBALS['profiler']->leaveSection('ngAbornCheck()');
                    return $v['cond'];
                }
            // �P���ɕ����񂪊܂܂�邩�ǂ������`�F�b�N
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
