<?php
// {{{ StrCtl

/**
 * rep2 - StrCtl -- 文字列操作クラス
 * クラスメソッドで利用する
 *
 * @static
 */
class StrCtl
{
    // {{{ wordForMatch()

    /**
     * フォームから送られてきたワードをマッチ関数に適合させる
     *
     * @return string $word_fm 適合パターン。SJISで返す。
     */
    static public function wordForMatch($word, $method = 'regex')
    {
        $word_fm = $word;

        // 「そのまま」でなければ、全角空白を半角空白に矯正
        if ($method != 'just') {
            $word_fm = mb_convert_kana($word_fm, 's');
        }

        $word_fm = p2h(trim($word_fm));

        if (in_array($method, array('and', 'or', 'just'))) {
            // preg_quote()で2バイト目が0x5B("[")の"ー"なども変換されてしまうので
            // UTF-8にしてから正規表現の特殊文字をエスケープ
            $word_fm = mb_convert_encoding($word_fm, 'UTF-8', 'CP932');
            if (P2_MBREGEX_AVAILABLE == 1) {
                $word_fm = preg_quote($word_fm);
            } else {
                $word_fm = preg_quote($word_fm, '/');
            }
            $word_fm = mb_convert_encoding($word_fm, 'CP932', 'UTF-8');

        // 他、regex（正規表現）なら
        } else {
            if (P2_MBREGEX_AVAILABLE == 0) {
                $word_fm = str_replace('/', '\/', $word_fm);
            }
        }
        return $word_fm;
    }

    // }}}
    // {{{ filterMatch()

    /**
     * マルチバイト対応で正規表現マッチする
     *
     * @param string $pattern マッチ文字列。SJISで入ってくる。
     * @param string $target  検索対象文字列。SJISで入ってくる。
     * @param string $zenhan  全角/半角の区別を完全になくす
     * （これをオンにすると、メモリの使用量が倍くらいになる。速度負担はそれほどでもない）
     */
    static public function filterMatch($pattern, $target, $zenhan = true)
    {
        // 全角/半角を（ある程度）区別なくマッチ
        if ($zenhan) {
            // 全角/半角を 完全に 区別なくマッチ
            $pattern = self::getPatternToHan($pattern);
            $target = self::getPatternToHan($target, true);

        } else {
            // 全角/半角を ある程度 区別なくマッチ
            $pattern = self::getPatternZenHan($pattern);
        }

        // HTML要素にマッチさせないための否定先読みパターンを付加
        $pattern = '(' . $pattern . ')(?![^<]*>)';

        if (P2_MBREGEX_AVAILABLE == 1) {
            $result = @mb_eregi($pattern, $target);    // None|Error:false
        } else {
            // UTF-8に変換してから処理する
            $pattern_utf8 = '/' . mb_convert_encoding($pattern, 'UTF-8', 'CP932') . '/iu';
            $target_utf8 = mb_convert_encoding($target, 'UTF-8', 'CP932');
            $result = @preg_match($pattern_utf8, $target_utf8);    // None:0, Error:false
            //$result = mb_convert_encoding($result, 'CP932', 'UTF-8');
        }

        if (!$result) {
            return false;
        } else {
            return true;
        }
    }

    // }}}
    // {{{ filterMarking()

    /**
     * マルチバイト対応でマーキングする
     *
     * @param string $pattern マッチ文字列。SJISで入ってくる。
     * @param string $target 置換対象文字列。SJISで入ってくる。
     */
    static public function filterMarking($pattern, $target, $marker = '<b class="filtering">\\1</b>')
    {
        // 全角/半角を（ある程度）区別なくマッチ
        $pattern = self::getPatternZenHan($pattern);

        // HTML要素にマッチさせないための否定先読みパターンを付加
        $pattern = '(' . $pattern . ')(?![^<]*>)';

        if (P2_MBREGEX_AVAILABLE == 1) {
            $result = @mb_eregi_replace($pattern, $marker, $target);    // Error:false
        } else {
            // UTF-8に変換してから処理する
            $pattern_utf8 = '/' . mb_convert_encoding($pattern, 'UTF-8', 'CP932') . '/iu';
            $target_utf8 = mb_convert_encoding($target, 'UTF-8', 'CP932');
            $result = @preg_replace($pattern_utf8, $marker, $target_utf8);
            $result = mb_convert_encoding($result, 'CP932', 'UTF-8');
        }

        if ($result === false) {
            return $target;
        }
        return $result;
    }

    // }}}
    // {{{ getPatternZenHan()

    /**
     * 全角/半角を（ある程度）区別なくパッチするための正規表現パターンを得る
     */
    static public function getPatternZenHan($pattern)
    {
        $pattern_han = self::getPatternToHan($pattern);

        if ($pattern != $pattern_han) {
            $pattern = $pattern.'|'.$pattern_han;
        }
        $pattern_zen = self::getPatternToZen($pattern);

        if ($pattern != $pattern_zen) {
            $pattern = $pattern.'|'.$pattern_zen;
        }

        return $pattern;
    }

    // }}}
    // {{{ getPatternToHan()

    /**
     * （パターン）文字列を半角にする
     */
    static public function getPatternToHan($pattern, $no_escape = false)
    {
        $kigou = self::getKigouPattern($no_escape);

        // 壊れる
        //$pattern = str_replace($kigou['zen'], $kigou['han'], $pattern);

        if (P2_MBREGEX_AVAILABLE == 1) {
            foreach ($kigou['zen'] as $k => $v) {
                $word_fm = $kigou['zen'][$k];

                /*
                // preg_quote()で2バイト目が0x5B("[")の"ー"なども変換されてしまうので
                // UTF-8にしてから正規表現の特殊文字をエスケープ
                $word_fm = mb_convert_encoding($word_fm, 'UTF-8', 'CP932');
                $word_fm = preg_quote($word_fm);
                $word_fm = mb_convert_encoding($word_fm, 'CP932', 'UTF-8');
                */

                $pattern = mb_ereg_replace($word_fm, $kigou['han'][$k], $pattern);
            }
        }

        //echo $pattern;
        $pattern = mb_convert_kana($pattern, 'rnk');

        return $pattern;
    }

    // }}}
    // {{{ getPatternToZen()

    /**
     * （パターン）文字列を全角にする
     */
    static public function getPatternToZen($pattern, $no_escape = false)
    {
        $kigou = self::getKigouPattern($no_escape);

        // 壊れる
        // $pattern = str_replace($kigou['han'], $kigou['zen'], $pattern);

        if (P2_MBREGEX_AVAILABLE == 1) {
            foreach ($kigou['zen'] as $k => $v) {
                $word_fm = $kigou['han'][$k];

                // preg_quote()で2バイト目が0x5B("[")の"ー"なども変換されてしまうので
                // UTF-8にしてから正規表現の特殊文字をエスケープ
                $word_fm = mb_convert_encoding($word_fm, 'UTF-8', 'CP932');
                $word_fm = preg_quote($word_fm);
                $word_fm = mb_convert_encoding($word_fm, 'CP932', 'UTF-8');

                $pattern = mb_ereg_replace($word_fm, $kigou['zen'][$k], $pattern);
            }
        }

        $pattern = mb_convert_kana($pattern, 'RNKV');

        return $pattern;
    }

    // }}}
    // {{{ getKigouPattern()

    /**
     * 全角/半角の記号パターンを得る
     */
    static public function getKigouPattern($no_escape = false)
    {
        $kigou['zen'] = array('｀', '（', '）', '？', '＃', '＄', '％', '＠', '＜',   '＞', '！',   '＊', '＋', '＆',  '＝', '～', '｜', '｛', '｝', '＿');
        $kigou['han'] = array('`',  '\(', '\)', '\?', '#',  '\$', '%',  '@',  '&lt;', '&gt;', '\!', '\*', '\+', '&amp;', '=', '~', '\|', '\{', '\}', '_');

        // NG ---- $ <
        // str_replace を通した時に、文字が壊れるの回避。。
        //$kigou['zen'] = array('｀', '（', '）', '？', '＃', '％', '＠', '＞', '！',   '＊', '＋', '＆');
        //$kigou['han'] = array('`',  '\(', '\)', '\?', '#',  '%',  '@',  '&gt;', '\!', '\*', '\+', '&amp;');

        if ($no_escape) {
            $kigou['han'] = array_map(function($str){return ltrim($str, "\\\\");}, $kigou['han']);
            /*
            foreach ($kigou['han'] as $k => $v) {
                $kigou['han'][$k] = ltrim($v, '\\');
            }
            */
        }

        return $kigou;
    }

    // }}}
    // {{{ toJavaScript()

    /**
     * Shift_JISの文字列をJavaScriptのUnicode表記(\uhhhh)に変換する
     *
     * ASCIIのprintableな文字からHTMLの特殊文字とバックスラッシュを
     * 除いた範囲の文字はそのままにしておく
     */
    static public function toJavaScript($str, $charset = 'CP932')
    {
        //            "   &   '   <   >   \  DEL
        $xcs = array(34, 38, 39, 60, 62, 92, 127);
        $ucs = array_values(unpack('C*', mb_convert_encoding($str, 'UCS-2', $charset)));
        $len = count($ucs);
        $pos = 0;
        $js = '';

        while ($pos < $len) {
            $ub = $ucs[$pos++];
            $lb = $ucs[$pos++];
            if ($ub == 0 && $lb < 128) {
                if ($lb < 32 || in_array($lb, $xcs)) {
                    $js .= sprintf('\\x%02X', $lb);
                } else {
                    $js .= sprintf('%c', $lb);
                }
            } else {
                $js .= sprintf('\\u%02X%02X', $ub, $lb);
            }
        }

        return $js;
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
