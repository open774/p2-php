<?php
/**
 * Thumbnailer_Gd
 * PHP Version 5
 */

// {{{ Thumbnailer_Gd

/**
 * Image manipulation class which uses gd php extension.
 */
class Thumbnailer_Gd extends Thumbnailer
{
    // {{{ save()

    /**
     * Convert and save.
     *
     * @param string $source
     * @param string $thumbnail
     * @param array $size
     * @return boolean
     * @throws PEAR_Error
     */
    public function save($source, $thumbnail, $size)
    {
        $dst = $this->_convert($source, $size);
        $dst = $this->decorate($source, $dst);  // デコレーション
        // サムネイルを保存
        if ($this->isPng()) {
            $result = imagepng($dst, $thumbnail);
        } else {
            $result = imagejpeg($dst, $thumbnail, $this->getQuality());
        }
        imagedestroy($dst);
        if (!$result) {
            $retval = PEAR::raiseError("Failed to create a thumbnail. ({$thumbnail})");
        } else {
            $retval = true;
        }
        return $retval;
    }

    /**
     * Convert and capture.
     *
     * @param string $source
     * @param array $size
     * @return string
     * @throws PEAR_Error
     */
    public function capture($source, $size)
    {
        $dst = $this->_convert($source, $size);
        // サムネイルを作成
        ob_start();
        if ($this->isPng()) {
            $result = imagepng($dst);
        } else {
            $result = imagejpeg($dst, '', $this->getQuality());
        }
        $retval = ob_get_clean();
        imagedestroy($dst);
        if (!$result) {
            unset($retval);
            $retval = PEAR::raiseError("Failed to create a thumbnail. ({$thumbnail})");
        }
        return $retval;
    }

    /**
     * Convert and output.
     *
     * @param string $source
     * @param string $name
     * @param array $size
     * @return boolean
     * @throws PEAR_Error
     */
    public function output($source, $name, $size)
    {
        $dst = $this->_convert($source, $size);
        // サムネイルを出力
        $this->_httpHeader($name);
        if ($this->isPng()) {
            $result = imagepng($dst);
        } else {
            $result = imagejpeg($dst, '', $this->getQuality());
        }
        imagedestroy($dst);
        if (!$result) {
            $retval = PEAR::raiseError("Failed to create a thumbnail. ({$name})");
        } else {
            $retval = true;
        }
        return $retval;
    }

    // }}}
    // {{{ _convert()

    /**
     * Image conversion abstraction.
     *
     * @param string $source
     * @param array $size
     * @return resource gd
     */
    protected function _convert($source, $size)
    {
        extract($size);

        // ソースのイメージストリームを取得
        $ext = strrchr($source, '.');
        switch ($ext) {
            case '.jpg': $src = imagecreatefromjpeg($source); break;
            case '.png': $src = imagecreatefrompng($source); break;
            case '.gif': $src = imagecreatefromgif($source); break;
            default:
                $src = false;
        }
        if (!$src) {
            $error = PEAR::raiseError("Failed to load the image. ({$source})");
            return $error;
        }

        // サムネイルのイメージストリームを作成し、背景を塗りつぶす
        $dst = imagecreatetruecolor($tw, $th);
        $bgcolor = $this->getBgColor();
        if (!imageistruecolor($src)) {
            $t_index = imagecolortransparent($src);
            if ($t_index > -1) {
                $t_colors = @imagecolorsforindex($src, $t_index);
                if ($t_colors) {
                    $bgcolor[0] = $t_colors['red'];
                    $bgcolor[1] = $t_colors['green'];
                    $bgcolor[2] = $t_colors['blue'];
                }
            }
        }
        $bg = imagecolorallocate($dst, $bgcolor[0], $bgcolor[1], $bgcolor[2]);
        imagefill($dst, 0, 0, $bg);

        // ソースをサムネイルにコピー
        if ($this->doesResampling()) {
            imagecopyresampled($dst, $src, 0, 0, $sx, $sy, $tw, $th, $sw, $sh);
        } else {
            imagecopy($dst, $src, 0, 0, $sx, $sy, $sw, $sh);
        }
        imagedestroy($src);

        // 回転
        if ($degrees = $this->getRotation()) {
            $degrees = ($degrees == 90) ? -90 : (($degrees == 270) ? 90: $degrees);
            $tmp = imagerotate($dst, $degrees, $bg);
            imagedestroy($dst);
            return $tmp;
        }

        return $dst;
    }

    // }}}
    // {{{ _decorateAnimationGif()

    /**
     * stamp animation gif mark.
     *
     * @param mixed $thumb
     * @return resource
     */
    protected function _decorateAnimationGif($thumb)
    {
        $deco = imagecreatefrompng($this->getDecorateAnigifFilePath());
        $deco_w = imagesx($deco);
        $deco_h = imagesy($deco);
        imagecopyresampled($thumb, $deco, 0, 0, 0, 0, imagesx($thumb), imagesy($thumb), imagesx($deco), imagesy($deco));
        imagedestroy($deco);
        return $thumb;
    }

    // }}}
    // {{{ _decorateGifCaution()

    /**
     * stamp gif caution mark.
     *
     * @param resource $thumb
     * @return resource
     */
    protected function _decorateGifCaution($thumb)
    {
        $deco = imagecreatefrompng($this->getDecorateGifCautionFilePath());
        $deco_w = imagesx($deco);
        $deco_h = imagesy($deco);
        // put to the center
        imagecopy($thumb, $deco, (imagesx($thumb) - $deco_w) / 2, (imagesy($thumb) - $deco_h) / 2, 0, 0, $deco_w, $deco_h);
        imagedestroy($deco);
        return $thumb;
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
