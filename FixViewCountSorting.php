<?php

class NetDoktor_FixViewCountSorting_FixViewCountSorting
{
    public static function listener($class, array &$extend)
    {
        // Extend the core class XenForo_ControllerPublic_Forum
        if ($class == 'XenForo_ControllerPublic_Forum') {
            $extend[] = 'NetDoktor_FixViewCountSorting_XenForo_ControllerPublic_Forum';
        }
    }
}