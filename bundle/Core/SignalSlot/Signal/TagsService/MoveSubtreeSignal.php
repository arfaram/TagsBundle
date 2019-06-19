<?php

namespace Netgen\TagsBundle\Core\SignalSlot\Signal\TagsService;

use eZ\Publish\Core\SignalSlot\Signal;

class MoveSubtreeSignal extends Signal
{
    /**
     * Source tag ID.
     *
     * @var int
     */
    public $sourceTagId;

    /**
     * Target parent tag ID.
     *
     * @var int
     */
    public $targetParentTagId;
}
