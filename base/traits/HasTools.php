<?php

namespace traits;
trait HasTools
{
    private array $tools = [];

    public function tools(array $tools): static
    {
        $this->tools = $tools;
        return $this;
    }

    public function addTool(array $tool): static
    {
        $this->tools[] = $tool;
        return $this;
    }

    public function getTools(): array
    {
        return $this->tools;
    }

    public function hasTools(): bool
    {
        return !empty($this->tools);
    }
}