<?php

namespace Puscha\Target;

use League\Flysystem\AdapterInterface;
use League\Flysystem\FilesystemInterface;

interface TargetInterface extends FilesystemInterface
{
    /**
     * Get the Adapter.
     * (adding this to the filesystem interface, it will be needed by the target handler)
     *
     * @return AdapterInterface adapter
     */
    public function getAdapter();
}
