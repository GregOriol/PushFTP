<?php

namespace Puscha\Model;

class Profile
{
    /**
     * @var string
     */
    protected $name;

    /**
     * @var Target
     * @required
     */
    protected $target;

    /**
     * @var string[]
     */
    protected $excludes = array();

    /**
     * @var string[]
     */
    protected $permissions = array();

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * @return Target
     */
    public function getTarget()
    {
        return $this->target;
    }

    /**
     * @param Target $target
     */
    public function setTarget($target)
    {
        $this->target = $target;
    }

    /**
     * @return string[]
     */
    public function getExcludes()
    {
        return $this->excludes;
    }

    /**
     * @param string[] $excludes
     */
    public function setExcludes($excludes)
    {
        $this->excludes = $excludes;
    }

    /**
     * @return string[]
     */
    public function getPermissions()
    {
        return $this->permissions;
    }

    /**
     * @param string[] $permissions
     */
    public function setPermissions($permissions)
    {
        $this->permissions = $permissions;
    }
}
