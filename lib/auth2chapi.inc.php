<?php
/**
 * rep2 - 2chログイン
 */

// {{{ authenticate_2chapi()


/**
* 2chAPIの SID を取得する
*
* @return mix 取得できた場合はSIDを返す
*/
    function authenticate_2chapi()
    {
    	global $_conf;

        if ($_conf['2chapi_ssl.auth'])
        {
            $url = 'https://api.2ch.net/v1/auth/';
        } else {
            $url = 'http://api.2ch.net/v1/auth/';
        }

        $CT = time();
        $AppKey = $_conf['2chapi_appkey'];
        $AppName = $_conf['2chapi_appname'];
        $HMKey = $_conf['2chapi_hmkey'];
        $AuthUA = sprintf($_conf['2chapi_ua.auth'],$AppName);
        $login2chID = "";
        $login2chPW = "";
        $message = $AppKey.$CT;
        $HB = hash_hmac("sha256", $message, $HMKey);

        if(empty($AppKey) || empty($AppName) || empty($HMKey)) {
            P2Util::pushInfoHtml("<p>p2 Error: 2ch API の認証に必要な情報が設定されていません。</p>");
            return '';
        }

        try {
            $req = P2Util::getHTTPRequest2($url,HTTP_Request2::METHOD_POST);

            $req->setHeader('User-Agent', $AuthUA);
            $req->setHeader('X-2ch-UA', $AppName);

            $req->addPostParameter('ID', $login2chID);
            $req->addPostParameter('PW', $login2chPW);
            $req->addPostParameter('KY', $AppKey);
            $req->addPostParameter('CT', $CT);
            $req->addPostParameter('HB', $HB);

            // POSTデータの送信
            $res = $req->send();

            $code = $res->getStatus();
            if ($code =! 200) {
                P2Util::pushInfoHtml("<p>p2 Error: HTTP Error({$code})</p>");
            } else {
                $body = $res->getBody();
            }
        } catch (Exception $e) {
            P2Util::pushInfoHtml("<p>p2 Error: 2ch API の認証サーバに接続出来ませんでした。({$e->getMessage()})</p>");
        }

        if(file_exists($_conf['sid2chapi_php'])) {
            unlink($_conf['sid2chapi_php']);
        }

        // 接続失敗ならば
        if (empty($body)) {
            P2Util::pushInfoHtml('<p>p2 info: 2ちゃんねるのAPIを使用するには、PHPの<a href="'.
                    P2Util::throughIme("http://www.php.net/manual/ja/ref.curl.php").
                    '">cURL関数</a>又は<a href="'.
                    P2Util::throughIme("http://www.php.net/manual/ja/ref.openssl.php").
                    '">OpenSSL関数</a>が有効である必要があります。</p>');

            P2Util::pushInfoHtml("<p>p2 error: 2ch API認証に失敗しました。{$curl_msg}</p>");
            return false;
        }

        if (strpos($body, ':') != false)
        {
            $sid = explode(':', $body);

            if($_conf['2chapi_debug_print']==1)
            {
                P2Util::pushInfoHtml($body."<br>".$AuthUA);
            }

            if($sid[0]!='SESSION-ID=Monazilla/1.00') {
                P2Util::pushInfoHtml("<p>p2 Error: レスポンスからSessionIDを取得出来ませんでした。</p>");
                return '';
            }

            $cont = sprintf('<?php $SID2chAPI = %s;', var_export($sid[1], true));
            if (false === file_put_contents($_conf['sid2chapi_php'], $cont, LOCK_EX)) {
                P2Util::pushInfoHtml("<p>p2 Error: {$_conf['sid2chapi_php']} を保存できませんでした。ログイン登録失敗。</p>");
                return '';
            }

            return $sid[1];
        }

        return '';
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
