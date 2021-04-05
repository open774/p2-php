<?php
/**
 * rep2expack - ImageCache2
 */

// {{{ ImageCache2_Thumbnailer

class ImageCache2_Thumbnailer
{
    // {{{ constants

    const SIZE_SOURCE   = 0; // ic2.php のため、便宜上設定しているが、好ましくない
    const SIZE_PC       = 1;
    const SIZE_MOBILE   = 2;
    const SIZE_INTERMD  = 3;

    const SIZE_DEFAULT  = 1;

    const DPR_DEFAULT   = 0;
    const DPR_1_5       = 0x150000;
    const DPR_2_0       = 0x200000;
    const DPR_MASK      = 0xff0000;

    // }}}
    // {{{ properties

    public $db;            // @var object  PDO_DataObjectのインスタンス
    public $ini;           // @var array   ImageCache2の設定
    public $mode;          // @var int     サムネイルの種類
    public $cachedir;      // @var string  ImageCache2のキャッシュ保存ディレクトリ
    public $cacheuri;      // @var string  ImageCache2のキャッシュ保存ディレクトリのURI
    public $sourcedir;     // @var string  ソース保存ディレクトリ
    public $sourceuri;     // @var string  ソース保存ディレクトリのURI
    public $thumbdir;      // @var string  サムネイル保存ディレクトリ
    public $thumburi;      // @var string  サムネイル保存ディレクトリのURI
    public $driver;        // @var string  イメージドライバの種類
    public $epeg;          // @var bool    Epegが利用可能か否か
    public $magick;        // @var string  ImageMagickのパス
    public $max_width;     // @var int     サムネイルの最大幅
    public $max_height;    // @var int     サムネイルの最大高さ
    public $type;          // @var string  サムネイルの画像形式（JPEGかPNG）
    public $quality;       // @var int     サムネイルの品質
    public $bgcolor;       // @var mixed   サムネイルの背景色
    public $resize;        // @var bolean  画像をリサイズするか否か
    public $rotate;        // @var int     画像を回転する角度（回転しないとき0）
    public $trim;          // @var bolean  画像をトリミングするか否か
    public $coord;         // @var array   画像をトリミングする範囲（トリミングしないときfalse）
    public $found;         // @var array   ImageCache2_DataObject_Imagesでクエリを送信した結果
    public $dynamic;       // @var bool    動的生成するか否か（trueのとき結果をファイルに保存しない）
    public $intermd;       // @var string  動的生成に利用する中間イメージのパス（ソースから直接生成するときfalse）
    public $buf;           // @var string  動的生成した画像データ

    /**
     * 動的生成時のオプション
     *
     * @var array
     */
    public $default_options = array(
        'quality' => null,
        'width'   => null,
        'height'  => null,
        'rotate'  => 0,
        'trim'    => false,
        'intermd' => false,
    );

    /**
     * MIMEタイプと拡張子の対応表
     *
     * @var array
     */
    public $mimemap = array(
        'image/jpeg' => '.jpg',
        'image/png'  => '.png',
        'image/gif'  => '.gif',
    );

    /**
     * device pixel ratio定数のいずれか
     *
     * @var int
     */
    protected $dpr = self::DPR_DEFAULT;

    // }}}
    // {{{ constructor

    /**
     * コンストラクタ
     *
     * @param int $mode
     * @param array $dynamic_options
     */
    public function __construct($mode = self::SIZE_DEFAULT, array $dynamic_options = null)
    {
        if ($dynamic_options) {
            $options = array_merge($this->default_options, $dynamic_options);
            $this->dynamic = true;
            $this->intermd = $options['intermd'];
        } else {
            $options = $this->default_options;
            $this->dynamic = false;
            $this->intermd = false;
        }

        // 設定
        $this->ini = ic2_loadconfig();

        // サムネイルモード判定
        $dpr = $mode & self::DPR_MASK;
        $mode = $mode & ~self::DPR_MASK;
        if ($dpr !== self::DPR_1_5 && $dpr !== self::DPR_2_0) {
            $dpr = self::DPR_DEFAULT;
        }
        $this->dpr = $dpr;
        switch ($mode) {
            case self::SIZE_SOURCE:
            case self::SIZE_PC:
                $this->mode = self::SIZE_PC;
                $setting = $this->ini['Thumb1'];
                break;
            case self::SIZE_MOBILE:
                $this->mode = self::SIZE_MOBILE;
                $setting = $this->ini['Thumb2'];
                break;
            case self::SIZE_INTERMD:
                $this->mode = self::SIZE_INTERMD;
                $setting = $this->ini['Thumb3'];
                break;
            default:
                $this->error('無効なサムネイルモードです。');
        }

        // イメージドライバ判定
        $driver = strtolower($this->ini['General']['driver']);
        $this->driver = $driver;
        switch ($driver) {
            case 'imagemagick6': // ImageMagick6 の convert コマンド
                $this->driver = 'imagemagick';
            case 'imagemagick': // ImageMagick の convert コマンド
                $searchpath = $this->ini['General']['magick'];
                if (!ic2_findexec('convert', $searchpath)) {
                    $this->error('ImageMagickが使えません。');
                }
                if ($searchpath) {
                    $this->magick = $searchpath . DIRECTORY_SEPARATOR . 'convert';
                } else {
                    $this->magick = 'convert';
                }
                break;
            case 'gd': // PHP の GD 拡張機能
            case 'imagick': // PHP の ImageMagick 拡張機能
            case 'imlib2': // PHP の Imlib2 拡張機能
                if (!extension_loaded($driver)) {
                    $this->error($driver . 'エクステンションが使えません。');
                }
                break;
            default:
                $this->error('無効なイメージドライバです。');
        }

        // ディレクトリ/URI設定
        $this->cachedir  = $this->ini['General']['cachedir'];
        $this->sourcedir = $this->cachedir . DIRECTORY_SEPARATOR . $this->ini['Source']['name'];
        $this->thumbdir  = $this->cachedir . DIRECTORY_SEPARATOR . $setting['name'];
        $this->cacheuri  = $this->ini['General']['cacheuri'];
        $this->sourceuri = $this->cacheuri . '/' . $this->ini['Source']['name'];
        $this->thumburi  = $this->cacheuri . '/' . $setting['name'];

        // サムネイルの画像形式・幅・高さ・回転角度・品質設定
        $rotate = (int) $options['rotate'];
        if (abs($rotate) < 4) {
            $rotate = $rotate * 90;
        }
        $rotate = ($rotate < 0) ? ($rotate % 360) + 360 : $rotate % 360;
        $this->rotate = ($rotate % 90 == 0) ? $rotate : 0;
        if ($options['width'] >= 1 && $options['height'] >= 1) {
            $setting['width']  = $options['width'];
            $setting['height'] = $options['height'];
        }
        if ($this->rotate % 180 == 90) {
            $this->max_width  = (int) $setting['height'];
            $this->max_height = (int) $setting['width'];
        } else {
            $this->max_width  = (int) $setting['width'];
            $this->max_height = (int) $setting['height'];
        }
        if (is_null($options['quality'])) {
            $this->quality = (int) $setting['quality'];
        } else {
            $this->quality = (int) $options['quality'];
        }
        if (0 < $this->quality && $this->quality <= 100) {
            $this->type = '.jpg';
        } else {
            $this->type = '.png';
            $this->quality = 0;
        }
        $this->trim = (bool) $options['trim'];

        // Epeg使用判定
        if ($this->ini['General']['epeg'] && extension_loaded('epeg') &&
            !$this->dynamic && $this->type == '.jpg' &&
            $this->quality <= $this->ini['General']['epeg_quality_limit'])
        {
            $this->epeg = true;
        } else {
            $this->epeg = false;
        }

        // サムネイルの背景色設定
        if (preg_match('/^#?([0-9A-F]{2})([0-9A-F]{2})([0-9A-F]{2})$/i', // RGB各色2桁の16進数
                       $this->ini['General']['bgcolor'], $c)) {
            $r = hexdec($c[1]);
            $g = hexdec($c[2]);
            $b = hexdec($c[3]);
        } elseif (preg_match('/^#?([0-9A-F])([0-9A-F])([0-9A-F])$/i', // RGB各色1桁の16進数
                  $this->ini['General']['bgcolor'], $c)) {
            $r = hexdec($c[1] . $c[1]);
            $g = hexdec($c[2] . $c[2]);
            $b = hexdec($c[3] . $c[3]);
        } elseif (preg_match('/^(\d{1,3}),(\d{1,3}),(\d{1,3})$/', // RGB各色1～3桁の10進数
                  $this->ini['General']['bgcolor'], $c)) {
            $r = max(0, min(intval($c[1]), 255));
            $g = max(0, min(intval($c[2]), 255));
            $b = max(0, min(intval($c[3]), 255));
        } else {
            $r = null;
            $g = null;
            $b = null;
        }
        $this->bgcolor = array($r, $g, $b);
    }

    // }}}
    // {{{ convert()

    /**
     * サムネイルを作成
     *
     * @return  string|bool|PEAR_Error
     *          サムネイルを生成・保存に成功したとき、サムネイルのパス
     *          テンポラリ・サムネイルの生成に成功したとき、true
     *          失敗したとき PEAR_Error
     */
    public function convert($size, $md5, $mime, $width, $height,
                            $force = false, $anigif = false, $gifcaution = false)
    {
        // 画像
        if (!empty($this->intermd) && file_exists($this->intermd)) {
            $src    = realpath($this->intermd);
            $csize  = getimagesize($this->intermd);
            $width  = $csize[0];
            $height = $csize[1];
        } else {
            $src = $this->srcPath($size, $md5, $mime);
        }
        $thumbUrl = $this->thumbUrl($size, $md5, $mime);
        $thumbPath = $this->thumbPath($size, $md5, $mime);
        if ($src == false) {
            $error = PEAR::raiseError("無効なMIMEタイプ。({$mime})");
            return $error;
        } elseif (!file_exists($src)) {
            $error = PEAR::raiseError("ソース画像がキャッシュされていません。({$src})");
            return $error;
        }
        if (!$force && !$this->dynamic && file_exists($thumbPath)) {
            return $thumbUrl;
        }
        $thumbDir = dirname($thumbPath);
        if (!is_dir($thumbDir) && !FileCtl::mkdirRecursive($thumbDir)) {
            $error = PEAR::raiseError("ディレクトリを作成できませんでした。({$thumbDir})");
            return $error;
        }

        // サイズが既定値以下で回転なし、画像形式が同じならばそのままコピー
        // --- 携帯で表示できないことがあるので封印、ちゃんとサムネイルをつくる
        /*if ($this->resize == false && $this->rotate == 0 && $this->type == $this->mimemap[$mime]) {
            if (@copy($src, $thumbPath)) {
                return $thumbUrl;
            } else {
                $error = PEAR::raiseError("画像をコピーできませんでした。({$src} -&gt; {$thumbPath})");
                return $error;
            }
        }*/

        // Epegでサムネイルを作成
        if ($mime === 'image/jpeg' && $this->epeg) {
            $dst = ($this->dynamic) ? '' : $thumbPath;
            list($max_width, $max_height) = $this->getMaxImageSize();
            $result = epeg_thumbnail_create($src, $dst, $max_width, $max_height, $this->quality);
            if ($result === false) {
                $error = PEAR::raiseError("サムネイルを作成できませんでした。({$src} -&gt; {$dst})");
                return $error;
            }
            if ($this->dynamic) {
                $this->buf = $result;
            }
            return $thumbUrl;
        }

        // 出力サイズを計算
        $size = array('w' => $width, 'h' => $height);
        list($size['tw'], $size['th']) = $this->calc($width, $height, true);
        if (is_array($this->coord)) {
            $size['sx'] = $this->coord['x'][0];
            $size['sy'] = $this->coord['y'][0];
            $size['sw'] = $this->coord['x'][1];
            $size['sh'] = $this->coord['y'][1];
        } else {
            $size['sx'] = 0;
            $size['sy'] = 0;
            $size['sw'] = $width;
            $size['sh'] = $height;
        }

        // イメージドライバにサムネイル作成処理をさせる
        $convertorClass = 'Thumbnailer_' . ucfirst(strtolower($this->driver));

        $convertor = new $convertorClass();
        $convertor->setBgColor($this->bgcolor[0], $this->bgcolor[1], $this->bgcolor[2]);
        $convertor->setHttp(true);
        if ($this->type == '.png') {
            $convertor->setPng(true);
        } else {
            $convertor->setQuality($this->quality);
        }
        $convertor->setResampling($this->resize);
        $convertor->setRotation($this->rotate);
        $convertor->setTrimming($this->trim);
        if ($this->driver === 'imagemagick') {
            $convertor->setImageMagickConvertPath($this->magick);
        }
        if ($anigif) {
            $convertor->setDecorateAnigif($anigif);
            $convertor->setDecorateAnigifFilePath($this->ini['Thumbdeco']['anigif_path']);
        }
        if ($gifcaution) {
            $convertor->setDecorateGifCaution($gifcaution);
            $convertor->setDecorateGifCautionFilePath($this->ini['Thumbdeco']['gifcaution_path']);
        }

        if ($this->dynamic) {
            $result = $convertor->capture($src, $size);
            if (is_string($result)) {
                $this->buf = $result;
            }
        } else {
            $result = $convertor->save($src, $thumbPath, $size);
        }

        if (PEAR::isError($result)) {
            return $result;
        }
        return $thumbUrl;
    }

    // }}}
    // {{{ utility methods
    // {{{ calc()

    /**
     * サムネイルサイズ計算
     */
    public function calc($width, $height, $return_array = false)
    {
        // デフォルト値・フラグを設定
        $t_width  = $width;
        $t_height = $height;
        $this->resize = false;
        $this->coord  = false;

        // 最大サイズ補正
        if ($return_array) {
            list($max_width, $max_height) = $this->getMaxImageSize();
        } else {
            $max_width  = $this->max_width;
            $max_height = $this->max_height;
        }

        // ソースがサムネイルの最大サイズより小さいとき、ソースの大きさをそのまま返す
        if ($width <= $max_width && $height <= $max_height) {
            // リサイズ・トリミングともに無効
            if ($return_array) {
                return array((int)$t_width, (int)$t_height);
            } else {
                return sprintf('%dx%d', $t_width, $t_height);
            }
        }

        // 縦横どちらに合わせるかを判定（最大サイズより横長 = 横幅に合わせる）
        if (($width / $height) >= ($max_width / $max_height)) {
            // 横に合わせる
            $main = $width;
            $sub  = $height;
            $max_main = $max_width;
            $max_sub  = $max_height;
            $t_main = &$t_width;  // $t_mainと$t_subをサムネイルサイズの
            $t_sub  = &$t_height; // リファレンスにしているのが肝
            $c_main = 'x';
            $c_sub  = 'y';
        } else {
            // 縦に合わせる
            $main = $height;
            $sub  = $width;
            $max_main = $max_height;
            $max_sub  = $max_width;
            $t_main = &$t_height;
            $t_sub  = &$t_width;
            $c_main = 'y';
            $c_sub  = 'x';
        }

        // サムネイルサイズと変換フラグを決定
        $t_main = $max_main;
        if ($this->trim) {
            // トリミングする
            $this->coord = array($c_main => array(0, $main), $c_sub => array(0, $sub));
            $ratio = $t_sub / $max_sub;
            if ($ratio <= 1) {
                // ソースがサムネイルの最大サイズより小さいとき、縮小せずにトリミング
                // $t_main == $max_main, $t_sub == $sub
                // ceil($sub * ($t_main / $t_sub)) = ceil($sub * $t_main / $sub) = $t_main = $max_main
                $c_length = $max_main;
            } elseif ($ratio < 1.05) {
                // 縮小率が極めて小さいとき、画質劣化を避けるために縮小せずにトリミング
                $this->coord[$c_sub][0] = floor(($t_sub - $max_sub) / 2);
                $t_sub = $max_sub;
                $c_length = $max_main;
            } else {
                // サムネイルサイズいっぱいに収まるように縮小＆トリミング
                $this->resize = true;
                $t_sub = $max_sub;
                $c_length = ceil($sub * ($t_main / $t_sub));
            }
            $this->coord[$c_main] = array(floor(($main - $c_length) / 2), $c_length);
        } else {
            // アスペクト比を維持したまま縮小し、トリミングはしない
            $this->resize = true;
            $t_sub = round($max_main * ($sub / $main));
        }

        // サムネイルサイズを返す
        if ($return_array) {
            return array((int)$t_width, (int)$t_height);
        } else {
            return sprintf('%dx%d', $t_width, $t_height);
        }
    }

    // }}}
    // {{{ getSource()

    /**
     * ソースパスを取得
     */
    public function getSource($size, $md5, $mime, $fullPath)
    {
        $basedir = $fullPath ? $this->sourcedir : $this->sourceuri;
        $directory = $this->getSubDir($basedir, $size, $md5, $mime, $fullPath);
        if (!$directory) {
            return false;
        }

        $basename = $size . '_' . $md5 . $this->mimemap[$mime];

        return $directory . ($fullPath ? DIRECTORY_SEPARATOR : '/') . $basename;
    }

    // }}}
    // {{{ srcPath()

    /**
     * ソース画像のパスを取得
     */
    public function srcPath($size, $md5, $mime)
    {
        return $this->getSource($size, $md5, $mime, true);
    }

    // }}}
    // {{{ srcUrl()

    /**
     * ソース画像のUrlを取得
     */
    public function srcUrl($size, $md5, $mime)
    {
        return $this->getSource($size, $md5, $mime, false);
    }

    // }}}
    // {{{ getThumbnail()

    /**
     * サムネイルのパスを取得
     */
    public function getThumbnail($size, $md5, $mime, $fullPath)
    {
        $basedir = $fullPath ? $this->thumbdir : $this->thumburi;
        $directory = $this->getSubDir($basedir, $size, $md5, $mime, $fullPath);
        if (!$directory) {
            return false;
        }

        $basename = $size . '_' . $md5;
        switch ($this->dpr) {
            case self::DPR_1_5:
                $basename .= '_x15';
                break;
            case self::DPR_2_0:
                $basename .= '_x20';
                break;
        }
        if ($this->rotate) {
            $basename .= '_' . str_pad($this->rotate, 3, 0, STR_PAD_LEFT);
        }
        if ($this->trim) {
            $basename .= '_tr';
        }
        $basename .= $this->type;

        return $directory . ($fullPath ? DIRECTORY_SEPARATOR : '/') . $basename;
    }

    // }}}
    // {{{ thumbPath()

    /**
     * サムネイルのパスを取得
     */
    public function thumbPath($size, $md5, $mime)
    {
        return $this->getThumbnail($size, $md5, $mime, true);
    }

    // }}}
    // {{{ thumbUrl()

    /**
     * サムネイルのURLを取得
     */
    public function thumbUrl($size, $md5, $mime)
    {
        return $this->getThumbnail($size, $md5, $mime, false);
    }

    // }}}
    // {{{ getSubDir()

    /**
     * 画像が保存されるサブディレクトリのパスを取得
     */
    public function getSubDir($basedir, $size, $md5, $mime, $fullPath = false)
    {
        $dirID = $this->dirID($size, $md5, $mime);
        if ($fullPath) {
            if (!is_dir($basedir)) {
                if (!FileCtl::mkdirRecursive($basedir)) {
                    return false;
                }
            }
            return realpath($basedir) . DIRECTORY_SEPARATOR . $dirID;
        }
        return $basedir . '/' . $dirID;
    }

    // }}}
    // {{{ dirID()

    /**
     * 画像1000枚ごとにインクリメントするディレクトリIDを取得
     */
    public function dirID($size = null, $md5 = null, $mime = null)
    {
        if ($size && $md5 && $mime) {
            $icdb = new ImageCache2_DataObject_Images();
            $icdb->whereAddQUoted('size', '=', $size);
            $icdb->whereAddQuoted('md5', '=', $md5);
            $icdb->whereAddQUoted('mime', '=', $mime);
            $icdb->orderByArray(array('id' => 'ASC'));
            if ($icdb->find(true)) {
                $this->found = $icdb->toArray();
                return str_pad(ceil($icdb->id / 1000), 5, 0, STR_PAD_LEFT);
            }
        }
        $icdb = new ImageCache2_DataObject_Images();
        $icdb->select(sprintf('MAX(%s) + 1 as id', $icdb->quoteIdentifier('id')));
        if ($icdb->find(true)) {
            $nextid = $icdb->id;
        } else {
            p2die('DBに登録された画像の枚数を数えられませんでした');
        }
        return str_pad(ceil($nextid / 1000), 5, 0, STR_PAD_LEFT);
    }

    // }}}
    // {{{ getImageMaxSize()

    /**
     * device pixel ratioを反映した最大画像サイズを得る
     */
    public function getMaxImageSize()
    {
        switch ($this->dpr) {
            case self::DPR_1_5:
                $w = intval(1.5 * $this->max_width);
                $h = intval(1.5 * $this->max_height);
                break;
            case self::DPR_2_0:
                $w = 2 * $this->max_width;
                $h = 2 * $this->max_height;
                break;
            default:
                $w = $this->max_width;
                $h = $this->max_height;
        }
        return array($w, $h);
    }

    // }}}
    // }}}
    // {{{ error()

    /**
     * エラーメッセージを表示して終了
     */
    public function error($message = '')
    {
        echo <<<EOF
<html>
<head><title>ImageCache::Error</title></head>
<body>
<p>{$message}</p>
</body>
</html>
EOF;
        exit;
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
