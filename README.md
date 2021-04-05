# rep2 expack 全部入り by （´・ω・） ｽ

* rep2-expack https://github.com/rsky/p2-php
* rep2-expack +live https://github.com/pluslive/p2-php
* rep2-expack test https://github.com/orzisun/p2-php
* rep2-expack https://github.com/open774/p2-php
* rep2-expack https://github.com/junk2ool/p2-php

上記やスレに上げられた修正を取り込んで全部入りを目指す闇鍋バージョンです。

このリポジトリにはrep2をPHP 8.xで動かすためのパッチが含まれています。

- [スクリーンショット](https://open774.github.io/p2-php/screenshots.html)
- [Wiki](https://github.com/open774/p2-php/wiki)
- [p2Wiki](http://akid.s17.xrea.com/p2puki/index.phtml)
- **[FAQ](https://github.com/open774/p2-php/wiki/FAQ) スレに書く前にからならず確認**

### 主な追加機能

各機能の説明はdocディレクトリのREADMEファイルを見てください。

* cronとかで最近読んだスレなどのdatをDL出来るスクリプト追加
```shell
php scripts/fetch-dat.php --mode モードを一つ指定(fav recent res_hist)
```
* 名無しが節穴になる板に名無しで書き込むときに警告を出す機能を追加
* NGあぼーんの対象になったレスのIDを自動的にNGあぼーんする機能を追加
* 「設定管理」からキャッシュ・履歴の消去できる機能を追加
* 本家からbeのログイン部分を移植してBE2.0に対応
* rep2に登録された外部板のリンクををrep2で開けるようにした（Janeと同じ動作）
* 0ちゃんねるスクリプトを使用した外部板の過去ログDATを取り込み対応
* SOCKS5プロクシ経由の接続に対応(人柱)
* tor内の掲示板(.onionドメイン)をtor経由で閲覧する機能を追加(人柱)
* curl を用いた並列ダウンロード機能を追加(人柱機能)

## セットアップ

### Git & Composerで

1. 本体をclone

```shell
git clone git://github.com/open774/p2-php.git
cd p2-php
```

2. 依存ライブラリをダウンロード

⚠️ **PEARサポートが廃止されたComposer 2.xでは動作しません** ⚠️

```shell
curl -O https://getcomposer.org/download/1.10.19/composer.phar
php -d detect_unicode=0 composer.phar install
```

3. Webサーバが書き込めるようにディレクトリのアクセス権をセット  

(CGI/suEXECIやCLI/Built-in web serverでは不要)

```shell
chmod 0777 data/* rep2/ic
```

## 動作環境

Linux(openSUSE Leap)のPHP7+Apacheで動作確認しています。
PHP5.6以降で動くはずですが、PHP7.*推奨です。
PHP7での不具合修正を優先するため突然PHP5.xのサポートが終わる可能性があります。

以下のコマンドを実行して、全ての項目で `OK` が出たなら大丈夫です。

何かエラーが出たらがんばって環境を整えてください。

```shell
php scripts/p2cmd.php check
```

## Built-in web serverで使ってみる (PHP 5.4+)

PHP 5.4の新機能、[ビルトインウェブサーバー](http://docs.php.net/manual/ja/features.commandline.webserver.php) で簡単に試せます。

以下のようにすると、Webサーバーの設定をしなくても `http://localhost:8080/` でrep2を使えます。**(Windowsでも!)**

```shell
cd rep2
php -S localhost:8080 web.php
```

moriyoshi++

## 画像を自動で保存したい

スレに貼られている画像を自動で保存する機能、**ImageCache2**があります。

see also [doc/ImageCache2/README.txt](doc/ImageCache2/README.txt), [doc/ImageCache2/INSTALL.txt](doc/ImageCache2/INSTALL.txt)

### 準備

1. SQLite以外のデータベースを使う場合はデータベースサーバーを立ち上げておく。  

2. conf/conf_admin_ex.inc.phpでImageCache2を有効にする。
  <pre>$_conf['expack.ic2.enabled'] = 3;</pre>

3. conf/conf_ic2.inc.phpで[DSN](http://pear.php.net/manual/ja/package.database.db.intro-dsn.php)を設定する。
  <pre>$_conf['expack.ic2.general.dsn'] = 'mysql://username:password@localhost:3306/database';</pre>

4. setupスクリプトを実行する。
  <pre>php scripts/ic2.php setup</pre>

### 注意

* PHP 5.4ではSQLite2がサポートされなくなったので、ImageCache2を使いたいときはMySQLかPostgreSQLが必要です。
* ホストに`localhost`を指定して接続できないときは、代わりに`127.0.0.1`にしてみてください。

## 設定を変えたい

細かい挙動の変更は `メニュー > 設定管理 > ユーザー設定編集` から行えます。

Webブラウザから変更できない項目は [conf/conf_admin.inc.php](https://github.com/open774/p2-php/blob/master/conf/conf_admin.inc.php) (基本), [conf/conf_admin_ex.inc.php](https://github.com/open774/p2-php/blob/master/conf/conf_admin_ex.inc.php) (拡張パック), [conf/conf_ic2.inc.php](https://github.com/open774/p2-php/blob/master/conf/conf_ic2.inc.php) (ImageCache2) を直接編集します。

どういうことができるか書き起こすのが面倒なので設定ファイルのコメントを見てください。

## cronを使った便利機能
下記のスクリプトをcronで定期的に回すとより便利にrep2を使用することが出来ます。
必要に応じてどちらか一つを使用すれば充分でしょう。

### 履歴の新着数更新
ブラウザから更新を行うと一覧の表示に時間がかかるため、subject.txtを更新するためのスクリプトが付属しています。
並列ダウンロードで高速ですが、使用するために設定変更を行う必要があります。

<pre>php scripts/fetch-subject-txt.php --mode モードを一つ指定(fav recent res_hist)</pre>

### 更新ついでにDATのダウンロード
並列ダウンロードの代わりにsubject.txtとDATのダウンロード機能を実装したスクリプトです。
時間はかかりますが、設定変更無しで使えるのでこちらがお手軽です。

<pre>php scripts/fetch-dat.php --mode モードを一つ指定(fav recent res_hist)</pre>

## 更新

    php scripts/p2cmd.php update

これは下記コマンドを個別に実行するのと等価です。

    git pull
    php -d detect_unicode=0 composer.phar self-update
    php -d detect_unicode=0 composer.phar update

## Authors & Contributors

* **aki** *(original)* http://akid.s17.xrea.com/
* **rsk** *(expack)* https://github.com/rsky/p2-php/
* **unpush** https://github.com/unpush/p2-php/
* **thermon** https://github.com/thermon/p2-php/
* **part32の892** *(+live)* https://github.com/pluslive/p2-php/
* **orzisun** https://github.com/orzisun/p2-php
* **open774** https://github.com/open774/p2-php
* **killer4989** https://github.com/killer4989/p2-php
* **dgg712** https://github.com/dgg712/p2-php
* **2ch p2/rep2スレの>>1-1000**

## License

see [LICENSE.txt](LICENSE.txt)
