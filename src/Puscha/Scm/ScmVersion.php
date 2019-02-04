<?php

namespace Puscha\Scm;

class ScmVersion
{
    /** @var string */
    protected $tree;
    /** @var string */
    protected $revision;
    /** @var string|null */
    protected $repository;

    /**
     * ScmVersion constructor.
     *
     * @param string      $tree
     * @param string      $revision
     * @param string|null $repository
     */
    public function __construct($tree = '', $revision = '', $repository = null)
    {
        $this->tree = $tree;
        $this->revision = $revision;
        $this->repository = $repository;
    }

    /**
     * @param $string
     *
     * @return ScmVersion|null
     */
    public static function fromString($string)
    {
        if (empty($string)) {
            return null;
        }

        $components = explode('@', $string);
        if (count($components) === 3) {
            return new ScmVersion($components[0], $components[1], $components[2]);
        } elseif (count($components) === 2) {
            return new ScmVersion($components[0], $components[1]);
        }

        return null;
    }

    /**
     * @return string
     */
    public function getTree()
    {
        return $this->tree;
    }

    /**
     * @return string
     */
    public function getRevision()
    {
        return $this->revision;
    }

    /**
     * @return string|null
     */
    public function getRepository()
    {
        return $this->repository;
    }

    /**
     * This setter should probably never be used, except during a detected upgrade from v0
     *
     * @param string|null $repository
     */
    public function setRepository($repository)
    {
        $this->repository = $repository;
    }

    /**
     * @return string
     */
    public function getShortString()
    {
        return $this->tree.'@'.$this->revision;
    }

    /**
     * @return string
     */
    public function getFullString()
    {
        $string = $this->getShortString();

        if ($this->repository !== null) {
            $string .= '@'.$this->repository;
        }

        return $string;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->getFullString();
    }
}
