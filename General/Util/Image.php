<?php
/**
 * Yaf.app Framework
 *
 * @copyright Copyright (c) 2009, Ian Selby/Gen X Design (http://phpthumb.gxdlabs.com)
 * @copyright Copyright (c) 2013 Beijing Jinritemai Technology Co.,Ltd. (http://www.Jinritemai.com)
 */

namespace General\Util;

class Image
{
    const TYPE_JPEG = 'jpeg';

    const TYPE_PNG = 'png';

    const TYPE_GIF = 'gif';

    protected $filename;

    protected $remote;

    protected $binary;

    protected $format;

    protected $oldImage;

    protected $currentDimensions;

    protected $newDimensions;

    protected $workingImage;

    protected $options;

    protected $maxWidth;

    protected $maxHeight;

    protected $percent;

    protected static $defaultOptions = array(
        'resizeUp' => false,
        'jpegQuality' => 100,
        'jpegInterlace' => true,
        'correctPermissions' => false,
        'preserveAlpha' => true,
        'alphaMaskColor' => array(255, 255, 255),
        'preserveTransparency' => true,
        'transparencyMaskColor' => array(0, 0, 0)
    );

    protected static $allowedFormats = array(
        self::TYPE_JPEG, self::TYPE_PNG, self::TYPE_GIF, 'jpg'
    );

    public function __construct($filename = null, array $options = array(), $binary = false, $type = null)
    {
        $this->filename = $filename;
        $this->remote = false;
        $this->binary = (bool)$binary;

        $this->assertFileAvaiable();
        $this->detectFormat($type);

        if ($this->binary) {
            $this->oldImage = @imagecreatefromstring($this->filename);
        } else {
            switch ($this->format) {
                case self::TYPE_PNG:
                    $this->oldImage = @imagecreatefrompng($this->filename);
                    break;
                case self::TYPE_JPEG:
                    $this->oldImage = @imagecreatefromjpeg($this->filename);
                    break;
                case self::TYPE_GIF:
                    $this->oldImage = @imagecreatefromgif($this->filename);
                    break;
            }
        }

        if (!$this->oldImage) {
            $error = error_get_last();
            throw new \InvalidArgumentException('create image with given resource failed' . ($error ? (' with error ' . $error['message']) : ''));
        }

        $this->currentDimensions = array(
            'width' => imagesx($this->oldImage),
            'height' => imagesy($this->oldImage)
        );

        $this->setOptions($options);
    }

    # ---------------------------------- Public Methods ---------------------------------- #

    /**
     * 缩放
     *
     * @param $maxWidth
     * @param $maxHeight
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function resize($maxWidth, $maxHeight)
    {
        // make sure our arguments are valid
        if (!is_numeric($maxWidth)) {
            throw new \InvalidArgumentException('$maxWidth must be numeric');
        }

        if (!is_numeric($maxHeight)) {
            throw new \InvalidArgumentException('$maxHeight must be numeric');
        }

        // make sure we're not exceeding our image size if we're not supposed to
        if ($this->options['resizeUp'] === false) {
            $this->maxHeight = (intval($maxHeight) > $this->currentDimensions['height']) ? $this->currentDimensions['height'] : $maxHeight;
            $this->maxWidth = (intval($maxWidth) > $this->currentDimensions['width']) ? $this->currentDimensions['width'] : $maxWidth;
        } else {
            $this->maxHeight = intval($maxHeight);
            $this->maxWidth = intval($maxWidth);
        }

        // get the new dimensions...
        $this->calcImageSize($this->currentDimensions['width'], $this->currentDimensions['height']);

        // create the working image
        if (function_exists('imagecreatetruecolor')) {
            $this->workingImage = imagecreatetruecolor($this->newDimensions['newWidth'], $this->newDimensions['newHeight']);
        } else {
            $this->workingImage = imagecreate($this->newDimensions['newWidth'], $this->newDimensions['newHeight']);
        }

        $this->preserveAlpha();

        // and create the newly sized image
        imagecopyresampled(
            $this->workingImage, $this->oldImage, 0, 0, 0, 0,
            $this->newDimensions['newWidth'], $this->newDimensions['newHeight'],
            $this->currentDimensions['width'], $this->currentDimensions['height']
        );

        // update all the variables and resources to be correct
        $this->oldImage = $this->workingImage;
        $this->currentDimensions['width'] = $this->newDimensions['newWidth'];
        $this->currentDimensions['height'] = $this->newDimensions['newHeight'];

        return $this;
    }

    /**
     * 缩放并裁剪
     *
     * @param $width
     * @param $height
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function resizeCrop($width, $height)
    {
        if (!is_numeric($width) || $width == 0) {
            throw new \InvalidArgumentException('$width must be numeric and greater than zero');
        }

        if (!is_numeric($height) || $height == 0) {
            throw new \InvalidArgumentException('$height must be numeric and greater than zero');
        }

        // make sure we're not exceeding our image size if we're not supposed to
        if ($this->options['resizeUp'] === false) {
            $this->maxHeight = (intval($height) > $this->currentDimensions['height']) ? $this->currentDimensions['height'] : $height;
            $this->maxWidth = (intval($width) > $this->currentDimensions['width']) ? $this->currentDimensions['width'] : $width;
        } else {
            $this->maxHeight = intval($height);
            $this->maxWidth = intval($width);
        }

        $this->calcImageSizeStrict($this->currentDimensions['width'], $this->currentDimensions['height']);

        // resize the image to be close to our desired dimensions
        $this->resize($this->newDimensions['newWidth'], $this->newDimensions['newHeight']);

        // reset the max dimensions...
        if ($this->options['resizeUp'] === false) {
            $this->maxHeight = (intval($height) > $this->currentDimensions['height']) ? $this->currentDimensions['height'] : $height;
            $this->maxWidth = (intval($width) > $this->currentDimensions['width']) ? $this->currentDimensions['width'] : $width;
        } else {
            $this->maxHeight = intval($height);
            $this->maxWidth = intval($width);
        }

        // create the working image
        if (function_exists('imagecreatetruecolor')) {
            $this->workingImage = imagecreatetruecolor($this->maxWidth, $this->maxHeight);
        } else {
            $this->workingImage = imagecreate($this->maxWidth, $this->maxHeight);
        }

        $this->preserveAlpha();

        $cropWidth = $this->maxWidth;
        $cropHeight = $this->maxHeight;
        $cropX = 0;
        $cropY = 0;

        // now, figure out how to crop the rest of the image...
        if ($this->currentDimensions['width'] > $this->maxWidth) {
            $cropX = intval(($this->currentDimensions['width'] - $this->maxWidth) / 2);
        } elseif ($this->currentDimensions['height'] > $this->maxHeight) {
            $cropY = intval(($this->currentDimensions['height'] - $this->maxHeight) / 2);
        }

        imagecopyresampled(
            $this->workingImage, $this->oldImage, 0, 0, $cropX, $cropY,
            $cropWidth, $cropHeight,
            $cropWidth, $cropHeight
        );

        // update all the variables and resources to be correct
        $this->oldImage = $this->workingImage;
        $this->currentDimensions['width'] = $this->maxWidth;
        $this->currentDimensions['height'] = $this->maxHeight;

        return $this;
    }

    /**
     * 按比例缩放
     *
     * @param int $percent
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function resizePercent($percent = 0)
    {
        if (!is_numeric($percent)) {
            throw new \InvalidArgumentException ('$percent must be numeric');
        }

        $this->percent = intval($percent);

        $this->calcImageSizePercent($this->currentDimensions['width'], $this->currentDimensions['height']);

        if (function_exists('imagecreatetruecolor')) {
            $this->workingImage = imagecreatetruecolor($this->newDimensions['newWidth'], $this->newDimensions['newHeight']);
        } else {
            $this->workingImage = imagecreate($this->newDimensions['newWidth'], $this->newDimensions['newHeight']);
        }

        $this->preserveAlpha();

        ImageCopyResampled(
            $this->workingImage, $this->oldImage, 0, 0, 0, 0,
            $this->newDimensions['newWidth'], $this->newDimensions['newHeight'],
            $this->currentDimensions['width'], $this->currentDimensions['height']
        );

        $this->oldImage = $this->workingImage;
        $this->currentDimensions['width'] = $this->newDimensions['newWidth'];
        $this->currentDimensions['height'] = $this->newDimensions['newHeight'];

        return $this;
    }

    /**
     * 裁剪
     *
     * @param $startX
     * @param $startY
     * @param $cropWidth
     * @param $cropHeight
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function crop($startX, $startY, $cropWidth, $cropHeight)
    {
        // validate input
        if (!is_numeric($startX)) {
            throw new \InvalidArgumentException('$startX must be numeric');
        }

        if (!is_numeric($startY)) {
            throw new \InvalidArgumentException('$startY must be numeric');
        }

        if (!is_numeric($cropWidth)) {
            throw new \InvalidArgumentException('$cropWidth must be numeric');
        }

        if (!is_numeric($cropHeight)) {
            throw new \InvalidArgumentException('$cropHeight must be numeric');
        }

        // do some calculations
        $cropWidth = ($this->currentDimensions['width'] < $cropWidth) ? $this->currentDimensions['width'] : $cropWidth;
        $cropHeight = ($this->currentDimensions['height'] < $cropHeight) ? $this->currentDimensions['height'] : $cropHeight;

        // ensure everything's in bounds
        if (($startX + $cropWidth) > $this->currentDimensions['width']) {
            $startX = ($this->currentDimensions['width'] - $cropWidth);
        }

        if (($startY + $cropHeight) > $this->currentDimensions['height']) {
            $startY = ($this->currentDimensions['height'] - $cropHeight);
        }

        if ($startX < 0) {
            $startX = 0;
        }

        if ($startY < 0) {
            $startY = 0;
        }

        // create the working image
        if (function_exists('imagecreatetruecolor')) {
            $this->workingImage = imagecreatetruecolor($cropWidth, $cropHeight);
        } else {
            $this->workingImage = imagecreate($cropWidth, $cropHeight);
        }

        $this->preserveAlpha();

        imagecopyresampled
        (
            $this->workingImage, $this->oldImage, 0, 0, $startX, $startY,
            $cropWidth, $cropHeight,
            $cropWidth, $cropHeight
        );

        $this->oldImage = $this->workingImage;
        $this->currentDimensions['width'] = $cropWidth;
        $this->currentDimensions['height'] = $cropHeight;

        return $this;
    }

    /**
     * 居中裁剪
     *
     * @param $cropWidth
     * @param null $cropHeight
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function cropFromCenter($cropWidth, $cropHeight = null)
    {
        if (!is_numeric($cropWidth)) {
            throw new \InvalidArgumentException('$cropWidth must be numeric');
        }

        if ($cropHeight !== null && !is_numeric($cropHeight)) {
            throw new \InvalidArgumentException('$cropHeight must be numeric');
        }

        if ($cropHeight === null) {
            $cropHeight = $cropWidth;
        }

        $cropWidth = ($this->currentDimensions['width'] < $cropWidth) ? $this->currentDimensions['width'] : $cropWidth;
        $cropHeight = ($this->currentDimensions['height'] < $cropHeight) ? $this->currentDimensions['height'] : $cropHeight;

        $cropX = intval(($this->currentDimensions['width'] - $cropWidth) / 2);
        $cropY = intval(($this->currentDimensions['height'] - $cropHeight) / 2);

        $this->crop($cropX, $cropY, $cropWidth, $cropHeight);

        return $this;
    }

    /**
     * 旋转图片 90 度（左右）
     *
     * @param string $direction
     * @return $this
     */
    public function rotateImage($direction = 'right')
    {
        switch ($direction) {
            case 'left':
                $this->rotateImageNDegrees(-90);
                break;
            case 'right':
            default:
                $this->rotateImageNDegrees(90);
                break;
        }

        return $this;
    }

    /**
     * 按角度旋转图片
     *
     * @param $degrees
     * @return $this
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     */
    public function rotateImageNDegrees($degrees)
    {
        if (!is_numeric($degrees)) {
            throw new \InvalidArgumentException('$degrees must be numeric');
        }

        if (!function_exists('imagerotate')) {
            throw new \RuntimeException('Your version of GD does not support image rotation.');
        }

        $this->workingImage = imagerotate($this->oldImage, $degrees, 0);

        $newWidth = $this->currentDimensions['height'];
        $newHeight = $this->currentDimensions['width'];
        $this->oldImage = $this->workingImage;
        $this->currentDimensions['width'] = $newWidth;
        $this->currentDimensions['height'] = $newHeight;

        return $this;
    }

    /**
     * 显示图片
     *
     * @param bool $send_header
     * @param null $format
     * @return $this
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     */
    public function show($send_header = true, $format = null)
    {
        if ($send_header && headers_sent()) {
            throw new \RuntimeException('headers have already been sent');
        }

        $format = ($format !== null) ? strtolower($format) : $this->format;

        if (!in_array($format, self::$allowedFormats)) {
            throw new \InvalidArgumentException ('Invalid format type specified in save function: ' . $format);
        }

        $send_header && header('Content-type: image/' . $format);

        switch ($format) {
            case self::TYPE_PNG:
                imagepng($this->oldImage);
                break;
            case self::TYPE_JPEG:
                if ($this->options['jpegInterlace'] === true) {
                    imageinterlace($this->oldImage, 1);
                }
                imagejpeg($this->oldImage, null, $this->options['jpegQuality']);
                break;
            case self::TYPE_GIF;
                imagegif($this->oldImage);
                break;
        }

        return $this;
    }

    /**
     * 获取二进制的图片
     *
     * @param null $format
     * @return string
     */
    public function getImageAsString($format = null)
    {
        $data = null;
        ob_start();
        $this->show(false, $format);
        $data = ob_get_contents();
        ob_end_clean();

        return $data;
    }

    /**
     * 保存图片到文件
     *
     * @param $filename
     * @param null $format
     * @return $this
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     */
    public function save($filename, $format = null)
    {
        $format = ($format !== null) ? strtolower($format) : $this->format;

        if (!in_array($format, self::$allowedFormats)) {
            throw new \InvalidArgumentException ('Invalid format type specified in save function: ' . $format);
        }

        // make sure the directory is writeable
        if (!is_writeable(dirname($filename))) {
            // try to correct the permissions
            if ($this->options['correctPermissions'] === true) {
                @chmod(dirname($filename), 0777);

                // throw an exception if not writeable
                if (!is_writeable(dirname($filename))) {
                    throw new \RuntimeException ('File is not writeable, and could not correct permissions: ' . $filename);
                }
            } // throw an exception if not writeable
            else {
                throw new \RuntimeException ('File not writeable: ' . $filename);
            }
        }

        switch ($format) {
            case self::TYPE_PNG:
                imagepng($this->oldImage, $filename);
                break;
            case self::TYPE_JPEG:
            case 'jpg':
                if ($this->options['jpegInterlace'] === true) {
                    imageinterlace($this->oldImage, 1);
                }
                imagejpeg($this->oldImage, $filename, $this->options['jpegQuality']);
                break;
            case self::TYPE_GIF:
                imagegif($this->oldImage, $filename);
                break;
        }

        return $this;
    }

    # ---------------------------------- Getter && Setters ---------------------------------- #

    /**
     * 设置参数
     *
     * @param array $options
     */
    public function setOptions(array $options = array())
    {
        if (sizeof($this->options) == 0) {
            $this->options = array_merge(self::$defaultOptions, $options);
        } else {
            $this->options = array_merge($this->options, $options);
        }
    }

    /**
     * 获取参数
     *
     * @return mixed
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * 获取当前文件尺寸
     *
     * @return array
     */
    public function getCurrentDimensions()
    {
        return $this->currentDimensions;
    }

    /**
     * 获取裁剪或缩放到的尺寸
     *
     * @return mixed
     */
    public function getNewDimensions()
    {
        return $this->newDimensions;
    }

    /**
     * 获取原始图片句柄
     *
     * @return resource
     */
    public function getOldImage()
    {
        return $this->oldImage;
    }

    public function __destruct()
    {
        if (is_resource($this->oldImage)) {
            imagedestroy($this->oldImage);
        }

        if (is_resource($this->workingImage)) {
            imagedestroy($this->workingImage);
        }
    }

    # ---------------------------------- Helpers ---------------------------------- #

    protected function assertFileAvaiable()
    {
        if ($this->binary === true) {
            return;
        }

        if (stristr($this->filename, 'http://')) {
            $this->remote = true;
            return;
        }

        if (!is_file($this->filename)) {
            throw new \InvalidArgumentException('Image file not exists: ' . $this->filename);
        }

        if (!is_readable($this->filename)) {
            throw new \RuntimeException('Image file not readable: ' . $this->filename);
        }
    }

    protected function detectFormat($type = null)
    {
        if ($this->binary) {
            if (!$type || !in_array($type, self::$allowedFormats)) {
                throw new \InvalidArgumentException('Binary image type must be specified');
            }
            $mime = 'image/' . $type;
        }

        if (empty($mime)) {
            $info = getimagesize($this->filename);
            if ($info === false) {
                throw new \RuntimeException('Read image infomation failed: ' . $this->filename);
            }
            $mime = isset($info['mime']) ? $info['mime'] : null;
        }

        $gdinfo = gd_info();

        switch ($mime) {
            case 'image/png':
                $this->format = self::TYPE_PNG;
                $compatible = $gdinfo['PNG Support'];
                break;
            case 'image/jpeg':
            case 'image/jpg':
                $this->format = self::TYPE_JPEG;
                $compatible = (isset($gdinfo['JPG Support']) || isset($gdinfo['JPEG Support'])) ? true : false;
                break;
            case 'image/gif':
                $this->format = self::TYPE_GIF;
                $compatible = $gdinfo['GIF Create Support'];
                break;
            default:
                throw new \RuntimeException('Image format not supported: ' . $mime);
                break;
        }

        if (!$compatible) {
            throw new \RuntimeException('Your GD installation does not support ' . $this->format . ' image types');
        }
    }

    protected function calcPercent($width, $height)
    {
        $newWidth = ($width * $this->percent) / 100;
        $newHeight = ($height * $this->percent) / 100;

        return array(
            'newWidth' => ceil($newWidth),
            'newHeight' => ceil($newHeight)
        );
    }

    protected function calcImageSize($width, $height)
    {
        $newsize = array(
            'newWidth' => $width,
            'newHeight' => $height
        );

        if ($this->maxWidth > 0) {
            $newsize = $this->calcWidth($width, $height);
            if ($this->maxHeight > 0 && $newsize['newHeight'] > $this->maxHeight) {
                $newsize = $this->calcHeight($newsize['newWidth'], $newsize['newHeight']);
            }
        }

        if ($this->maxHeight > 0) {
            $newsize = $this->calcHeight($width, $height);
            if ($this->maxWidth > 0 && $newsize['newWidth'] > $this->maxWidth) {
                $newsize = $this->calcWidth($newsize['newWidth'], $newsize['newHeight']);
            }
        }

        $this->newDimensions = $newsize;
    }

    protected function calcImageSizeStrict($width, $height)
    {
        // first, we need to determine what the longest resize dimension is..
        if ($this->maxWidth >= $this->maxHeight) {
            // and determine the longest original dimension
            if ($width > $height) {
                $newDimensions = $this->calcHeight($width, $height);

                if ($newDimensions['newWidth'] < $this->maxWidth) {
                    $newDimensions = $this->calcWidth($width, $height);
                }
            } elseif ($height >= $width) {
                $newDimensions = $this->calcWidth($width, $height);

                if ($newDimensions['newHeight'] < $this->maxHeight) {
                    $newDimensions = $this->calcHeight($width, $height);
                }
            }
        } elseif ($this->maxHeight > $this->maxWidth) {
            if ($width >= $height) {
                $newDimensions = $this->calcWidth($width, $height);

                if ($newDimensions['newHeight'] < $this->maxHeight) {
                    $newDimensions = $this->calcHeight($width, $height);
                }
            } elseif ($height > $width) {
                $newDimensions = $this->calcHeight($width, $height);

                if ($newDimensions['newWidth'] < $this->maxWidth) {
                    $newDimensions = $this->calcWidth($width, $height);
                }
            }
        }

        $this->newDimensions = $newDimensions;
    }

    protected function calcImageSizePercent($width, $height)
    {
        if ($this->percent > 0) {
            $this->newDimensions = $this->calcPercent($width, $height);
        }
    }

    protected function calcWidth($width, $height)
    {
        $newWidthPercentage = (100 * $this->maxWidth) / $width;
        $newHeight = ($height * $newWidthPercentage) / 100;

        return array(
            'newWidth' => intval($this->maxWidth),
            'newHeight' => intval($newHeight)
        );
    }

    protected function calcHeight($width, $height)
    {
        $newHeightPercentage = (100 * $this->maxHeight) / $height;
        $newWidth = ($width * $newHeightPercentage) / 100;

        return array(
            'newWidth' => ceil($newWidth),
            'newHeight' => ceil($this->maxHeight)
        );
    }

    protected function preserveAlpha()
    {
        if ($this->format == self::TYPE_PNG && $this->options['preserveAlpha'] === true) {
            imagealphablending($this->workingImage, false);

            $colorTransparent = imagecolorallocatealpha(
                $this->workingImage,
                $this->options['alphaMaskColor'][0],
                $this->options['alphaMaskColor'][1],
                $this->options['alphaMaskColor'][2],
                0
            );

            imagefill($this->workingImage, 0, 0, $colorTransparent);
            imagesavealpha($this->workingImage, true);
        }

        if ($this->format == self::TYPE_GIF && $this->options['preserveTransparency'] === true) {
            $colorTransparent = imagecolorallocate(
                $this->workingImage,
                $this->options['transparencyMaskColor'][0],
                $this->options['transparencyMaskColor'][1],
                $this->options['transparencyMaskColor'][2]
            );

            imagecolortransparent($this->workingImage, $colorTransparent);
            imagetruecolortopalette($this->workingImage, true, 256);
        }
    }
}