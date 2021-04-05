<?php
function find5ch_search($query)
{
    parse_str($query, $query_arry);

    $q = $query_arry['q'];

    $base_url = 'https://find.5ch.net';
    $endpoint = $base_url . '/search?q=' . urlencode($q);
    $referer = $endpoint;

    try {
        $req = P2Commun::createHTTPRequest($endpoint, HTTP_Request2::METHOD_GET);
        $req->setHeader('Referer', $referer);

        $response = P2Commun::getHTTPResponse($req);

        $code = $response->getStatus();
        if ($code != 200) {
            p2die("HTTP Error - {$code}");
        }

        $body = $response->getBody();
    } catch (Exception $e) {
        p2die($e->getMessage());
    }

    mb_convert_variables('SHIFT-JIS', 'UTF-8', $body);

    // FIXME ‘½­‚Ì\‘¢•Ï‰»‚É‘Ï‚¦‚ç‚ê‚é‚æ‚¤‚È³‹K•\Œ»‚ÖC³
    $re = '~<div class="list_line">\n?<a class="list_line_link" href="(?P<thread_url>.+)">\n?<div class="list_line_link_title">(?P<thread_name>.+) \((?<thread_res_count>\d+)\)</div>\n?</a>\n?<div class="list_line_info">\n?<div class="list_line_info_container list_line_info_container-board"><a href="(?P<board_url>.+)">(?<board_name>.+)</a></div>\n?<div class="list_line_info_container">(?<created_at>.+)</div>\n?<div class="list_line_info_container list_line_info_container-danger">(?P<thread_dayres>.+)/“ú</div>\n?</div>~m';
    preg_match_all($re, $body, $threads, PREG_SET_ORDER);

    mb_convert_variables('UTF-8', 'SHIFT-JIS', $threads);

    $result = array();
    $boards = array();
    $hits = array();
    $names = array();

    foreach ($threads as $n => $t) {
        $host = parse_url($t['thread_url'], PHP_URL_HOST);
        $thread_key = array_slice(explode('/', $t['thread_url']), -1, 1)[0];
        $bbs = array_slice(explode('/', $t['board_url']), -2, 1)[0];

        $result['threads'][$n] = new stdClass;
        $result['threads'][$n]->title = $t['thread_name'];
        $result['threads'][$n]->host = $host;
        $result['threads'][$n]->bbs = $bbs;
        $result['threads'][$n]->tkey = $thread_key;
        $result['threads'][$n]->resnum = $t['thread_res_count'];
        $result['threads'][$n]->ita = $t['board_name'];
        $result['threads'][$n]->dayres = $t['thread_dayres'];

        $bkey = md5($host . '-' . $bbs . '-' . $t['board_name']);
        if (!isset($boards[$bkey])) {
            $board = new stdClass;
            $board->host = $host;
            $board->bbs = $bbs;
            $names[$bkey] = $board->name = $t['board_name'];
            $hits[$bkey] = $board->hits = 1;
            $boards[$bkey] = $board;
        } else {
            $hits[$bkey] = ++$boards[$bkey]->hits;
            $names[$bkey] = $boards[$bkey]->name;
        }
    }

    $result['modified'] = $response->getHeader('Date');
    $result['profile']['regex'] = '/(' . $q . ')/i';
    $result['profile']['hits'] = count($threads);
    array_multisort($hits, SORT_DESC, $names, $boards);
    $result['profile']['boards'] = $boards;

    return $result;
}
