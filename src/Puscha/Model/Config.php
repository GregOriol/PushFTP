<?php

namespace Puscha\Model;

class Config
{
    /**
     * @var string
     */
    protected $base = '.';

    /**
     * @var Profile[]
     * @required
     */
    protected $profiles;

    /**
     * @var string[][]|null
     */
    protected $excludes;

    /**
     * @var string[][]|null
     */
    protected $permissions;

    /**
     * @return Profile[]
     */
    public function getProfiles()
    {
        return $this->profiles;
    }

    /**
     * @param Profile[]
     */
    public function setProfiles($profiles)
    {
        $this->profiles = $profiles;
    }

    /**
     * @return string[][]|null
     */
    public function getExcludes()
    {
        return $this->excludes;
    }

    /**
     * @param string[][]|null $excludes
     */
    public function setExcludes($excludes)
    {
        $this->excludes = $excludes;
    }

    /**
     * @return string[][]|null
     */
    public function getPermissions()
    {
        return $this->permissions;
    }

    /**
     * @param string[][]|null $permissions
     */
    public function setPermissions($permissions)
    {
        $this->permissions = $permissions;
    }
}
