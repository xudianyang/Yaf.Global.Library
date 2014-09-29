<?php
namespace General\File\Qiniu;
use General\File\StorageInterface;
use General\File\Exception\RuntimeException;

require_once("io.php");
require_once("rs.php");
require_once("fop.php");

class Qiniu implements StorageInterface
{
    public $accessKey;
    public $secretKey;
    public $bucket;
    public $key;

    public function __construct($option)
    {
        global $QINIU_ACCESS_KEY;
        global $QINIU_SECRET_KEY;

        if (empty($option['accessKey']) || empty($option['secretKey']) || empty($option['bucket'])) {
            throw new RuntimeException("Qiniu AccessKey or SecretKey or Bucket Not Set");
        }

        $QINIU_ACCESS_KEY = $option['accessKey'];
        $QINIU_SECRET_KEY = $option['secretKey'];

        $this->accessKey = $QINIU_ACCESS_KEY;
        $this->secretKey = $QINIU_SECRET_KEY;
        $this->bucket    = $option['bucket'];

        $this->domains = $option['domains'];
    }

    public function exists($filename)
    {
        $this->setBucketAndKey($filename);

        Qiniu_SetKeys($this->accessKey, $this->secretKey);
        $client = new \Qiniu_MacHttpClient(null);

        $result = Qiniu_RS_Stat($client, $this->bucket, $this->key);

        if ($result[0] !== NULL) {
            return true;
        } else {
            return false;
        }
    }

    public function read($filename) {
        $this->setBucketAndKey($filename);

        Qiniu_SetKeys($this->accessKey, $this->secretKey);
        $domain = $this->getDomain();
        $baseUrl = Qiniu_RS_MakeBaseUrl($domain, $this->key);

        return $baseUrl;
    }

    public function write($filename, $filePath)
    {
        $this->setBucketAndKey($filename);

        Qiniu_SetKeys($this->accessKey, $this->secretKey);
        $putPolicy = new \Qiniu_RS_PutPolicy($this->bucket);
        $upToken = $putPolicy->Token(null);
        $putExtra = new \Qiniu_PutExtra();
        $putExtra->Crc32 = 1;
        list($ret, $err) = Qiniu_PutFile($upToken, $this->key, $filePath, $putExtra);

        if ($err !== null) {
            throw new RuntimeException(
                'Qiniu::write Error. ' .
                'TargetFilename: ' . $filename . ', LocalFile: ' . $filePath .
                ', Reqid: ' . $err->Reqid . ', Err: ' . $err->Err, $err->Code
            );
        } else {
            return $ret['hash'];
        }
    }

    public function delete($filename)
    {
        $this->setBucketAndKey($filename);

        Qiniu_SetKeys($this->accessKey, $this->secretKey);
        $client = new \Qiniu_MacHttpClient(null);

        $err = Qiniu_RS_Delete($client, $this->bucket, $this->key);

        if ($err !== null) {
            return false;
        } else {
            return true;
        }
    }

    public function imageInfo($filename)
    {
        $this->setBucketAndKey($filename);

        Qiniu_SetKeys($this->accessKey, $this->secretKey);
        $domain = $this->getDomain();
        $baseUrl = Qiniu_RS_MakeBaseUrl($domain, $this->key);

        $imgInfo = new \Qiniu_ImageInfo;
        $imgInfoUrl = $imgInfo->MakeRequest($baseUrl);

        $info = json_decode(@file_get_contents($imgInfoUrl), true);

        return $info;
    }

    public function imageThumb($filename, $mode, $width, $height)
    {
        $this->setBucketAndKey($filename);
        Qiniu_SetKeys($this->accessKey, $this->secretKey);
        $domain = $this->getDomain();
        $baseUrl = Qiniu_RS_MakeBaseUrl($domain, $this->key);

        $imgView = new \Qiniu_ImageView();
        $imgView->Mode = $mode;
        $imgView->Width = $width;
        $imgView->Height = $height;
        $imgViewUrl = $imgView->MakeRequest($baseUrl);

        return $imgViewUrl;
    }

    public function setBucketAndKey($filename)
    {
        $filename = explode(":", $filename, 2);
        if (count($filename) == 2) {
            $this->bucket = $filename[0];
            $this->key    = $filename[1];
        } else {
            $this->key = $filename[0];
        }
    }

    public function getDomain()
    {
        $index = array_rand($this->domains);

        return $this->domains[$index];
    }
}