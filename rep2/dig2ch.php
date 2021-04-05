<?php
// {{{ dig2chsearch()
function dig2chsearch($query)
{
    global $_conf;

    parse_str($query, $query_arry);

    //$query_q = preg_replace('/(\s+)/' , '\+' ,$query_arry['q']);
    $query_arry['q'] = urlencode($query_arry['q']);

    $url = 'https://dig.5ch.net/' . '?AndOr=' . $query_arry['AndOr'] . '&maxResult=' . $query_arry['maxResult'] . '&atLeast=1&Sort=' . $query_arry['Sort'] . '&Link=1&Bbs=all&924=' . $query_arry['924'] . '&json=1&keywords=' . $query_arry['q'];

    try {
        $req = P2Commun::createHTTPRequest ($url, HTTP_Request2::METHOD_GET);
        // $req->setHeader('User-Agent', $_SERVER['HTTP_USER_AGENT']); やっぱMonazilla名乗っといた方が良さそうか
        $req->setHeader('Accept-Charset', 'utf-8');
        $req->setHeader('Cache-Control', 'no-cache');
        $req->setHeader('Accept', 'application/json');

        $response = P2Commun::getHTTPResponse($req);

        $code = $response->getStatus();
        if ($code != 200) {
            p2die("HTTP Error - {$code}");
        }

        $body = $response->getBody();
    } catch (Exception $e) {
        p2die($e->getMessage());
    }

    // 先方の鯖で何か障害が発生したらJSONにHTMLのコメントが混ざるのでその対策
    if (strpos($body,"<!--") !== false)
    {
        $body = preg_replace("/<\!--[\s\S]*?-->/", "", $body);
    }

    // formタグが混入することがあるので除去
    if (strpos($body,"<form") !== false)
    {
        $body = preg_replace("/<form[\s\S]*?form>/", "", $body);
    }

    $jsontest1 = json_decode($body, true);

    //mb_convert_variables('SHIFT-JIS','UTF-8',$jsontest1);

    if ($jsontest1 === NULL) {

        $jsonerror = "";
        switch (json_last_error()) {
            case JSON_ERROR_NONE:
                $jsonerror = ' - No errors';
                break;
            case JSON_ERROR_DEPTH:
                $jsonerror = ' - Maximum stack depth exceeded';
                break;
            case JSON_ERROR_STATE_MISMATCH:
                $jsonerror = ' - Underflow or the modes mismatch';
                break;
            case JSON_ERROR_CTRL_CHAR:
                $jsonerror = ' - Unexpected control character found';
                break;
            case JSON_ERROR_SYNTAX:
                $jsonerror = ' - Syntax error, malformed JSON';
                break;
            case JSON_ERROR_UTF8:
                $jsonerror = ' - Malformed UTF-8 characters, possibly incorrectly encoded';
                break;
            default:
                $jsonerror = ' - Unknown error';
                break;
        }
        if (true) { // 本来はconf_admin.phpで切り替えることが出来るようにするが強制ON
            echo "<b>PHPが動作しているOSのuname</b><br>". php_uname() ."<br>";
            echo "<b>PHPのバージョン</b><br>".  phpversion() ."<br>";
            if ($_conf['proxy_use'])
            {
                echo '<font color="red"><b>この環境は串が設定されています(2chとの通信に介入する串を使用している場合はおま環)</b></font><br>';
                echo "{$_conf['proxy_host']}:{$_conf['proxy_port']}<br>";
            }
            echo "<b>dig2chに送信したURL(ブラウザでアクセスを試してみてください。)</b><br>{$url}<br>";
            // 表示のためSJIS化
            $body = mb_convert_encoding($body, "SJIS", "UTF-8");
            echo '<b>dig2chからのレスポンス(http://jsonlint.com/ でエラーが無ければおま環)</b><br><textarea readonly rows="30" cols="100" wrap="off">'.$body.'</textarea><br>';
            echo '<b>HTTP_Requestのvar_dump</b><br>';
            var_dump($req);
            echo '<b>HTTP_Responseのvar_dump</b><br>';
            var_dump($response);
        }
        p2die("検索結果の解析に失敗しました".$jsonerror);
    } else {
        unset ($body);
    }

    $boards = array();
    $hits = array();
    $names = array();
    foreach ($jsontest1[result] as $jsontest2) {
        $result['threads'][$n1] = new stdClass;
        $result['threads'][$n1]->title = $jsontest2[subject];
        $result['threads'][$n1]->host = $jsontest2[server];
        $result['threads'][$n1]->bbs = $jsontest2[bbs];
        $result['threads'][$n1]->tkey = $jsontest2[key];
        $result['threads'][$n1]->resnum = $jsontest2[resno];
        $result['threads'][$n1]->ita = $jsontest2[ita];
        $result['threads'][$n1]->dayres = $jsontest2[ikioi];
        $n1++;
        $bkey = md5($jsontest2['server'].'-'.$jsontest2['bbs'].'-'.$jsontest2['ita']);
        if (! isset($boards[$bkey])) {
            $board = new stdClass;
            $board->host = $jsontest2['server'];
            $board->bbs = $jsontest2['bbs'];
            $names[$bkey] = $board->name = $jsontest2['ita'];
            $hits[$bkey] = $board->hits = 1;
            $boards[$bkey] = $board;
        } else {
            $hits[$bkey] = ++$boards[$bkey]->hits;
            $names[$bkey] = $boards[$bkey]->name;
        }
    }
    $result['modified'] = $response->getHeader('Date');
    $result['profile']['regex'] = '/(' . $jsontest1[query] .')/i';
    $result['profile']['hits'] = $jsontest1[found];
    $result['profile']['cm0'] = str_replace("a href=" , "a target=\"_blank\" href=", $jsontest1[cm0]);
    if (strstr($result['profile']['cm0'] , "rounin")) { $result['profile']['cm0'] = str_replace("src=\"" , "src=\"http://dig.2ch.net", $result['profile']['cm0']);}
    $result['profile']['cm0'] = str_replace("<br></a>" , "</a>", $result['profile']['cm0']);

    $result['profile']['cm1'] = str_replace("a href=" , "a target=\"_blank\" href=", $jsontest1[cm1]);
    if (strstr($result['profile']['cm1'] , "rounin")) { $result['profile']['cm1'] = str_replace("src=\"" , "src=\"http://dig.2ch.net", $result['profile']['cm1']);}
    $result['profile']['cm1'] = str_replace("<br></a>" , "</a>", $result['profile']['cm1']);

    $result['profile']['cm2'] = str_replace("a href=" , "a target=\"_blank\" href=", $jsontest1[cm2]);
    if (strstr($result['profile']['cm2'] , "rounin")) { $result['profile']['cm2'] = str_replace("src=\"" , "src=\"http://dig.2ch.net", $result['profile']['cm2']);}
    $result['profile']['cm2'] = str_replace("<br></a>" , "</a>", $result['profile']['cm2']);
    array_multisort($hits, SORT_DESC, $names, $boards);
    $result['profile']['boards'] = $boards;
    unset($boards,$hits,$names);

    return $result;
}

