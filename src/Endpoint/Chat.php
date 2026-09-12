<?php

namespace Conduit\Endpoint;

use Conduit\Enum\ThinkingEffort;
use Conduit\Exception\ConduitException;
use Conduit\Factory\AdapterFactory;
use Conduit\Response\ChatResponse;
use Conduit\Support\HasTools;

/**
 * Endpoint for text requests to a language model.
 *
 * Not created directly — obtained from the LLMClient and filled with fluent
 * setters: $oClient->chat()->model(...)->content([...])->call(). Only call()
 * sends the request and returns a ChatResponse.
 *
 * Which provider is behind it is derived from the model id set via
 * model(...) — see AdapterFactory::make(). This endpoint only knows the
 * neutral payload format. Provider errors do not throw, they come back as a
 * ChatResponse with hasErrors() === true.
 */
class Chat extends AbstractLLMEndpoint
{
    use HasTools;

    private array          $aContext       = [];
    private array          $aContent       = [];
    private string         $sInstruction   = '';
    private string         $sUser          = 'user';
    private int            $iMaxTokens     = 1024;
    private ThinkingEffort $oEffort        = ThinkingEffort::Low;
    private bool           $bEffortSummary = false;
    private array          $aJsonSchema    = [];

    /**
     * Sends the assembled request to the provider.
     *
     * A provider error is deliberately not propagated but returned as a
     * ChatResponse with an error set — the caller checks hasErrors() instead
     * of catching.
     *
     * @return ChatResponse Model answer or error response.
     */
    public function call(): ChatResponse
    {
        $oAdapter = AdapterFactory::make($this->sModel, $this->sApiKey, $this->oProviderOverride);

        try {
            $aNormalized = $oAdapter->chat([
                'model'         => $this->sModel,
                'effort'        => $this->oEffort->value,
                'effortSummary' => $this->bEffortSummary,
                'instruction'   => $this->sInstruction ?: null,
                'maxTokens'     => $this->iMaxTokens,
                'user'          => $this->sUser,
                'context'       => $this->aContext,
                'content'       => $this->aContent,
                'tools'         => $this->getTools(),
                'jsonSchema'    => $this->aJsonSchema,
            ]);
            return ChatResponse::fromArray($aNormalized);
        } catch (ConduitException $oException) {
            return ChatResponse::fromError($oException);
        }
    }

    /**
     * Sets the conversation history the model should see.
     *
     * @param array $aContext List of messages with role and content (from Context::...).
     * @return self
     */
    public function context(array $aContext): self
    {
        $this->aContext = $aContext;
        return $this;
    }

    /**
     * Sets the actual user input.
     *
     * @param array $aContent Blocks from Content::text(), ::image() and ::file().
     * @return self
     */
    public function content(array $aContent): self
    {
        $this->aContent = $aContent;
        return $this;
    }

    /**
     * Sets the system instruction that gives the model its role and behaviour.
     *
     * @param string $sInstruction System instruction.
     * @return self
     */
    public function instruction(string $sInstruction): self
    {
        $this->sInstruction = $sInstruction;
        return $this;
    }

    /**
     * Role of the current message (default 'user'). Only evaluated by the
     * OpenAI adapter, Anthropic always sends as 'user'.
     *
     * @param string $sUser Role, e.g. 'user' or 'assistant'.
     * @return self
     */
    public function user(string $sUser): self
    {
        $this->sUser = $sUser;
        return $this;
    }

    /**
     * Limits the length of the answer (default 1024).
     *
     * @param int $iMaxTokens Maximum number of answer tokens.
     * @return self
     */
    public function maxTokens(int $iMaxTokens): self
    {
        $this->iMaxTokens = $iMaxTokens;
        return $this;
    }

    /**
     * Clears the conversation history so the same endpoint object can be
     * reused for a new request without prior context.
     *
     * @return self
     */
    public function clearContext(): self
    {
        $this->aContext = [];
        return $this;
    }

    /**
     * Clears the user input so the same endpoint object can be reused.
     *
     * @return self
     */
    public function clearContent(): self
    {
        $this->aContent = [];
        return $this;
    }

    /**
     * Forces a JSON schema as the response format (structured outputs).
     *
     * @param array $aSchema Full JSON schema (type, properties, required, additionalProperties).
     * @return self
     */
    public function jsonSchema(array $aSchema): self
    {
        $this->aJsonSchema = $aSchema;
        return $this;
    }

    /**
     * Sets the reasoning / thinking depth of the model.
     *
     * @param ThinkingEffort $oEffort  Level: Low, Medium, High, XHigh, Max.
     * @param bool           $bSummary Also request a summary of the thinking
     *                                 (lands as OutputType::Thinking in the response).
     * @return self
     */
    public function effort(ThinkingEffort $oEffort, bool $bSummary = false): self
    {
        $this->oEffort        = $oEffort;
        $this->bEffortSummary = $bSummary;
        return $this;
    }
}
