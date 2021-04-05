<?php
function ff5ch_search($query)
{
    parse_str($query, $query_arry);

    $q = $query_arry['q'];

    $base_url = 'https://ff5ch.syoboi.jp';
    $endpoint = $base_url . '/?q=' . urlencode($q);
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

    // FIXME ‘½­‚Ì\‘¢•Ï‰»‚É‘Ï‚¦‚ç‚ê‚é‚æ‚¤‚È³‹K•\Œ»‚ÖC³
    $re = '/<li.*><a class="thread" href="(?P<thread_url>.+)">(?P<thread_name>.+)<\/a><span\s+?class="count"> \((?<thread_res_count>\d+)\)<\/span><br\/>\s*<a class="board" href="(?P<board_url>.+)">(?<board_name>.+)<\/a>\s*<span class="time">(?<created_at>.+)<\/span>\s*<\/li>/m';

    preg_match_all($re, $body, $threads, PREG_SET_ORDER);

    $result = array();
    $boards = array();
    $hits = array();
    $names = array();

    $now = strtotime($response->getHeader('Date'));

    foreach ($threads as $n => $t) {
        $host = parse_url($t['thread_url'], PHP_URL_HOST);
        $thread_key = array_slice(explode('/', $t['thread_url']), -2, 1)[0];
        $bbs = array_slice(explode('/', $t['board_url']), -2, 1)[0];
        $created_at = strtotime($t['created_at'] . ' +0900');
        $dayres = intval($t['thread_res_count']) / ($now - $created_at) * 86400;

        $result['threads'][$n] = new stdClass;
        $result['threads'][$n]->title = $t['thread_name'];
        $result['threads'][$n]->host = $host;
        $result['threads'][$n]->bbs = $bbs;
        $result['threads'][$n]->tkey = $thread_key;
        $result['threads'][$n]->resnum = $t['thread_res_count'];
        $result['threads'][$n]->ita = $t['board_name'];
        $result['threads'][$n]->dayres = $dayres;

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
