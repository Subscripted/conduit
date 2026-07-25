<?php

interface Hydratable
{
    public static function fromArray(array $aData): self;
}
