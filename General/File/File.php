<?php
namespace General\File;
use General\File\Exception\RuntimeException;

class File implements StorageInterface
{
    private $_basePath = '/tmp/';

    public function __construct($options)
    {
        if (!empty($options['basePath'])) {
            $this->_basePath = rtrim($options['basePath'], '/') . '/';
        }
    }

    public function exists($filename) {
        if (file_exists($filename)) {
            return true;
        } else {
            return false;
        }
    }

    public function read($filename) {
        if ((strpos($filename, 'http://') === false && strpos($filename, 'https://') === false) &&
            $filename[0] != '/') {
            $filename = $this->_basePath . $filename;
        }

        $options = array(
            'http'=>array(
                'method'    => 'GET',
                'timeout'   => 3,
            )
        );

        $i = 0;
        $context = stream_context_create($options);
        while (($data = file_get_contents($filename, false, $context)) === false && $i < 3) $i++;
        return $data;
    }

    public function write($filename, $content) {
        if ($filename[0] != '/') {
            $filename = $this->_basePath . $filename;
        }

        $dir = dirname($filename);
        $this->mkdir($dir);

        if (file_put_contents($filename, $content)) {
            return true;
        } else {
            throw new RuntimeException(sprintf('File::write can not delete file %s', $filename));
        }
    }

    public function delete($filename) {
        if(!@unlink($filename)) {
            throw new RuntimeException(sprintf('File::delete can not delete file %s', $filename));
        }

        return true;
    }

    public function setBasePath($path) {
        $this->_basePath = rtrim($path, '/') . '/';
        return $this;
    }

    public function mkdir($structure, $mode = 0755)
    {
        if (is_dir($structure) || $structure == '') {
            return true;
        }

        if (self::mkdir(dirname($structure), $mode)) {
            return @mkdir($structure, $mode);
        } else {
            throw new RuntimeException(sprintf('File::mkdir can not mkdir %s', $structure));
        }
    }
}