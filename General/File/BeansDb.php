<?php
/**
 *
 * @author
 * @copyright Copyright (c) Beijing Jinritemai Technology Co.,Ltd.
 */

namespace General\File;

use Memcache as MemcacheResource;

class BeansDb implements StorageInterface
{
    const DEFAULT_PORT = 11211;

    const DEFAULT_WEIGHT = 1;

    const DEFAULT_PERSISTENT = true;

    /**
     * @var \Memcache
     */
    protected $resource;

    public function __construct($resource = null)
    {
        if (null !== $resource) {
            $this->setResource($resource);
        }
    }

    /**
     * @param \Memcache $resource
     * @return $this
     * @throws Exception\InvalidArgumentException
     */
    public function setResource($resource)
    {
        if ($resource instanceof MemcacheResource) {
            $this->resource = $resource;
            return $this;
        }
        if (is_string($resource)) {
            $resource = array($resource);
        }
        if (!is_array($resource) && !$resource instanceof \ArrayAccess) {
            throw new Exception\InvalidArgumentException(sprintf(
                '%s: expects an string, array, or Traversable argument; received "%s"',
                __METHOD__, (is_object($resource) ? get_class($resource) : gettype($resource))
            ));
        }

        $host = $port = $weight = $persistent = null;
        // array(<host>[, <port>[, <weight>[, <persistent>]]])
        if (isset($resource[0])) {
            $host = (string) $resource[0];
            if (isset($resource[1])) {
                $port = (int) $resource[1];
            }
            if (isset($resource[2])) {
                $weight = (int) $resource[2];
            }
            if (isset($resource[3])) {
                $persistent = (bool) $resource[3];
            }
        }
        // array('host' => <host>[, 'port' => <port>[, 'weight' => <weight>[, 'persistent' => <persistent>]]])
        elseif (isset($resource['host'])) {
            $host = (string) $resource['host'];
            if (isset($resource['port'])) {
                $port = (int) $resource['port'];
            }
            if (isset($resource['weight'])) {
                $weight = (int) $resource['weight'];
            }
            if (isset($resource['persistent'])) {
                $persistent = (bool) $resource['persistent'];
            }
        }

        if (!$host) {
            throw new Exception\InvalidArgumentException('Invalid beansdb resource, option "host" must be given');
        }

        $this->resource = array(
            'host' => $host,
            'port' => $port === null ? self::DEFAULT_PORT : $port,
            'weight' => $weight <= 0 ? self::DEFAULT_WEIGHT : $weight,
            'persistent' => $persistent === null ? self::DEFAULT_PERSISTENT : $persistent
        );

        return $this;
    }

    protected function getResource()
    {
        if (!$this->resource) {
            throw new Exception\RuntimeException('Memcache resource must be set');
        }
        if (!$this->resource instanceof MemcacheResource) {
            $resource = new MemcacheResource;
            if (!$resource->addserver($this->resource['host'], $this->resource['port'],
                $this->resource['persistent'], $this->resource['weight'])) {
                throw new Exception\RuntimeException(sprintf(
                    'Cannot connect to beansdb server on %s:%d',
                    $this->resource['host'], $this->resource['port']
                ));
            }

            $this->resource = $resource;
        }
        return $this->resource;
    }

    public function exists($key)
    {
        return $this->getResource()->get('?' . $key) ? true : false;
    }

    public function read($key)
    {
        return $this->getResource()->get($key);
    }

    public function write($key, $value)
    {
        return $this->getResource()->set($key, $value);
    }

    public function delete($key)
    {
        return $this->getResource()->delete($key);
    }
}