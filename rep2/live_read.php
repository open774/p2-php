<?php
/**
 * rep2 - �X���b�h�\���X�N���v�g�i������p�j
 * �t���[��������ʁA�E������
 */

define('P2_SESSION_CLOSE_AFTER_AUTHENTICATION', 0);
require_once __DIR__ . '/../init.php';

$_login->authorize(); // ���[�U�F��

// +Wiki
require_once P2_LIB_DIR . '/wiki/read.inc.php';

// iPhone
if ($_conf['iphone']) {
    include P2_LIB_DIR . '/toolbar_i.inc.php';
    define('READ_HEADER_INC_PHP', P2_LIB_DIR . '/read_header_i.inc.php');
    define('READ_FOOTER_INC_PHP', P2_LIB_DIR . '/read_footer_i.inc.php');
// �g��
} elseif ($_conf['ktai']) {
    define('READ_HEADER_INC_PHP', P2_LIB_DIR . '/read_header_k.inc.php');
    define('READ_FOOTER_INC_PHP', P2_LIB_DIR . '/read_footer_k.inc.php');
// PC
} else {
    define('READ_HEADER_INC_PHP', P2_LIB_DIR . '/read_header.inc.php');
    define('READ_FOOTER_INC_PHP', P2_LIB_DIR . '/read_footer.inc.php');
}

//================================================================
// �ϐ�
//================================================================
$newtime = date('gis');  // ���������N���N���b�N���Ă��ēǍ����Ȃ��d�l�ɑ΍R����_�~�[�N�G���[
// $_today = date('y/m/d');

//=================================================
// �X���̎w��
//=================================================
detectThread();    // global $host, $bbs, $key, $ls

//=================================================
// ���X�t�B���^
//=================================================
$do_filtering = false;
if (array_key_exists('rf', $_REQUEST) && is_array($_REQUEST['rf'])) {
    $resFilter = ResFilter::configure($_REQUEST['rf']);
    if ($resFilter->hasWord()) {
        $do_filtering = true;
        if ($_conf['ktai']) {
            $page = isset($_REQUEST['page']) ? $_REQUEST['page'] : 1;
            $resFilter->setRange($_conf['mobile.rnum_range'], $page);
        }
        if (empty($popup_filter) && isset($_REQUEST['submit_filter'])) {
            $resFilter->save();
        }
    }
} else {
    $resFilter = ResFilter::restore();
}

//=================================================
// ���ځ[��&NG���[�h�ݒ�ǂݍ���
//=================================================
$GLOBALS['ngaborns'] = NgAbornCtl::loadNgAborns();

//==================================================================
// ���C��
//==================================================================

if (!isset($aThread)) {
    $aThread = new ThreadRead();
}

// ls�̃Z�b�g
if (!empty($ls)) {
    $aThread->ls = mb_convert_kana($ls, 'a');
}

//==========================================================
// idx�̓ǂݍ���
//==========================================================

// host�𕪉�����idx�t�@�C���̃p�X�����߂�
if (!isset($aThread->keyidx)) {
    $aThread->setThreadPathInfo($host, $bbs, $key);
}

// �f�B���N�g����������΍��
FileCtl::mkdirFor($aThread->keyidx);
FileCtl::mkdirFor($aThread->keydat);

$aThread->itaj = P2Util::getItaName($host, $bbs);
if (!$aThread->itaj) { $aThread->itaj = $aThread->bbs; }

// idx�t�@�C��������Γǂݍ���
if ($lines = FileCtl::file_read_lines($aThread->keyidx, FILE_IGNORE_NEW_LINES)) {
    $idx_data = explode('<>', $lines[0]);
} else {
    $idx_data = array_fill(0, 12, '');
}
$aThread->getThreadInfoFromIdx();

//===========================================================
// DAT�̃_�E�����[�h
//===========================================================
$offline = !empty($_GET['offline']);

if (!$offline) {
    $aThread->downloadDat();
}

// DAT��ǂݍ���
$aThread->readDat();

// �I�t���C���w��ł����O���Ȃ���΁A���߂ċ����ǂݍ���
if (empty($aThread->datlines) && $offline) {
    $aThread->downloadDat();
    $aThread->readDat();
}

// �^�C�g�����擾���Đݒ�
$aThread->setTitleFromLocal();

//===========================================================
// �\�����X�Ԃ͈̔͂�ݒ�
//===========================================================
if ($_conf['ktai']) {
    $before_respointer = $_conf['mobile.before_respointer'];
} else {
    $before_respointer = $_conf['before_respointer'];
}

// �擾�ς݂Ȃ�
if ($aThread->isKitoku()) {

    //�u�V�����X�̕\���v�̎��͓��ʂɂ�����ƑO�̃��X����\��
    if (!empty($_GET['nt'])) {
        if (substr($aThread->ls, -1) == '-') {
            $n = $aThread->ls - $before_respointer;
            if ($n < 1) { $n = 1; }
            $aThread->ls = $n . '-';
        }

    } elseif (!$aThread->ls) {
        $from_num = $aThread->readnum +1 - $_conf['respointer'] - $before_respointer;
        if ($from_num < 1) {
            $from_num = 1;
        } elseif ($from_num > $aThread->rescount) {
            $from_num = $aThread->rescount - $_conf['respointer'] - $before_respointer;
        }
        $aThread->ls = $from_num . '-';
    }

    if ($_conf['ktai'] && strpos($aThread->ls, 'n') === false) {
        $aThread->ls = $aThread->ls . 'n';
    }

// ���擾�Ȃ�
} else {
    if (!$aThread->ls) {
        $aThread->ls = $_conf['get_new_res_l'];
    }
}

// �t�B���^�����O�̎��́Aall�Œ�Ƃ���
if ($resFilter && $resFilter->hasWord()) {
    $aThread->ls = 'all';
}

$aThread->lsToPoint();

//===============================================================
// �v�����g
//===============================================================
$ptitle_ht = p2h($aThread->itaj) . ' / ' . $aThread->ttitle_hd;

if ($_conf['ktai']) {
    include READ_HEADER_INC_PHP;

    echo "PC�̂ݎ����ł��܂��B";

    include READ_FOOTER_INC_PHP;

} else {

    // �w�b�_ �\��
    include READ_HEADER_INC_PHP;
    flush();

    //===========================================================
    // ���[�J��Dat��ϊ�����HTML�\��
    //===========================================================
    // ���X������A�����w�肪�����
    if ($resFilter && $resFilter->hasWord() && $aThread->rescount) {

        $all = $aThread->rescount;

        $GLOBALS['filter_hits'] = 0;

        echo "<p><b id=\"filterstart\">{$all}���X�� <span id=\"searching\">n</span>���X���q�b�g</b></p>\n";
    }
    if ($_GET['showbl']) {
        echo  '<p><b>' . p2h($aThread->resrange['start']) . '�ւ̃��X</b></p>';
    }

    //$GLOBALS['debug'] && $GLOBALS['profiler']->enterSection("datToHtml");

    if ($aThread->rescount) {
        // �ǂ����\�����Ȃ��̂Ŗ{�̂�ShowThreadPc���g��
        // �\�����Ȃ����e��S�����̂��A���Ȃ̂ōŒ���̏o�͂�ShowThreadLive�ɕύX
		require_once P2_LIB_DIR . '/live/live_ShowThreadLive.php';
        $aShowThread = new ShowThreadLive($aThread);

        if ($_conf['expack.spm.enabled']) {
            echo $aShowThread->getSpmObjJs();
        }

        $res1 = $aShowThread->quoteOne(); // >>1�|�b�v�A�b�v�p

        // �Ă΂Ȃ���ID�J���[�Ȃǂ����f����Ȃ��̂ŌĂԂ����ʂ͕\�����Ȃ�
        if ($_GET['showbl']) {
            $aShowThread->getDatToHtml_resFrom();
        } else {
            $aShowThread->getDatToHtml();
        }

        // ���X�ǐՃJ���[
        if ($_conf['backlink_coloring_track']) {
            echo $aShowThread->getResColorJs();
        }

        // ID�J���[�����O
        if ($_conf['coloredid.enable'] > 0 && $_conf['coloredid.click'] > 0) {
            echo $aShowThread->getIdColorJs();
        }

        // �{���̑���
        echo <<<LIVE
\n<div id="live_view"></div>\n
LIVE;

        // �O���c�[��
        $pluswiki_js = '';

        if ($_conf['wiki.idsearch.spm.mimizun.enabled']) {
            if (!class_exists('Mimizun', false)) {
                require P2_PLUGIN_DIR . '/mimizun/Mimizun.php';
            }
            $mimizun = new Mimizun();
            $mimizun->host = $aThread->host;
            $mimizun->bbs  = $aThread->bbs;
            if ($mimizun->isEnabled()) {
                $pluswiki_js .= "WikiTools.addMimizun({$aShowThread->spmObjName});";
            }
        }

        if ($_conf['wiki.idsearch.spm.hissi.enabled']) {
            if (!class_exists('Hissi', false)) {
                require P2_PLUGIN_DIR . '/hissi/Hissi.php';
            }
            $hissi = new Hissi();
            $hissi->host = $aThread->host;
            $hissi->bbs  = $aThread->bbs;
            if ($hissi->isEnabled()) {
                $pluswiki_js .= "WikiTools.addHissi({$aShowThread->spmObjName});";
            }
        }

        if ($_conf['wiki.idsearch.spm.stalker.enabled']) {
            if (!class_exists('Stalker', false)) {
                require P2_PLUGIN_DIR . '/stalker/Stalker.php';
            }
            $stalker = new Stalker();
            $stalker->host = $aThread->host;
            $stalker->bbs  = $aThread->bbs;
            if ($stalker->isEnabled()) {
                $pluswiki_js .= "WikiTools.addStalker({$aShowThread->spmObjName});";
            }
        }

        if ($pluswiki_js !== '') {
            echo <<<EOP
<script type="text/javascript">
//<![CDATA[
{$pluswiki_js}
//]]>
</script>
EOP;
        }

    } elseif ($aThread->diedat && count($aThread->datochi_residuums) > 0) {
        $aShowThread = new ShowThreadPc($aThread);
        echo $aShowThread->getDatochiResiduums();
    }

    //$GLOBALS['debug'] && $GLOBALS['profiler']->leaveSection("datToHtml");

    // �t�B���^���ʂ�\��
    if ($resFilter && $resFilter->hasWord() && $aThread->rescount) {
        echo <<<EOP
<script type="text/javascript">
//<![CDATA[
var filterstart = document.getElementById('filterstart');
if (filterstart) {
    filterstart.style.backgroundColor = 'yellow';
    filterstart.style.fontWeight = 'bold';
}
//]]>
</script>\n
EOP;
        if ($GLOBALS['filter_hits'] > 5) {
            echo "<p><b class=\"filtering\">{$all}���X�� {$GLOBALS['filter_hits']}���X���q�b�g</b></p>\n";
        }
    }

    // �t�b�^ �\��
    include READ_FOOTER_INC_PHP;
}
flush();

//===========================================================
// idx�̒l��ݒ�A�L�^
//===========================================================
if ($aThread->rescount) {

    // �����̎��́A���ǐ����X�V���Ȃ�
    if ((isset($GLOBALS['word']) && strlen($GLOBALS['word']) > 0)) {
        $aThread->readnum = $idx_data[5];
    } else {
        $aThread->readnum = min($aThread->rescount, max(0, $idx_data[5], $aThread->resrange['to']));
    }
    $newline = $aThread->readnum + 1; // $newline�͔p�~�\�肾���A���݊��p�ɔO�̂���

    $sar = array($aThread->ttitle, $aThread->key, $idx_data[2], $aThread->rescount, '',
                 $aThread->readnum, $idx_data[6], $idx_data[7], $idx_data[8], $newline,
                 $idx_data[10], $idx_data[11], $aThread->datochiok);
    P2Util::recKeyIdx($aThread->keyidx, $sar); // key.idx�ɋL�^
}

//===========================================================
// �������L�^
// ����headline�͍ŋߓǂ񂾃X���ɋL�^���Ȃ��悤�ɂ��Ă݂�
//===========================================================
if ($aThread->rescount && $aThread->host != 'headline.2ch.net'&& $aThread->host != 'headline.5ch.net') {
    recRecent(implode('<>', array($aThread->ttitle, $aThread->key, $idx_data[2], '', '',
                                  $aThread->readnum, $idx_data[6], $idx_data[7], $idx_data[8], $newline,
                                  $aThread->host, $aThread->bbs)));
}

// NG���ځ[����L�^
NgAbornCtl::saveNgAborns();

// �ȏ� ---------------------------------------------------------------
exit;

//===============================================================================
// �֐�
//===============================================================================
// {{{ detectThread()

/**
 * �X���b�h���w�肷��
 */
function detectThread()
{
    global $_conf, $host, $bbs, $key, $ls;

    list($nama_url, $host, $bbs, $key, $ls) = P2Util::detectThread();

    if (!($host && $bbs && $key)) {
        if ($nama_url) {
            $nama_url = p2h($nama_url);
            p2die('�X���b�h�̎w�肪�ςł��B', "<a href=\"{$nama_url}\">{$nama_url}</a>", true);
        } else {
            p2die('�X���b�h�̎w�肪�ςł��B');
        }
    }
}

// }}}
// {{{ recRecent()

/**
 * �������L�^����
 */
function recRecent($data)
{
    global $_conf;

    $lock = new P2Lock($_conf['recent_idx'], false);

    // $_conf['recent_idx'] �t�@�C�����Ȃ���ΐ���
    FileCtl::make_datafile($_conf['recent_idx']);

    $lines = FileCtl::file_read_lines($_conf['recent_idx'], FILE_IGNORE_NEW_LINES);
    $neolines = array();

    // {{{ �ŏ��ɏd���v�f���폜���Ă���

    if (is_array($lines)) {
        foreach ($lines as $l) {
            $lar = explode('<>', $l);
            $data_ar = explode('<>', $data);
            if (!$lar[1] || !strlen($lar[11])) { continue; } // �s���f�[�^���폜
            if ($lar[1] == $data_ar[1] && $lar[11] == $data_ar[11] && $lar[10] == $data_ar[10]) { continue; } // key, bbs�ŏd�����
            $neolines[] = $l;
        }
    }

    // }}}

    // �V�K�f�[�^�ǉ�
    array_unshift($neolines, $data);

    while (sizeof($neolines) > $_conf['rct_rec_num']) {
        array_pop($neolines);
    }

    // {{{ ��������

    if ($neolines) {
        $cont = '';
        foreach ($neolines as $l) {
            $cont .= $l . "\n";
        }

        if (FileCtl::file_write_contents($_conf['recent_idx'], $cont) === false) {
            p2die('cannot write file.');
        }
    }

    // }}}

    return true;
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
