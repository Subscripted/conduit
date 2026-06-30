<?php

interface Castable
{
    public function __toArray(): array;
    public function __toString(): string;


}