<?php

namespace Puscha\Helper\Flysystem;

use League\Flysystem\AdapterInterface;
use League\Flysystem\Exception;
use League\Flysystem\Filesystem;
use League\Flysystem\Handler;
use Psr\Log\LoggerInterface;

class LoggableFilesystem extends Filesystem
{
    const WITHOUT_RESULT = '##none##';

    /** @var LoggerInterface */
    protected $logger;

    public function __construct(AdapterInterface $adapter, $config = null, $logger = null)
    {
        parent::__construct($adapter, $config);

        $this->logger = $logger;
    }

    protected function log($method, $string, $result = self::WITHOUT_RESULT)
    {
        // Getting a name for the adapter from the class' name, and making it nicely displayable
        $adapterName = get_class($this->getAdapter());
        if ($pos = strrpos($adapterName, '\\')) $adapterName = substr($adapterName, $pos + 1);
        $adapterName = str_replace('Adapter', '', $adapterName);
        $adapterName = strtolower($adapterName);

        // Preparing result to be nicely displayable
        $resultString = ' # ';
        if ($result !== self::WITHOUT_RESULT) { // using this special string for absence check as other true/false/null values are real data
            if (is_string($result)) {
                // Removing class' namespace if found
                if ($pos = strrpos($result, '\\')) {
                    $resultString .= substr($result, $pos + 1);
                } else {
                    $resultString .= $result;
                }
            } else {
                $resultString .= var_export($result, true);
            }
        }

        $this->logger->debug($adapterName.': '.$method.': '.$string.$resultString);
    }

    /**
     * @inheritdoc
     */
    public function has($path)
    {
        try {
            $r = parent::has($path);

            $this->log(__FUNCTION__, $path, $r);

            return $r;
        } catch (Exception $e) {
            $this->log(__FUNCTION__, $path, get_class($e));

            throw $e;
        }
    }

    /**
     * @inheritdoc
     */
    public function write($path, $contents, array $config = [])
    {
        try {
            $r = parent::write($path, $contents, $config);

            $this->log(__FUNCTION__, $path, $r);

            return $r;
        } catch (Exception $e) {
            $this->log(__FUNCTION__, $path, get_class($e));

            throw $e;
        }
    }

    /**
     * @inheritdoc
     */
    public function writeStream($path, $resource, array $config = [])
    {
        try {
            $r = parent::writeStream($path, $resource, $config);

            $this->log(__FUNCTION__, $path, $r);

            return $r;
        } catch (Exception $e) {
            $this->log(__FUNCTION__, $path, get_class($e));

            throw $e;
        }
    }

    /**
     * @inheritdoc
     */
    public function put($path, $contents, array $config = [])
    {
        try {
            $r = parent::put($path, $contents, $config);

            $this->log(__FUNCTION__, $path, $r);

            return $r;
        } catch (Exception $e) {
            $this->log(__FUNCTION__, $path, get_class($e));

            throw $e;
        }
    }

    /**
     * @inheritdoc
     */
    public function putStream($path, $resource, array $config = [])
    {
        try {
            $r = parent::putStream($path, $resource, $config);

            $this->log(__FUNCTION__, $path, $r);

            return $r;
        } catch (Exception $e) {
            $this->log(__FUNCTION__, $path, get_class($e));

            throw $e;
        }
    }

    /**
     * @inheritdoc
     */
    public function readAndDelete($path)
    {
        try {
            $r = parent::readAndDelete($path);

            $this->log(__FUNCTION__, $path, ($r !== false));

            return $r;
        } catch (Exception $e) {
            $this->log(__FUNCTION__, $path, get_class($e));

            throw $e;
        }
    }

    /**
     * @inheritdoc
     */
    public function update($path, $contents, array $config = [])
    {
        try {
            $r = parent::update($path, $contents, $config);

            $this->log(__FUNCTION__, $path, $r);

            return $r;
        } catch (Exception $e) {
            $this->log(__FUNCTION__, $path, get_class($e));

            throw $e;
        }
    }

    /**
     * @inheritdoc
     */
    public function updateStream($path, $resource, array $config = [])
    {
        try {
            $r = parent::updateStream($path, $resource, $config);

            $this->log(__FUNCTION__, $path, $r);

            return $r;
        } catch (Exception $e) {
            $this->log(__FUNCTION__, $path, get_class($e));

            throw $e;
        }
    }

    /**
     * @inheritdoc
     */
    public function read($path)
    {
        try {
            $r = parent::read($path);

            $this->log(__FUNCTION__, $path, ($r !== false));

            return $r;
        } catch (Exception $e) {
            $this->log(__FUNCTION__, $path, get_class($e));

            throw $e;
        }
    }

    /**
     * @inheritdoc
     */
    public function readStream($path)
    {
        try {
            $r = parent::readStream($path);

            $this->log(__FUNCTION__, $path, ($r !== false));

            return $r;
        } catch (Exception $e) {
            $this->log(__FUNCTION__, $path, get_class($e));

            throw $e;
        }
    }

    /**
     * @inheritdoc
     */
    public function rename($path, $newpath)
    {
        try {
            $r = parent::rename($path, $newpath);

            $this->log(__FUNCTION__, $path.' -> '.$newpath, $r);

            return $r;
        } catch (Exception $e) {
            $this->log(__FUNCTION__, $path.' -> '.$newpath, get_class($e));

            throw $e;
        }
    }

    /**
     * @inheritdoc
     */
    public function copy($path, $newpath)
    {
        try {
            $r = parent::copy($path, $newpath);

            $this->log(__FUNCTION__, $path.' -> '.$newpath, $r);

            return $r;
        } catch (Exception $e) {
            $this->log(__FUNCTION__, $path.' -> '.$newpath, get_class($e));

            throw $e;
        }
    }

    /**
     * @inheritdoc
     */
    public function delete($path)
    {
        try {
            $r = parent::delete($path);

            $this->log(__FUNCTION__, $path, $r);

            return $r;
        } catch (Exception $e) {
            $this->log(__FUNCTION__, $path, get_class($e));

            throw $e;
        }
    }

    /**
     * @inheritdoc
     */
    public function deleteDir($dirname)
    {
        try {
            $r = parent::deleteDir($dirname);

            $this->log(__FUNCTION__, $dirname, $r);

            return $r;
        } catch (Exception $e) {
            $this->log(__FUNCTION__, $dirname, get_class($e));

            throw $e;
        }
    }

    /**
     * @inheritdoc
     */
    public function createDir($dirname, array $config = [])
    {
        $r = parent::createDir($dirname, $config);

        $this->log(__FUNCTION__, $dirname, $r);

        return $r;
    }

    /**
     * @inheritdoc
     */
    public function listContents($directory = '', $recursive = false)
    {
        try {
            $r = parent::listContents($directory, $recursive);

            $this->log(__FUNCTION__, $directory.' recursive='.var_export($recursive, true));

            return $r;
        } catch (Exception $e) {
            $this->log(__FUNCTION__, $directory.' recursive='.var_export($recursive, true), get_class($e));

            throw $e;
        }
    }

    /**
     * @inheritdoc
     */
    public function getMimetype($path)
    {
        try {
            $r = parent::getMimetype($path);

            $this->log(__FUNCTION__, $path, ($r !== false));

            return $r;
        } catch (Exception $e) {
            $this->log(__FUNCTION__, $path, get_class($e));

            throw $e;
        }
    }

    /**
     * @inheritdoc
     */
    public function getTimestamp($path)
    {
        try {
            $r = parent::getTimestamp($path);

            $this->log(__FUNCTION__, $path, ($r !== false));

            return $r;
        } catch (Exception $e) {
            $this->log(__FUNCTION__, $path, get_class($e));

            throw $e;
        }
    }

    /**
     * @inheritdoc
     */
    public function getVisibility($path)
    {
        try {
            $r = parent::getVisibility($path);

            $this->log(__FUNCTION__, $path, ($r !== false));

            return $r;
        } catch (Exception $e) {
            $this->log(__FUNCTION__, $path, get_class($e));

            throw $e;
        }
    }

    /**
     * @inheritdoc
     */
    public function getSize($path)
    {
        try {
            $r = parent::getSize($path);

            $this->log(__FUNCTION__, $path, ($r !== false));

            return $r;
        } catch (Exception $e) {
            $this->log(__FUNCTION__, $path, get_class($e));

            throw $e;
        }
    }

    /**
     * @inheritdoc
     */
    public function setVisibility($path, $visibility)
    {
        $perm = (method_exists($this->getAdapter(), 'getPermPublic') ? '='.decoct($this->getAdapter()->getPermPublic()) : '');

        try {
            $r = parent::setVisibility($path, $visibility);

            $this->log(__FUNCTION__, $path.' -> '.$visibility.$perm, $r);

            return $r;
        } catch (Exception $e) {
            $this->log(__FUNCTION__, $path.' -> '.$visibility.$perm, get_class($e));

            throw $e;
        }
    }

    /**
     * @inheritdoc
     */
    public function getMetadata($path)
    {
        try {
            $r = parent::getMetadata($path);

            $this->log(__FUNCTION__, $path, ($r !== false));

            return $r;
        } catch (Exception $e) {
            $this->log(__FUNCTION__, $path, get_class($e));

            throw $e;
        }
    }

    /**
     * @inheritdoc
     */
    public function get($path, Handler $handler = null)
    {
        try {
            $r = parent::get($path);

            $this->log(__FUNCTION__, $path);

            return $r;
        } catch (Exception $e) {
            $this->log(__FUNCTION__, $path, get_class($e));

            throw $e;
        }
    }
}
