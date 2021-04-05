<?php
/*
	+live - スレッド表示に関する共通処理 ../ShowThreadPc.php より読み込まれる
*/

// 名前
// デフォルトの名無しの表示
// showthreadpc.class.phpにて

// 日付とID
// IDフィルタ
if ($_conf['flex_idpopup'] == 1 && $id && $this->thread->idcount[$id] > 1) {
	$date_id = str_replace($idstr, $this->idFilter($idstr, $id), $date_id);
}
// ID末尾 (O,P,Q,I 等) の強調
if ($_conf['live.id_b']) {
	if (!preg_match("(ID:)", $date_id)) { // ID無しで末尾表示のみ有りの板 (狼等)
		$date_id = preg_replace('((\s([a-zA-Z])$)(?![^<]*>))', '<b class="mail">$1</b>', $date_id);
	} else {
		$date_id = preg_replace('((ID: ?)([0-9A-Za-z/.+]{10}|[0-9A-Za-z/.+]{8}|\\?\\?\\?)?([a-zA-Z])(?=[^0-9A-Za-z/.+]|$)(?![^<]*>))', '$1$2<b class="mail">&nbsp;$3</b>', $date_id);
	}
}
// 日付の短縮
if (preg_match("([0-2][0-9]{3}/[0-1][0-9]/[0-3][0-9])", $date_id)) {
	if ($_GET['live']) { // 実況中は日付を全削除
		$date_id = preg_replace("([0-2][0-9]{3}/[0-1][0-9]/[0-3][0-9]\(..\))", "", $date_id);
	} else { // 上記以外は年を下2桁に
		if (preg_match("(class=\"ngword)", $date_id)) { // NGIDの時
			$date_id = preg_replace("(([0-2][0-9])([0-9]{2}/[0-1][0-9]/[0-3][0-9]\(..\)))", "$2", $date_id);
		} else {
			$date_id = preg_replace("(([0-2][0-9])([0-9]{2}/[0-1][0-9]/[0-3][0-9]\(..\)))", "$2", $date_id);
		}
	}
}

// メール
if ($mail) {
	// 実況中の場合
	if ($_conf['live.mail_sage'] 
	&& ($_GET['live'])) {
		// sage を ▼ に
		if (preg_match("(^[\\s　]*sage[\\s　]*$)", $mail)) {
			if ($STYLE['read_mail_sage_color']) {
				$mail = "<span class=\"sage\" title=\"{$mail}\">▼</span>";
			} elseif ($STYLE['read_mail_color']) {
				$mail = "<span class=\"mail\" title=\"{$mail}\">▼</span>";
			} else {
				$mail = "<span title=\"{$mail}\">▼</span>";
			}
		// sage 以外を ● に
		} else {
			$mail = "<span class=\"mail\" title=\"{$mail}\">●</span>";
		}
	// ノーマル処理
	} elseif (preg_match("(^[\\s　]*sage[\\s　]*$)", $mail)
	&& $STYLE['read_mail_sage_color']) {
		$mail = "<span class=\"sage\">{$mail}</span>";
	} elseif ($STYLE['read_mail_color']) {
		$mail = "<span class=\"mail\">{$mail}</span>";
	} else {
		$mail = "{$mail}";
	}
}

// [これにレス] の方法
if ($_GET['live']) {
    $res_dblclc = "";
	$ttitle_en_q ="&amp;ttitle_en=".UrlSafeBase64::encode($this->thread->ttitle);
	// 内容をダブルクリック
	if ($_conf['live.res_button'] >= 1) {
		$res_dblclc = "ondblclick=\"window.parent.livepost.location.href='live_post_form.php?host={$this->thread->host}&amp;bbs={$this->thread->bbs}&amp;key={$this->thread->key}&amp;resnum={$i}{$ttitle_en_q}&amp;inyou=1'\" title=\"{$i} にレス (double click)\"";
	}
	// レスボタン
	if ($_conf['live.res_button'] <= 1) {
		if ($_conf['iframe_popup'] == 3) {
			$res_button = "<a href=\"live_post_form.php?host={$this->thread->host}&amp;bbs={$this->thread->bbs}&amp;key={$this->thread->key}&amp;resnum={$i}{$ttitle_en_q}&amp;inyou=1\" target=\"livepost\" title=\"{$i} にレス\"><img src =\"./img/re.png\" alt=\"Re:\" width=\"22\" height=\"12\"></a>";
		} else {
			$res_button = "(<a href=\"live_post_form.php?host={$this->thread->host}&amp;bbs={$this->thread->bbs}&amp;key={$this->thread->key}&amp;resnum={$i}{$ttitle_en_q}&amp;inyou=1\" target=\"livepost\" title=\"{$i} にレス\">re:</a>)";
		}
	} 
}

// 内容

// 実況中の表示切詰め処理
if ($_conf['live.msg']
&& ($_GET['live'])) {
	$msg = mb_convert_kana($msg, 'rnas');								// 全角の英数、記号、スペースを半角に
	if (!preg_match ("(tp:/|ps:/|res/)", $msg)) {
		$msg = mb_ereg_replace("([\\s　]*<br>[\\s　]*)", " ", $msg);	// 全改行を消去し半角スペースに。内容に外部リンクや板別勢い一覧を含む場合は対象外
	}
	$msg = mb_ereg_replace("(\s{2,})", " ", $msg);						// 連続スペースを1つに
}

// +live スレッド内容表示切替
if ($_GET['live']) {
	if ($_conf['live.view_type'] == 0 ) {
		include P2_LIB_DIR . '/live/default_view.inc.php';
	} else {
		include P2_LIB_DIR . '/live/live_view.inc.php';
	}
} else {
	if ($_conf['live.view_type'] > 1 ) {
		include P2_LIB_DIR . '/live/live_view.inc.php';
	} else {
		include P2_LIB_DIR . '/live/default_view.inc.php';
	}
}

?>