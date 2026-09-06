<?php

namespace traits;

/**
 * Tools for endpoints that support them.
 *
 * Keeps the tool definitions from the Tool factory until call() passes them
 * to the adapter. A trait because not every endpoint knows tools — the
 * Image endpoint, for example, does not.
 */
trait HasTools
{
    private array $aTools = [];

    /**
     * Sets the tools available to the model, replacing any set before.
     *
     * @param array $aTools Definitions from Tool::webSearch(), ::webFetch(), ::function() etc.
     * @return static
     */
    public function tools(array $aTools): static
    {
        $this->aTools = $aTools;
        return $this;
    }

    /**
     * Appends a single tool definition.
     *
     * @param array $aTool One definition from the Tool factory.
     * @return static
     */
    public function addTool(array $aTool): static
    {
        $this->aTools[] = $aTool;
        return $this;
    }

    /**
     * @return array The set tool definitions, empty when none were set.
     */
    public function getTools(): array
    {
        return $this->aTools;
    }

    /**
     * @return bool True when at least one tool was set.
     */
    public function hasTools(): bool
    {
        return !empty($this->aTools);
    }
}
