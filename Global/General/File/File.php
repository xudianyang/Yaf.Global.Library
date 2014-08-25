<?php
namespace General\File;
use General\File\Exception\RuntimeException;

class File implements StorageInterface
{
    private $_basePath = '/tmp/';

    public function __construct($options)
    {
        if (!empty($options['basePath'])) {
            $this->_basePath = $options['basePath'];
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
        return file_get_contents($filename);
    }

    public function write($filename, $content) {
        if ($filename[0] != '/') {
            $filename = $this->_basePath . $filename;
        }

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
}