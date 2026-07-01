<?php

namespace entity;

use Castable;
use Returnable;

class ChatResponse implements Castable, Returnable
{
    private string $content = '';
    private string $model = '';
    private int $inputTokens = 0;
    private int $outputTokens = 0;
    private array $errors = [];


    public function getContent(): string
    {
        return $this->content;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function getInputTokens(): int
    {
        return $this->inputTokens;
    }

    public function getOutputTokens(): int
    {
        return $this->outputTokens;
    }

    public static function fromArray(array $raw): self
    {
        $instance = new self();
        $instance->content = $raw['content'] ?? '';
        $instance->model = $raw['model'] ?? '';
        $instance->inputTokens = $raw['input_tokens'] ?? 0;
        $instance->outputTokens = $raw['output_tokens'] ?? 0;
        $instance->errors = $raw['errors'] ?? [];
        return $instance;
    }

    public static function error(string $message): self
    {
        $instance = new self();
        $instance->errors[] = $message;
        return $instance;
    }

    public function __toArray(): array
    {
        return [
            'content' => $this->content,
            'model' => $this->model,
            'input_tokens' => $this->inputTokens,
            'output_tokens' => $this->outputTokens,
            'errors' => $this->errors,
        ];
    }

    public function __toString(): string
    {
        return $this->content;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}