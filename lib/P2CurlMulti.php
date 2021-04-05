<?php
// {{{ CONSTANTS

class P2CurlMulti
{
    private $mh;
    private $ch;
    private $file_update;
    private $mode;

    private function __construct() {
        $this->mh = curl_multi_init();
        $this->ch = array();
        $this->file_update = array();
    }

    private function __destruct() {
        foreach ($this->ch as $ch_array) {
            curl_multi_remove_handle($this->mh, $ch_array);
            curl_close($ch_array);
        }
        curl_multi_close($this->mh);
    }


    private function add($subjects, $force = false) {
        global $_conf;

        if(empty($subjects)){ return; }

        $time = time() - $_conf['sb_dl_interval'];
        $isOldFile = array();

        foreach ($subjects as $key => $subject) {
            list($host, $bbs) = explode("_", $key);

            if ($_conf['2ch_ssl.subject']) {
                $url = "https://{$host}/{$bbs}/subject.txt";
            } else {
                $url = "http://{$host}/{$bbs}/subject.txt";
            }
            $file = P2Util::datDirOfHostBbs($host, $bbs) . 'subject.txt';

            $isOldFile[$key] = false;
            if (!$force && file_exists($file) && $time <= filemtime($file)) {
                $isOldFile[$key] = true;
                continue;
            }

            $this->ch[$key] = curl_init();

            $this->file_update[$key] = file_exists($file) ? filemtime($file) : 0;

            // dat取得用header生成
            $header = array();
            $header["If-Modified-Since"] = gmdate('D, d M Y H:i:s T', $this->file_update[$key]);
            $header["Connection"] = 'close';

            curl_setopt($this->ch[$key], CURLOPT_URL, $url);
            curl_setopt($this->ch[$key], CURLOPT_RETURNTRANSFER, true);
            curl_setopt($this->ch[$key], CURLOPT_TIMEOUT, $_conf['http_read_timeout']);
            curl_setopt($this->ch[$key], CURLOPT_CONNECTTIMEOUT, $_conf['http_conn_timeout']);
            curl_setopt($this->ch[$key], CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($this->ch[$key], CURLOPT_TIMECONDITION, CURL_TIMECOND_IFMODSINCE);
            curl_setopt($this->ch[$key], CURLOPT_FILETIME, true);
            curl_setopt($this->ch[$key], CURLOPT_HTTPHEADER, $header);
            curl_setopt($this->ch[$key], CURLINFO_HEADER_OUT, true);
            curl_setopt($this->ch[$key], CURLOPT_HEADER, true);
            curl_setopt($this->ch[$key], CURLOPT_MAXCONNECTS, $_conf['expack.curl_per_host']);

            // User-Agent
            if(P2HostMgr::isHost2chs($host) && !P2HostMgr::isNotUse2chsAPI($host) && $_conf['2chapi_use']){
                $user_agent = sprintf ($_conf['2chapi_ua.read'], $_conf['2chapi_appname']);
            } else {
                $user_agent = P2Commun::getP2UA(true, P2HostMgr::isHost2chs($purl['host']));
            }
            curl_setopt($this->ch[$key], CURLOPT_USERAGENT, $user_agent);

            // プロキシ
            if ($_conf['tor_use'] && P2HostMgr::isHostTor($purl['host'], 0)) { // Tor(.onion)はTor用の設定をセット
                $tor_user_info = sprintf("%s%s@", $_conf['tor_proxy_user'], empty($_conf['tor_proxy_password']) ? "" : ":{$_conf['tor_proxy_password']}");
                $tor_address   = "{$_conf['tor_proxy_host']}:{$_conf['tor_proxy_port']}";
                $address = sprintf("http://%s%s", strpos($tor_user_info, "@") === 0 ? "" : $tor_user_info, $tor_address);

                curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, 1);
                curl_setopt($ch, CURLOPT_PROXY, $address);

                if($_conf['tor_proxy_mode'] == 'socks5'){
                    curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
                }

            } elseif ($_conf['proxy_use']) {
                $proxy_user_info = sprintf("%s%s@", $_conf['proxy_user'], empty($_conf['proxy_password']) ? "" : ":{$_conf['proxy_password']}");
                $proxy_address   = "{$_conf['proxy_host']}:{$_conf['proxy_port']}";
                $address = sprintf("http://%s%s", strpos($proxy_user_info, "@") === 0 ? "" : $proxy_user_info, $proxy_address);

                curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, 1);
                curl_setopt($ch, CURLOPT_PROXY, $address);

                if($_conf['proxy_mode'] == 'socks5'){
                    curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
                }
            }

            curl_multi_add_handle($this->mh, $this->ch[$key]);
        }
    }

    private function execute() {
        global $_conf;

        if(is_null($this->mh) && is_null($this->ch)){
            return;
        }

        // execute
        do {
            $stat = curl_multi_exec($this->mh, $running);
        } while ($stat === CURLM_CALL_MULTI_PERFORM);

        // check
        // 暫定で残す(not start Download なら終了でも?)
//      if (!$running || $stat !== CURLM_OK) {
//      //  throw new RuntimeException("$running $stat");
//          error_log("not start download. please check. running:[$running], stat:[$stat]\n");
//      }

        // wait
        do {
            switch (curl_multi_select($this->mh, $_conf['http_conn_timeout'] + $_conf['http_read_timeout'])) {
                case -1: // selectに失敗するケースがあるらしい https://bugs.php.net/bug.php?id=61141
                    usleep(10);
                    do{
                        $stat = curl_multi_exec($this->mh, $running);
                    } while ($stat === CURLM_CALL_MULTI_PERFORM);
                    continue 2;

                case 0: //timeout
                    continue 2;

                default:
                    //何か変化があった
                    do{
                        $stat = curl_multi_exec($this->mh, $running);
                    } while ($stat === CURLM_CALL_MULTI_PERFORM);
            }
        } while ($running);
    }

    private function getResult() {

        $eucjp2sjis = null;

        foreach ($this->ch as $key => $ch_array) {
            list($host, $bbs) = explode("_", $key);

            if ($isOldFile[$key]) {
                continue;
            }

            $file = P2Util::datDirOfHostBbs($host, $bbs) . 'subject.txt';

            if(is_null($this->mh)){
                return;
            }

            if(empty($ch_array)){
                continue;
            }

            $tmp = curl_getinfo($ch_array);
            $tmp += array("before_time" =>  $this->file_update[$key], "after_time" => empty($tmp['filetime']) ? time() : $tmp['filetime']);
        //  $result[$key] = $tmp;

            $data = curl_multi_getcontent($ch_array);
            $header_size = $tmp['header_size'];

            if (P2HostMgr::isHostJbbsShitaraba($host) || P2HostMgr::isHostBe2chs($host)) {
                $data = mb_convert_encoding($data, 'CP932', 'CP51932');
            }

            // 304が来なかったとき用
            if($tmp['http_code']  != "304" && $tmp['before_time'] <= $tmp['after_time']){
                $body   = substr($data, $header_size);
                if (file_put_contents($file, $body) === false) {
                    error_log("cannot write file.[$file]\n");
                }
            }
        }
    }

    // {{{ fetchSubjectTxt()

    /**
     * subject.txtを一括ダウンロード&保存する
     *
     * @param array|string $subjects
     * @param bool $force
     * @return void
     */
    static public function fetchSubjectTxt($subjects, $force = false)
    {
        global $_conf;

        $makeIdFormat = "%s_%s";

        // {{{ ダウンロード対象を設定

        // お気に板等の.idx形式のファイルをパース
        if (is_string($subjects)) {
            $lines = FileCtl::file_read_lines($subjects, FILE_IGNORE_NEW_LINES);
            if (!$lines) {
                return;
            }

            $subjects = array();

            foreach ($lines as $l) {
                $la = explode('<>', $l);
                if (count($la) < 12) {
                    continue;
                }

                $host = $la[10];
                $bbs = $la[11];
                if ($host === '' || $bbs === '') {
                    continue;
                }

                $key = sprintf($makeIdFormat, $host, $bbs);
                if (isset($subjects[$key])) {
                    continue;
                }

                $subjects[$key] = array($host, $bbs);
            }

        // [host, bbs] の連想配列を検証
        } elseif (is_array($subjects)) {
            $originals = $subjects;
            $subjects = array();

            foreach ($originals as $s) {
                if (!is_array($s) || !isset($s['host']) || !isset($s['bbs'])) {
                    continue;
                }

                $key = sprintf($makeIdFormat, $s['host'], $s['bbs']);
                if (isset($subjects[$key])) {
                    continue;
                }

                $subjects[$key] = array($s['host'], $s['bbs']);
            }

        // 上記以外
        } else {
            return;
        }

        if (!count($subjects)) {
            return;
        }

        // }}}
        // {{{

        ksort($subjects);

        $self = new self;

        // 各 subject.txt へのリクエストをキューに追加
        $self->add($subjects, $force);

        // ダウンロードスタート
        $self->execute();

        // 各 subject.txt を保存
        $self->getResult();

        // }}}

        return ;
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
