<?php

namespace Room11\Jeeves\Chat\Plugin\Traits;

use Room11\Jeeves\Chat\Room\Room as ChatRoom;

trait NoEnable
{
    public function enableForRoom(ChatRoom $room, bool $persist) {}
}
