<?php

// {{{ ImageCache2_DatabaseManager

/**
 * ImageCache2 - 画像情報を操作するクラス
 */
class ImageCache2_DatabaseManager
{
    // {{{ update()

    /**
     * 画像情報を更新
     */
    static public function update($updated)
    {
        if (empty($updated)) {
            return;
        }
        if (!is_array($updated)) {
            P2Util::pushInfoHtml('<p>WARNING! ImageCache2_DatabaseManager::update(): 不正な引数</p>');
            return;
        }

        // トランザクションの開始
        $ta = new ImageCache2_DataObject_Images();
        $ta->PDO()->beginTransaction();

        // 画像データを更新
        foreach ($updated as $id => $data) {
            $icdb = new ImageCache2_DataObject_Images();
            $icdb->whereAdd("id = $id");
            if ($icdb->find(true)) {
                // メモを更新
                if ($icdb->memo != $data['memo']) {
                    $memo = new ImageCache2_DataObject_Images();
                    $memo->memo = (strlen($data['memo']) > 0) ? $data['memo'] : '';
                    $memo->whereAdd("id = $id");
                    $memo->update();
                }
                // ランクを更新
                if ($icdb->rank != $data['rank']) {
                    $rank = new ImageCache2_DataObject_Images();
                    $rank->rank = $data['rank'];
                    $rank->whereAddQuoted('size', '=', $icdb->size);
                    $rank->whereAddQuoted('md5',  '=', $icdb->md5);
                    $rank->whereAddQuoted('mime', '=', $icdb->mime);
                    $rank->update();
                }
            }
        }

        // トランザクションのコミット
        $ta->PDO()->commit();
    }

    // }}}
    // {{{ remove()

    /**
     * 画像を削除
     */
    static public function remove($target, $to_blacklist = false)
    {
        $ini = ic2_loadconfig();

        $removed_files = array();
        if (empty($target)) {
            return $removed_files;
        }
        if (!is_array($target)) {
            if (is_integer($target) || ctype_digit($target)) {
                $id = (int) $target;
                if ($id > 0) {
                    $target = array($id);
                } else {
                    return $removed_files;
                }
            } else {
                P2Util::pushInfoHtml('<p>WARNING! ImageCache2_DatabaseManager::remove(): 不正な引数</p>');
                return $removed_files;
            }
        }

        // トランザクションの開始
        $ta = new ImageCache2_DataObject_Images();
        $ta->PDO()->beginTransaction();

        // 画像を削除
        $parent_dir = dirname($ini['General']['cachedir']) . DIRECTORY_SEPARATOR;
        $pattern = '/^' . preg_quote($parent_dir, '/') . '/';
        foreach ($target as $id) {
            $icdb = new ImageCache2_DataObject_Images();
            $icdb->whereAdd("id = {$id}");

            if ($icdb->find(true)) {
                // キャッシュしているファイルを削除
                $sizes = array(
                    ImageCache2_Thumbnailer::SIZE_PC,
                    ImageCache2_Thumbnailer::SIZE_MOBILE,
                    ImageCache2_Thumbnailer::SIZE_INTERMD,
                );
                $dprs = array(
                    ImageCache2_Thumbnailer::DPR_DEFAULT,
                    ImageCache2_Thumbnailer::DPR_1_5,
                    ImageCache2_Thumbnailer::DPR_2_0,
                );
                foreach ($sizes as $size) {
                    foreach ($dprs as $dpr) {
                        $t = new ImageCache2_Thumbnailer($size | $dpr);
                        $path = $t->thumbPath($icdb->size, $icdb->md5, $icdb->mime);
                        if (file_exists($path)) {
                            unlink($path);
                            $removed_files[] = preg_replace($pattern, '', $path);
                        }
                    }
                }
                $t = new ImageCache2_Thumbnailer();
                $path = $t->srcPath($icdb->size, $icdb->md5, $icdb->mime);
                if (file_exists($path)) {
                    unlink($path);
                    $removed_files[] = preg_replace($pattern, '', $path);
                }

                // ブラックリスト送りの準備
                if ($to_blacklist) {
                    $_blacklist = new ImageCache2_DataObject_BlackList();
                    $_blacklist->size = $icdb->size;
                    $_blacklist->md5  = $icdb->md5;
                    if ($icdb->mime === 'clamscan/infected' || $icdb->rank == -4) {
                        $_blacklist->type = 2;
                    } elseif ($icdb->rank < 0) {
                        $_blacklist->type = 1;
                    } else {
                        $_blacklist->type = 0;
                    }
                }

                // 同一画像を検索
                $remover = new ImageCache2_DataObject_Images();
                $remover->whereAddQuoted('size', '=', $icdb->size);
                $remover->whereAddQuoted('md5',  '=', $icdb->md5);
                //$remover->whereAddQuoted('mime', '=', $icdb->mime); // SizeとMD5で十分
                $remover->find();
                while ($remover->fetch()) {
                    // ブラックリスト送りにする
                    if ($to_blacklist) {
                        $blacklist = new ImageCache2_DataObject_BlackList();
                        $blacklist->uri = $remover->uri;
                        $blacklist->size = $remover->size;
                        $blacklist->md5 = $remover->md5;
                        $blacklist->insert();
                    }
                    // テーブルから抹消
                    $remover->delete();
                }
            }
        }

        // トランザクションのコミット
        $ta->PDO()->commit();

        return $removed_files;
    }

    // }}}
    // {{{ setRank()

    /**
     * ランクを設定
     */
    static public function setRank($target, $rank)
    {
        if (empty($target)) {
            return;
        }
        if (!is_array($target)) {
            if (is_integer($target) || ctype_digit($target)) {
                $id = (int)$target;
                if ($id > 0) {
                    $target = array($id);
                } else {
                    return;
                }
            } else {
                P2Util::pushInfoHtml('<p>WARNING! ImageCache2_DatabaseManager::setRank(): 不正な引数</p>');
            }
        }

        $icdb = new ImageCache2_DataObject_Images();
        $icdb->rank = $rank;
        foreach ($target as $id) {
            $icdb->whereAdd("id = $id", 'OR');
        }
        $icdb->update();
    }

    // }}}
    // {{{ addMemo()

    /**
     * メモを追加
     */
    static public function addMemo($target, $memo)
    {
        if (empty($target)) {
            return;
        }
        if (!is_array($target)) {
            if (is_integer($target) || ctype_digit($target)) {
                $id = (int)$target;
                if ($id > 0) {
                    $target = array($id);
                } else {
                    return;
                }
            } else {
                P2Util::pushInfoHtml('<p>WARNING! ImageCache2_DatabaseManager::addMemo(): 不正な引数</p>');
                return;
            }
        }

        // トランザクションの開始
        $ta = new ImageCache2_DataObject_Images();
        $db = $ta->PDO()->beginTransaction();

        // メモに指定文字列が含まれていなければ更新
        foreach ($target as $id) {
            $find = new ImageCache2_DataObject_Images();
            $find->whereAdd("id = $id");
            if ($find->find(true) && strpos($find->memo, $memo) === false) {
                $update = new ImageCache2_DataObject_Images();
                $update->whereAdd("id = $id");
                if (strlen($find->memo) > 0) {
                    $update->memo = $find->memo . ' ' . $memo;
                } else {
                    $update->memo = $memo;
                }
                $update->update();
                unset($update);
            }
            unset($find);
        }

        // トランザクションのコミット
        $ta->PDO()->commit();
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
