<?php
function refind2ch_search ($query)
{
    parse_str($query, $query_arry);

    $q = $query_arry['q'];

    $base_url = 'https://refind2ch.org';
    $endpoint = $base_url . '/module/get_search';
    $referer = $base_url . '/search?q=' . urlencode($q);

    try {
        $req = P2Commun::createHTTPRequest ($endpoint, HTTP_Request2::METHOD_POST);
        $req->setHeader('Accept', 'application/json');
        $req->setHeader('Referer', $referer);

        $req->addPostParameter('q', $q);
        $req->addPostParameter('p', 0);
        $req->addPostParameter('pink', 0);
        $req->addPostParameter('sort', 'false');
        $req->addPostParameter('alive', 'true');
        $req->addPostParameter('pl', 'false');
        $req->addPostParameter('b', 'false');

        $response = P2Commun::getHTTPResponse($req);

        $code = $response->getStatus();
        if ($code != 200) {
            p2die("HTTP Error - {$code}");
        }

        $body = $response->getBody();
    } catch (Exception $e) {
        p2die($e->getMessage());
    }

    $threads = json_decode($body, true)['results'];

    $result = array();
    $boards = array();
    $hits = array();
    $names = array();

    foreach ($threads as $n => $t) {
        $host = parse_url($t['url'])['host'];
        $bbs = explode('_', $t['board_key'])[1];

        $result['threads'][$n] = new stdClass;
        $result['threads'][$n]->title = $t['thread_title'];
        $result['threads'][$n]->host = $host;
        $result['threads'][$n]->bbs = $bbs;
        $result['threads'][$n]->tkey = $t['created_at'];
        $result['threads'][$n]->resnum = $t['res_num'];
        $result['threads'][$n]->ita = $t['board_title'];
        $result['threads'][$n]->dayres = $t['res_rate'];

        $bkey = md5($host.'-'.$bbs.'-'.$t['board_title']);
        if (! isset($boards[$bkey])) {
            $board = new stdClass;
            $board->host = $host;
            $board->bbs = $bbs;
            $names[$bkey] = $board->name = $t['board_title'];
            $hits[$bkey] = $board->hits = 1;
            $boards[$bkey] = $board;
        } else {
            $hits[$bkey] = ++$boards[$bkey]->hits;
            $names[$bkey] = $boards[$bkey]->name;
        }
    }

    $result['modified'] = $response->getHeader('Date');
    $result['profile']['regex'] = '/(' . $q .')/i';
    $result['profile']['hits'] = count($threads);
    array_multisort($hits, SORT_DESC, $names, $boards);
    $result['profile']['boards'] = $boards;

    return $result;
}
