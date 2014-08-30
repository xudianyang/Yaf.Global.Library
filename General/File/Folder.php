<?php
namespace General\File;
use General\File\Exception\RuntimeException;
/**
 * 简单文件夹操作类
 *
 * @author: xudianyang
 * @version: Folder.php v-1.0.0, 2014-05-23 15:22 Created
 * @copyright Copyright (c) 2014 Beijing Jinritemai Technology Co.,Ltd.
 */

class Folder implements StorageInterface
{
    public function read($pattern)
    {
        return glob($pattern, GLOB_ONLYDIR);
    }

    public function write($structure, $mode = 0755)
    {
        if (is_dir($structure) || $structure == '') {
            return true;
        }

        if (self::write(dirname($structure), $mode)) {
            return @mkdir($structure, $mode);
        } else {
            throw new RuntimeException(sprintf('Folder::create can not mkdir %s', $structure));
        }
    }

    public function exists($path) {
        if (is_dir($path) || $path == '') {
            return true;
        }

        return false;
    }

    public function delete($path) {
        if (!is_dir($path)) return false;

        $path = self::path($path);
        $items = scandir($path);
        if (!is_array($items)) return true;

        foreach ($items as $v) {
            if($v == '.' || $v == '..') {
                continue;
            }
            $v = $path .$v;
            if (is_dir($v)) {
                self::delete($v);
            } else  {
                if(!@unlink($v)) {
                    throw new RuntimeException(sprintf('Folder::delete can not delete file %s', $v));
                }
            }
        }

        if(!@rmdir($path)) {
            throw new RuntimeException(sprintf('Folder::delete can not rmdir %s', $path));
        }

        return true;
    }

    public function clear($path)
    {
        if (!is_dir($path)) return false;

        $path = self::path($path);
        $items = glob($path.'*');
        if (!is_array($items)) return true;

        foreach ($items as $v) {
            if (is_dir($v)) {
                self::delete($v);
            } else {
                if(!@unlink($v)) {
                    throw new RuntimeException(sprintf('Folder::clear can not delete file %s', $v));
                }
            }
        }

        return true;
    }

    public function rename($oldpath, $newpath)
    {
        return rename($oldpath, $newpath);
    }

    function move($source, $target)
    {
        if (!is_dir($source)) return false;
        if (!is_dir($target)) self::create($target);

        $source = self::path($source);
        $target = self::path($target);
        $items  = glob($source.'*');
        if (!is_array($items)) return true;

        foreach ($items as $v) {
            $basename = basename($v);
            $to = $target.DS.$basename;
            if (is_dir($v)) {
                self::move($v, $to);
            } else  {
                if(!@rename($v, $to)) {
                    throw new RuntimeException(sprintf('Folder::move can not move file %s to %s', $v, $to));
                }
            }
        }

        if(!@rmdir($source)) {
            throw new RuntimeException(sprintf('Folder::move can not rmdir source %s', $source));
        }

        return true;
    }

    public function copy($source, $target, $mode = null, $pattern = null)
    {
        if(PHP_OS == 'WINNT') $mode = null;
        if (!is_dir($source)) return false;
        if (is_null($mode)) {
            if (!is_dir($target)) self::create($target);
            $source = self::path($source);
            $target = self::path($target);
            $items = glob($source.'*');
            if (!is_array($items)) return true;

            foreach ($items as $v) {
                $basename = basename($v);
                $to = $target.DS.$basename;
                if (is_dir($v)) {
                    self::copy($v, $to);
                } else {
                    if(!@copy($v, $to)) {
                        throw new RuntimeException(sprintf('Folder::copy can not copy file %s to %s', $v, $to));
                    }
                }
            }
        } else {
            $files = self::find($source, $pattern, $mode, true);
            foreach ($files as $file) {
                if (is_file($file)) {
                    $newfile = str_replace($source, $target, str_replace("\\", "/", $file));
                    self::create(dirname($newfile));
                    if (!copy($file, $newfile)) {
                        throw new RuntimeException(sprintf('Folder::copy can not copy file %s to %s', $file, $newfile));
                        return false;
                    }
                }
            }
        }
        return true;
    }

    public function find($path, $pattern, $mode = 'name', $deep = false, &$array = array())
    {
        if (!is_dir($path)) return false;
        $path = self::path($path);
        $items = glob($path.'*');
        if (!is_array($items)) return array();

        if ($mode == 'name') {
            $array = array_merge($array, preg_grep($pattern, $items));
        } elseif ($mode == 'data') {
            foreach ($items as $item) {
                if (is_file($item) && preg_grep($pattern, file_get_contents($item))) $array[] = $item;
            }
        } elseif ($mode == 'filemtime') {
            $filemtime = strtotime($pattern);
            foreach ($items as $item) {
                if (is_file($item) && @filemtime($item) >= $filemtime) $array[] = $item;
            }
        }
        if ($deep) {
            foreach ($items as $item) {
                if (is_dir($item)) self::find($item, $pattern, $mode, $deep, $array);
            }
        }
        return $array;
    }

    public function chmod($path, $mode = 0755)
    {
        if (!is_dir($path)) return false;
        $mode = intval($mode, 8);
        if(!@chmod($path, $mode)) {
            throw new RuntimeException(sprintf('Folder::chmod %s not changed to %s', $path, $mode));
        }

        $path = self::path($path);
        $items = glob($path.'*');
        if (!is_array($items)) return true;

        foreach ($items as $item) {
            if (is_dir($item)) {
                self::chmod($item, $mode);
            } else {
                if(!@chmod($item, $mode)) {
                    throw new RuntimeException(sprintf('Folder::chmod %s not changed to %s', $item, $mode));
                }
            }
        }

        return true;
    }

    public function touch($path, $mtime = 0, $atime = 0)
    {
        if (!is_dir($path)) return false;
        if(!@touch($path, $mtime, $atime)) {
            throw new RuntimeException(sprintf('Folder::touch %s not touch to %s', $path, $mtime));
        }

        $path = self::path($path);
        $items = glob($path.'*');
        if (!is_array($items)) return true;

        foreach ($items as $item) {
            if (is_dir($item)) {
                self::touch($path, $mtime, $atime);
            } else  {
                if(!@touch($item, $mtime, $atime)) {
                    throw new RuntimeException(sprintf('Folder::touch %s not touch to %s', $item, $mtime));
                }
            }
        }
        return true;
    }

    static function fileExtName($filename, $flag='.')
    {
        $filearea = explode ($flag,$filename );
        $partnum = count ( $filearea );
        $fileclass = $filearea[$partnum - 1];

        return $fileclass;
    }

    static function tree($path, $mode = null, &$array = array())
    {
        if (!is_dir($path)) return false;
        $path = self::path($path);
        $items = glob($path.'*');
        if (!is_array($items)) return $array;
        if ($mode === null) {
            foreach ($items as $item) {
                if (is_dir($item)) {
                    $array['dir'][] = $item;
                    self::tree($item, $mode, $array);
                } else {
                    $array['file'][] = $item;
                }
            }
        } elseif ($mode == 'file') {
            foreach ($items as $item) {
                if (is_dir($item)) {
                    self::tree($item, $mode, $array);
                } else {
                    $array[] = $item;
                }
            }
        } elseif ($mode == 'dir') {
            foreach ($items as $item) {
                if (is_dir($item)) {
                    $array[] = $item;
                    self::tree($item, $mode, $array);
                }
            }
        }

        return $array;
    }

    static function size($path)
    {
        if (!is_dir($path)) return false;
        $size = 0;
        $path = self::path($path);
        $items = glob($path.'*');
        if (!is_array($items)) return $size;
        foreach ($items as $item) {
            if (is_dir($item)) {
                $size += self::size($item);
            } else {
                $size += filesize($item);
            }
        }

        return $size;
    }

    public function sizeunit($filesize)
    {
        if($filesize >= 1073741824) {
            $filesize = round($filesize / 1073741824 * 100) / 100 . ' GB';
        } elseif($filesize >= 1048576) {
            $filesize = round($filesize / 1048576 * 100) / 100 . ' MB';
        } elseif($filesize >= 1024) {
            $filesize = round($filesize / 1024 * 100) / 100 . ' KB';
        } else {
            $filesize = $filesize . ' Bytes';
        }

        return $filesize;
    }

    public function path($path)
    {
        return rtrim(preg_replace("|[\/]+|", DS, $path), DS).DS;
    }
}