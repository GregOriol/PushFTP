<?php

namespace Puscha\Scm;

class ScmChange
{
    const TYPE_ADDED    = 'added';
    const TYPE_DELETED  = 'deleted';
    const TYPE_MODIFIED = 'modified';
    //const TYPE_COPIED   = 'copied';
    //const TYPE_RENAMED  = 'renamed';
    const TYPE_UNKNOWN  = 'unknown';

    /** @var string */
    protected $type;
    /** @var string */
    protected $file;

    public function __construct($type, $file)
    {
        $this->type = $type;
        $this->file = $file;
    }

    public function __toString()
    {
        return $this->type."\t".$this->file;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @return string
     */
    public function getFile()
    {
        return $this->file;
    }
}
