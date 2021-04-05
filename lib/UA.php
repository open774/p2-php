<?php
// 例えば、クエリーが b=k なら isK() がtrueとなるので、携帯向け表示にしたりする

// {{{ UA

// [todo] enableJS() や enableAjax() も欲しいかも

/**
 * staticメソッドで利用する
 */
class UA
{
    // @see getQueryKey()
    static private $_query_key = 'b';

	// @see setPCQuery() // b=pc
    static private $_pc_query = 'pc';

    // @see setMobileQuery() // b=k
    static private $_mobile_query = 'k';
    
    // @see setIPhoneGroupQuery() // b=i
    static private $_iphonegroup_query = 'i';

    static private $_force_mode = null;

    // {{{ setForceMode()

    /**
     * 強制的にモード（pc, k）を指定する
     * （クエリーをセットするわけではない）
     */
    static public function setForceMode($v)
    {
        self::$_force_mode = $v;
    }

    // }}}
    // {{{ isPC()

    /**
     * UAがPC（非モバイル）ならtrueを返す
     * iPhoneも含んでいるが、いずれ含まなくなる可能性があることに注意。
     * 現在、iPhoneはsetForceMode()でisMobileByQuery()扱いしている。（効力弱めで）
     *
     * @return  boolean
     */
    static public function isPC($ua = null)
    {
        return !self::isMobile($ua);
    }

    // }}}
    // {{{ isK()

    /**
     * isMobile() のエイリアスになっている
     *
     * [plan] 携帯isK()と、モバイルisMobile()は、別のものとして区別した方がいいかな。（isMobile()はisK()を含むものとして）
     * 携帯：画面が小さい。ページの表示容量に制限がある。数字のアクセスキーを使う。
     * モバイル：携帯と同じく画面が小さめだが、フルブラウザで、JavaScriptが使える。
     */
    static public function isK($ua = null)
    {
        return self::isMobile($ua);
    }

    // }}}
    // {{{ isMobile()

    /**
     * UAが携帯表示対象ならtrueを返す
     * isK()と意味を区別する予定があるので、それまでの間は使わないでおく（現時点、使っていない）
     * （isMobileByQuery()などは使われているが）
     * isM()にしたい気も。
     *
     * @params  string  $ua  UAを指定するなら
     * @return  boolean
     */
    static public function isMobile($ua = null)
    {
        static $cache_ = null;

        // 強制指定があれば
        if (isset(self::$_force_mode)) {
            // ここはキャッシュしない
            return (self::$_force_mode == self::$_mobile_query);
        }

        // 引数のUAが無指定なら、クエリー指定を参照
        if (is_null($ua)) {
            if (self::getQueryValue()) {
                return self::isMobileByQuery();
            }
        }

        // 引数のUAが無指定なら、キャッシュ有効
        if (is_null($ua) and !is_null($cache_)) {
            return $cache_;
        }

        $isMobile = false;
        if ($nuam = self::getNet_UserAgent_Mobile($ua)) {
            if (!$nuam->isNonMobile()) {
                $isMobile = true;
            }
        }

        /*
        // NetFront系（含むPSP）もモバイルに
        if (!$isMobile) {
            $isMobile = self::isNetFront($ua);
        }

        // Nintendo DSもモバイルに
        if (!$isMobile) {
            $isMobile = self::isNintendoDS($ua);
        }
        */

        // 引数のUAが無指定なら、キャッシュ保存
        if (is_null($ua)) {
            $cache_ = $isMobile;
        }

        return $isMobile;
    }

    // }}}
    // {{{ isIPhoneGroup()

    /**
     * UAがiPhone, iPod touchならtrueを返す。
     *
     * @param   string   $aua  UAを指定するなら
     * @return  boolean
     */
    static public function isIPhoneGroup($aua = null)
    {
        static $cache_ = null;

        // 強制指定があればチェック
        if (isset(self::$_force_mode)) {
            // 移行の便宜上、効力を弱めている
            // return ($GLOBALS['_UA_force_mode'] == $GLOBALS['_UA_iphonegroup_query']);
            if (self::$_force_mode == self::$_iphonegroup_query) {
                return true;
            }
        }

        $ua = $aua;

        // UAの引数が無指定なら、
        if (is_null($aua)) {
            // クエリー指定を参照
            if (self::getQueryValue()) {
                //// 後方互換上、b=kでもiPhoneとみなすことを許す。
                //if (!self::isMobileByQuery()) {
                    return self::isIPhoneGroupByQuery();
                //}
            }

            // （キャッシュするほどではないかも）
            // 引数のUAが無指定なら、キャッシュ有効
            if (!is_null($cache_)) {
                return $cache_;
            }

            // クライアントのUAで判別
            if (isset($_SERVER['HTTP_USER_AGENT'])) {
                $ua = $_SERVER['HTTP_USER_AGENT'];
            }
        }

        $isiPhoneGroup = false;

        // iPhone
        // Mozilla/5.0 (iPhone; U; CPU like Mac OS X; en) AppleWebKit/420+ (KHTML, like Gecko) Version/3.0 Mobile/1A543a Safari/419.3

        // iPod touch
        // Mozilla/5.0 (iPod; U; CPU like Mac OS X; ja-jp) AppleWebKit/420.1 (KHTML, like Gecko) Version/3.0 Mobile/3A110a Safari/419.3
        if (preg_match('/iP(?:hone|[ao]d)/', $ua) || self::isAndroid($ua)) {
            $isiPhoneGroup = true;
        }

        // UAの引数が無指定なら、キャッシュ保存
        if (is_null($aua)) {
            $cache_ = $isiPhoneGroup;
        }
        return $isiPhoneGroup;
    }

    // }}}
    // {{{ isPCByQuery()

    /**
     * クエリーがPCを指定しているならtrueを返す
     *
     * @return  boolean
     */
    static private function isPCByQuery()
    {
        $qv = self::getQueryValue();
        if (isset($qv) && $qv == self::getPCQuery()) {
            return true;
        }
        return false;
    }

    // }}}
    // {{{ isMobileByQuery()

    /**
     * クエリーが携帯を指定しているならtrueを返す
     *
     * @return  boolean
     */
    static private function isMobileByQuery()
    {
        $qv = self::getQueryValue();
        if (isset($qv) && $qv == self::getMobileQuery()) {
            return true;
        }
        return false;
    }

    /**
     * クエリーがIPhoneGroupを指定しているならtrueを返す
     *
     * @return  boolean
     */
    static private function isIPhoneGroupByQuery()
    {
        $qv = self::getQueryValue();
        if (isset($qv) && $qv == self::getIPhoneGroupQuery()) {
            return true;
        }
        return false;
    }

    // }}}
    // {{{ getQueryValue()

    /**
     * 表示モード指定用のクエリー値を取得する
     *
     * @return  string|null
     */
    static public function getQueryValue($key = null)
    {
        if (is_null($key)) {
            if (!$key = self::getQueryKey()) {
                return null;
            }
        }

        $r = null;
        if (isset($_REQUEST[$key])) {
            if (preg_match('/^\\w+$/', $_REQUEST[$key])) {
                $r = $_REQUEST[$key];
            }
        }
        return $r;
    }

    // }}}
    // {{{ getQueryKey()

    /**
     * @return  string
     */
    static public function getQueryKey()
    {
        return self::$_query_key;
    }

    // }}}
    // {{{ setPCQuery()

    /**
     * @param   string  $pc  default is 'pc'
     * @return  void
     */
    static public function setPCQuery($pc)
    {
        self::$_pc_query = $pc;
    }

    // }}}
    // {{{ getPCQuery()

    /**
     * @return  string
     */
    static public function getPCQuery()
    {
        return self::$_pc_query;
    }

    // }}}
    // {{{ setMobileQuery()

    /**
     * @param   string  $k  default is 'k'
     * @return  void
     */
    static public function setMobileQuery($k)
    {
        self::$_mobile_query = $k;
    }

    // }}}
    // {{{ getMobileQuery()

    /**
     * @return  string
     */
    static public function getMobileQuery()
    {
        return self::$_mobile_query;
    }

    // }}}
    // {{{ setIPhoneGroupQuery()

    /**
     * @param   string  $i  default is 'i'
     * @return  void
     */
    static public function setIPhoneGroupQuery($i)
    {
        self::$_iphonegroup_query = $i;
    }

    // }}}
    // {{{ getIPhoneGroupQuery()

    /**
     * @return  string
     */
    static public function getIPhoneGroupQuery()
    {
        return self::$_iphonegroup_query;
    }

    // }}}
    // {{{ getNet_UserAgent_Mobile()

    /**
     * Net_UserAgent_Mobile::singleton() の結果を取得する。
     * REAR Error は false に変換される。
     *
     * @param   string  $ua
     * @return  Net_UserAgent_Mobile|false
     */
    static public function getNet_UserAgent_Mobile($ua = null)
    {
        static $cache_ = null;

        if (is_null($ua) and !is_null($cache_)) {
            return $cache_;
        }

        if (!is_null($ua)) {
            $nuam = Net_UserAgent_Mobile::factory($ua);
        } else {
            $nuam = (new Net_UserAgent_Mobile)->singleton();
        }

        if (PEAR::isError($nuam)) {
            trigger_error($nuam->toString, E_USER_WARNING);
            $return = false;

        } elseif (!$nuam) {
            $return = false; // null

        } else {
            $return = $nuam;
        }

        if (is_null($ua)) {
            $cache_ = $return;
        }

        return $return;
    }

    // }}}
    // {{{ isNetFront()

    /**
     * UAがNetFront（携帯、PDA、PSP）ならtrueを返す
     *
     * @param   string   $ua  UAを指定するなら
     * @return  boolean
     */
    static public function isNetFront($ua = null)
    {
        if (is_null($ua) and isset($_SERVER['HTTP_USER_AGENT'])) {
            $ua = $_SERVER['HTTP_USER_AGENT'];
        }

        if (preg_match('/(NetFront|AVEFront\/|AVE-Front\/)/', $ua)) {
            return true;
        }
        if (self::isPSP()) {
            return true;
        }
        return false;
    }

    // }}}
    // {{{ isPSP()

    /**
     * UAがPSPならtrueを返す。NetFront系らしい。
     *
     * @param   string   $ua  UAを指定するなら
     * @return  boolean
     */
    static public function isPSP($ua = null)
    {
        if (is_null($ua) and isset($_SERVER['HTTP_USER_AGENT'])) {
            $ua = $_SERVER['HTTP_USER_AGENT'];
        }

        // Mozilla/4.0 (PSP (PlayStation Portable); 2.00)
        if (false !== strpos($ua, 'PlayStation Portable')) {
            return true;
        }
        return false;
    }

    // }}}
    // {{{ isNintendoDS()

    /**
     * UAがNintendo DSならtrueを返す。
     *
     * @param   string   $ua  UAを指定するなら
     * @return  boolean
     */
    static public function isNintendoDS($ua = null)
    {
        if (is_null($ua) and isset($_SERVER['HTTP_USER_AGENT'])) {
            $ua = $_SERVER['HTTP_USER_AGENT'];
        }

        // Mozilla/4.0 (compatible; MSIE 6.0; Nitro) Opera 8.5 [ja]
        if (false !== strpos($ua, ' Nitro')) {
            return true;
        }
        return false;
    }

    // }}}
    // {{{ isAndroid()

    /**
     * UAがAndroidならtrueを返す。
     *
     * @param   string   $ua  UAを指定するなら
     * @return  boolean
     */
    static public function isAndroid($ua = null)
    {
        if (is_null($ua) and isset($_SERVER['HTTP_USER_AGENT'])) {
            $ua = $_SERVER['HTTP_USER_AGENT'];
        }
        if (!$ua) {
            return false;
        }
        // シミュレータ
        // Mozilla/5.0 (Linux; U; Android 1.0; en-us; generic) AppleWebKit/525.10+ (KHTML, like Gecko) Version/3.0.4 Mobile Safari/523.12.2
        // T-mobile G1
        // Mozilla/5.0 (Linux; U; Android 1.0; en-us; dream) AppleWebKit/525.10+ (KHTML, like Gecko) Version/3.0.4 Mobile Safari/523.12.2
        // genericとdreamが異なる
        if (false !== strpos($ua, 'Android')) {
            return true;
        }
        return false;
    }

    // }}}
    // {{{ isAndroidWebKit()

    /**
     * UAがAndroid（でWebkit）ならtrueを返す。
     *
     * @param   string   $ua  UAを指定するなら
     * @return  boolean
     */
    static public function isAndroidWebKit($ua = null)
    {
        if (is_null($ua) and isset($_SERVER['HTTP_USER_AGENT'])) {
            $ua = $_SERVER['HTTP_USER_AGENT'];
        }
        if (!$ua) {
            return false;
        }
        // シミュレータ
        // Mozilla/5.0 (Linux; U; Android 1.0; en-us; generic) AppleWebKit/525.10+ (KHTML, like Gecko) Version/3.0.4 Mobile Safari/523.12.2
        // T-mobile G1
        // Mozilla/5.0 (Linux; U; Android 1.0; en-us; dream) AppleWebKit/525.10+ (KHTML, like Gecko) Version/3.0.4 Mobile Safari/523.12.2
        // genericとdreamが異なる
        if (false !== strpos($ua, 'Android') && false !== strpos($ua, 'WebKit')) {
            return true;
        }
        return false;
    }

    // }}}
    // {{{ isAndroidWebKit()

    /**
     * UAがAndroid（でFirefox）ならtrueを返す。
     *
     * @param   string   $ua  UAを指定するなら
     * @return  boolean
     */
    static public function isAndroidFirefox($ua = null)
    {
        if (is_null($ua) and isset($_SERVER['HTTP_USER_AGENT'])) {
            $ua = $_SERVER['HTTP_USER_AGENT'];
        }
        if (!$ua) {
            return false;
        }
        if (false !== strpos($ua, 'Android') && false !== strpos($ua, 'Firefox')) {
            return true;
        }
        return false;
    }

    // }}}
    // {{{ isSafariGroup()

    /**
     * UAがSafari系なら true を返す
     *
     * @param   string   $ua  UAを指定するなら
     * @return  boolean
     */
    static public function isSafariGroup($ua = null)
    {
        if (is_null($ua) and isset($_SERVER['HTTP_USER_AGENT'])) {
            $ua = $_SERVER['HTTP_USER_AGENT'];
        }

        return (boolean)preg_match('/Safari|AppleWebKit|Konqueror/', $ua);
    }

    // }}}
    // {{{ isIModeBrowser2()

    /**
     * UAがiモードブラウザ2.xなら true を返す
     *
     * @param   string   $ua  UAを指定するなら
     * @return  boolean
     */
    static public function isIModeBrowser2($ua = null)
    {
        if (is_null($ua) and isset($_SERVER['HTTP_USER_AGENT'])) {
            $ua = $_SERVER['HTTP_USER_AGENT'];
        }

        if (preg_match('!^DoCoMo/2\\.\\d \\w+\\(c(\\d+)!', $ua, $matches)) {
            // キャッシュ500KB以上ならiモードブラウザ2.xとみなす
            if (500 <= (int)$matches[1]) {
                return true;
            }
        }

        return false;
    }

    // }}}
    // {{{ isClientOSWindowsCE()

    /**
     * ブラウザがWindows CEで動作するものならtrueを返す
     */
    static public function isClientOSWindowsCE()
    {
        return (strpos($_SERVER['HTTP_USER_AGENT'], 'Windows CE') !== false);
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
