<?php

namespace traits;

trait HasTools
{
    private array $aTools = [];

    public function tools(array $aTools): static
    {
        $this->aTools = $aTools;
        return $this;
    }

    public function addTool(array $aTool): static
    {
        $this->aTools[] = $aTool;
        return $this;
    }

    public function getTools(): array
    {
        return $this->aTools;
    }

    public function hasTools(): bool
    {
        return !empty($this->aTools);
    }
}
