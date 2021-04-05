<?php
/**
 * rep2 - 殿堂入り関係の処理
 */

// {{{ setPal()

/**
 * スレを殿堂入りにセットする
 *
 * @param   string      $host
 * @param   string      $bbs
 * @param   string      $key
 * @param   int|string  $setpal  0(解除), 1(追加), 2(トグル) top, up, down, bottom
 * @param   string      $ttitle
 * @return  bool
 */
function setPal($host, $bbs, $key, $setpal, $ttitle = null)
{
    global $_conf;

     // key.idx のパスを求めて
    $idxfile = P2Util::idxDirOfHostBbs($host, $bbs) . $key . '.idx';

    // 既に key.idx データがあるなら読み込む
    if ($lines = FileCtl::file_read_lines($idxfile, FILE_IGNORE_NEW_LINES)) {
        $data = explode('<>', $lines[0]);
    } else {
        $data = array_fill(0, 12, '');
        if (is_string($ttitle) && strlen($ttitle)) {
            $data[0] = p2h($ttitle, false);
        }
    }

    //==================================================================
    // p2_palace.idxに書き込む
    //==================================================================
    $lock = new P2Lock($_conf['palace_idx'], false);

    // palace_idx ファイルがなければ生成
    FileCtl::make_datafile($_conf['palace_idx']);

    // palace_idx 読み込み
    $pallines = FileCtl::file_read_lines($_conf['palace_idx'], FILE_IGNORE_NEW_LINES);

    $neolines = array();
    $pal_attayo = false;
    $before_line_num = 0;

     // {{{ 最初に重複要素を削除しておく

    if (!empty($pallines)) {
        $i = -1;
        foreach ($pallines as $l) {
            $i++;
            $lar = explode('<>', $l);
            // 重複回避
            if ($lar[1] == $key && $lar[11] == $bbs && $lar[10] == $host) {
                $pal_attayo = true;
                $before_line_num = $i;    // 移動前の行番号をセット
                continue;
            // keyのないものは不正データなのでスキップ
            } elseif (!$lar[1]) {
                continue;
            } else {
                $neolines[] = $l;
            }
        }
    }

    // }}}

    if ($setpal == 2) {
        $setpal = ($pal_attayo) ? 0 : 1;
    }

    // 新規データ設定
    if ($setpal) {
        $newdata = "{$data[0]}<>{$key}<>{$data[2]}<>{$data[3]}<>{$data[4]}<>{$data[5]}<>{$data[6]}<>{$data[7]}<>{$data[8]}<>{$data[9]}<>{$host}<>{$bbs}";
        require_once P2_LIB_DIR . '/getsetposlines.inc.php';
        $rec_lines = getSetPosLines($neolines, $newdata, $before_line_num, $setpal);
    } else {
        $rec_lines = $neolines;
    }

    $cont = '';
    if (!empty($rec_lines)) {
        foreach ($rec_lines as $l) {
            $cont .= $l . "\n";
        }
    }

    // 書き込む
    if (FileCtl::file_write_contents($_conf['palace_idx'], $cont) === false) {
        p2die('cannot write file.');
    }

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
