<?php

// {{{ BrdMenu

/**
 * rep2 - ボードメニュークラス for menu.php
 */
class BrdMenu
{
    // {{{ properties

    public $categories;    // クラス BrdMenuCate のオブジェクトを格納する配列
    public $num;           // 格納された BrdMenuCate オブジェクトの数
    public $format;        // html形式か、brd形式か("html", "brd")
    public $cate_match;    // カテゴリーマッチ形式
    public $ita_match;     // 板マッチ形式
    public $matches;       // マッチした BrdMenuIta オブジェクトを格納する配列

    // }}}
    // {{{ constructor

    public function __construct()
    {
        $this->categories = array();
        $this->num = 0; 
        $this->matches = array();
    }

    // }}}
    // {{{ addBrdMenuCate()

    /**
     * カテゴリーを追加する
     */
    public function addBrdMenuCate(BrdMenuCate $aBrdMenuCate)
    {
        $this->categories[] = $aBrdMenuCate;
        $this->num++;
    }

    // }}}
    // {{{ setBrdMatch()

    /**
    * パターンマッチの形式を登録する
    */
    public function setBrdMatch($brdName)
    {
        // html形式
        if (preg_match('/(html?|cgi)$/', $brdName)) {
            $this->format = 'html';
            $this->cate_match = '{<B>(.+)</B><BR>.*$}i';
            $this->ita_match = '{^<A HREF="?(https?://(.+)/([^/]+)/([^/]+\\.html?)?)"?( target="?_blank"?)?>(.+)</A>(<br>)?$}i';
        // brd形式
        } else {
            $this->format = 'brd';
            $this->cate_match = "/^(.+)\t([0-9])\$/";
            $this->ita_match = "/^\t?(.+)\t(.+)\t(.+)\$/";
        }
    }

    // }}}
    // {{{ setBrdList()

    /**
    * データを読み込んで、カテゴリと板を登録する
    */
    public function setBrdList($data)
    {
        global $_conf;

        if (empty($data)) { return false; }

        $do_filtering = !empty($GLOBALS['words_fm']);

        // 除外URLリスト
        $not_bbs_list = array("http://members.tripod.co.jp/Backy/del_2ch/");

        foreach ($data as $v) {
            $v = rtrim($v);

            // カテゴリを探す
            if (preg_match($this->cate_match, $v, $matches)) {
                $aBrdMenuCate = new BrdMenuCate($matches[1]);
                if ($this->format == 'brd') {
                    $aBrdMenuCate->is_open = $matches[2];
                }
                $this->addBrdMenuCate($aBrdMenuCate);

            // 板を探す
            } elseif (preg_match($this->ita_match, $v, $matches)) {
                // html形式なら除外URLを外す
                if ($this->format == 'html') {
                    foreach ($not_bbs_list as $not_a_bbs) {
                        if ($not_a_bbs == $matches[1]) { continue 2; }
                    }
                }
                $aBrdMenuIta = new BrdMenuIta();
                // html形式
                if ($this->format == 'html') {
                    $aBrdMenuIta->host = $matches[2];
                    $aBrdMenuIta->bbs = $matches[3];
                    $itaj_match = $matches[6];
                // brd形式
                } else {
                    $aBrdMenuIta->host = $matches[1];
                    $aBrdMenuIta->bbs = $matches[2];
                    $itaj_match = $matches[3];
                }
                $aBrdMenuIta->setItaj(rtrim($itaj_match));

                // {{{ 板検索マッチ

                // and検索
                if ($do_filtering) {

                    $no_match = false;

                    foreach ($GLOBALS['words_fm'] as $word_fm_ao) {
                        $target = $aBrdMenuIta->itaj."\t".$aBrdMenuIta->bbs;
                        if (!StrCtl::filterMatch($word_fm_ao, $target)) {
                            $no_match = true;
                        }
                    }

                    if (!$no_match) {
                        $this->categories[$this->num-1]->ita_match_num++;
                        $GLOBALS['ita_mikke']['num']++;
                        $GLOBALS['ita_mikke']['host'] = $aBrdMenuIta->host;
                        $GLOBALS['ita_mikke']['bbs'] = $aBrdMenuIta->bbs;
                        $GLOBALS['ita_mikke']['itaj_en'] = $aBrdMenuIta->itaj_en;

                        // マーキング
                        if ($_conf['ktai'] && is_string($_conf['k_filter_marker'])) {
                            $aBrdMenuIta->itaj_ht = StrCtl::filterMarking($GLOBALS['word_fm'], $aBrdMenuIta->itaj, $_conf['k_filter_marker']);
                        } else {
                            $aBrdMenuIta->itaj_ht = StrCtl::filterMarking($GLOBALS['word_fm'], $aBrdMenuIta->itaj);
                        }

                        // マッチマーキングなければ（bbsでマッチしたとき）、全部マーキング
                        if ($aBrdMenuIta->itaj_ht == $aBrdMenuIta->itaj) {
                            $aBrdMenuIta->itaj_ht = '<b class="filtering">'.$aBrdMenuIta->itaj_ht.'</b>';
                        }

                        $this->matches[] = $aBrdMenuIta;

                    // 検索が見つからなくて、さらに携帯の時
                    } else {
                        if ($_conf['ktai']) {
                            continue;
                        }
                    }
                }

                // }}}

                if ($this->num) {
                    $this->categories[$this->num-1]->addBrdMenuIta($aBrdMenuIta);
                }
            }
        }
    }

    // }}}
    // {{{ makeBrdFile()

    /**
    * brdファイルを生成する
    *
    * @return    string    brdファイルのパス
    */
    public function makeBrdFile($cachefile)
    {
        global $_conf, $word;

        $p2brdfile = $cachefile.".p2.brd";
        FileCtl::make_datafile($p2brdfile);
        $data = FileCtl::file_read_lines($cachefile);
        $cont = '';
        $this->setBrdMatch($cachefile); // パターンマッチ形式を登録
        $this->setBrdList($data);       // カテゴリーと板をセット
        if ($this->categories) {
            foreach ($this->categories as $cate) {
                if ($cate->num > 0) {
                    $cont .= $cate->name."\t0\n";
                    foreach ($cate->menuitas as $mita) {
                        $cont .= "\t{$mita->host}\t{$mita->bbs}\t{$mita->itaj}\n";
                    }
                }
            }
        }

        if ($cont) {
            if (FileCtl::file_write_contents($p2brdfile, $cont) === false) {
                p2die("{$p2brdfile} を更新できませんでした");
            }
            return $p2brdfile;
        } else {
            if (!$word) {
                P2Util::pushInfoHtml("<p>p2 error: {$cachefile} から板メニューを生成することはできませんでした。</p>");
            }
            return false;
        }
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
