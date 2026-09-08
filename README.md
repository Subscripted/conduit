# Conduit

A provider-neutral LLM SDK for PHP. You write one request; Conduit translates it
into the wire format of whichever provider is active and normalizes the answer
back into one shape. Swapping OpenAI for Anthropic is a one-line change and no
call-site edits.

- **One request shape** — `model → instruction → content → tools → call()`, the
  same for every provider.
- **One response shape** — a list of typed output blocks (text, function call,
  web search, MCP call, image, thinking, refusal) with the same accessors
  regardless of who produced them.
- **Adapters, not `if`-chains** — each provider lives in its own class behind the
  `LLMAdapter` interface. A new provider is one adapter plus one `match` arm.
- **Errors don't explode** — a provider failure comes back as a response with
  `hasErrors() === true`, carrying the original exception object.
- **No runtime dependencies** beyond `ext-curl`.

Supported today: **OpenAI** (Responses API + Images API) and **Anthropic**
(Messages API). Both cover chat, tool calling, web search, structured output and
reasoning effort; OpenAI additionally covers image generation. Remote **MCP**
servers work on both.

---

## Requirements

- PHP 8.4+
- `ext-curl`

## Install

```bash
composer require conduit
```

PSR-4 autoloading under the `Conduit\` namespace (`src/`).

---

## Quick start

```php
use Conduit\Client\LLMClient;
use Conduit\Enum\AIProvider;
use Conduit\Entity\Content;

$client = (new LLMClient($apiKey))
    ->setAIProvider(AIProvider::Anthropic);

$response = $client->chat()
    ->model('claude-opus-5')
    ->instruction('You answer in one sentence.')
    ->content([Content::text('Why is the sky blue?')])
    ->call();

echo $response;            // first text block — ChatResponse has __toString()
```

Switching provider changes two things — the enum and the model id — and nothing
else:

```php
$response = (new LLMClient($openAiKey))
    ->setAIProvider(AIProvider::OpenAI)
    ->chat()
    ->model('gpt-5')
    ->instruction('You answer in one sentence.')
    ->content([Content::text('Why is the sky blue?')])
    ->call();
```

---

## Building a request

`chat()` returns a `Conduit\Endpoint\Chat` you configure with fluent setters.
Only `call()` performs the HTTP request.

| Setter | Purpose | Default |
| --- | --- | --- |
| `model(string)` | Model id. Required. | — |
| `instruction(string)` | System prompt. | none |
| `content(array)` | The user turn — blocks from `Content::…`. | `[]` |
| `context(array)` | Prior turns — messages from `Context::…`. | `[]` |
| `user(string)` | Role of the current turn (`user` / `assistant`). OpenAI only. | `user` |
| `maxTokens(int)` | Answer length cap. | `1024` |
| `effort(ThinkingEffort, bool $summary = false)` | Reasoning depth; optionally ask for a summary. | `Low` |
| `jsonSchema(array)` | Force a JSON Schema on the answer. | none |
| `tools(array)` / `addTool(array)` | Tools from `Tool::…`. | `[]` |
| `clearContext()` / `clearContent()` | Reset for reuse of the same object. | — |

### Conversation history

The API is stateless — send the whole history every turn.

```php
use Conduit\Entity\Context;

$history = Context::from([
    'What is the capital of France?',                 // bare string → user turn
    Context::assistant('Paris.'),
    Context::user('And of Italy?'),
]);

$response = $client->chat()
    ->model('claude-opus-5')
    ->context($history)
    ->content([Content::text('And of Spain?')])
    ->call();
```

### Multimodal input

```php
use Conduit\Entity\Content;

$client->chat()
    ->model('gpt-5')
    ->content([
        Content::text('Describe this image and this document.'),
        Content::image('https://example.com/photo.jpg'),
        Content::image('data:image/png;base64,iVBORw0KGgo...'),   // data URI works too
        Content::file('https://example.com/report.pdf'),
    ])
    ->call();
```

`Content::images($list)` and `Content::files($list)` build several blocks at once.

---

## Tools

All tool definitions come from the `Conduit\Entity\Tool` factory. The neutral
`_type` key (a `Conduit\Enum\ToolType` value) is what each adapter switches on.

### Custom functions

```php
use Conduit\Entity\Tool;
use Conduit\Entity\Context;

$response = $client->chat()
    ->model('claude-opus-5')
    ->content([Content::text('What is the weather in Berlin?')])
    ->tools([
        Tool::function('get_weather', 'Current weather for a city', [
            'type'       => 'object',
            'properties' => ['city' => ['type' => 'string']],
            'required'   => ['city'],
        ]),
    ])
    ->call();

foreach ($response->getFunctionCalls() as $call) {
    $args   = $call->getArguments();                 // ['city' => 'Berlin']
    $result = my_weather_lookup($args['city']);

    // feed the result back on the next turn
    $followUp = $client->chat()
        ->model('claude-opus-5')
        ->context([
            Context::user('What is the weather in Berlin?'),
            Context::tool($call->getCallId(), $result),
        ])
        ->content([Content::text('')])
        ->call();
}
```

### Provider-run web search / fetch

```php
$client->chat()
    ->model('claude-opus-5')
    ->content([Content::text('Summarise today’s AI news.')])
    ->tools([
        Tool::webSearch(aAllowedDomains: ['reuters.com', 'apnews.com'], iMaxUses: 3),
        Tool::webFetch(bCitations: true),
    ])
    ->call();
```

`Tool::location()` supplies an approximate `user_location` for regional results.

### Remote MCP servers

One neutral definition; each adapter emits its own format — OpenAI a single `mcp`
tool, Anthropic an `mcp_toolset` entry plus a top-level `mcp_servers` block and
the `mcp-client-2025-11-20` beta header.

```php
$response = $client->chat()
    ->model('claude-opus-5')
    ->content([Content::text('What are my open P1 issues?')])
    ->tools([
        Tool::mcp(
            sName:               'sentry',
            sUrl:                'https://mcp.sentry.dev/sse',
            sRequireApproval:    'never',
            aAllowedTools:       ['find_issues', 'get_issue_details'],
            sAuthorizationToken: $oauthToken,
        ),
    ])
    ->call();

foreach ($response->getMcpCalls() as $call) {
    echo $call->getName(), ' @ ', $call->getServerLabel(), "\n";
}
foreach ($response->getMcpResults() as $result) {
    echo $result->isMcpError() ? "error: " : "", $result->getMcpOutput(), "\n";
}
```

Fields the target provider doesn't support are dropped silently: Anthropic
ignores `aHeaders`, `sDescription`, `sConnectorId` and `sRequireApproval` (its
connector has no approval step). When a call needs confirmation
(`require_approval` other than `never`, OpenAI), it arrives as an
`McpApprovalRequest` block; answer it with `Context::mcpApproval($id, true)`.

### Image generation inside a chat (OpenAI)

```php
$response = $client->chat()
    ->model('gpt-5')
    ->content([Content::text('Draw a red bicycle.')])
    ->tools([Tool::imageGeneration(iWidth: 1024, iHeight: 1024)])
    ->call();

foreach ($response->getImages() as $image) {
    file_put_contents('bike.png', base64_decode($image->getImageData()));
}
```

---

## Structured output

```php
$response = $client->chat()
    ->model('gpt-5')
    ->content([Content::text('Extract name and age: "Tom is 40."')])
    ->jsonSchema([
        'type'                 => 'object',
        'properties'           => [
            'name' => ['type' => 'string'],
            'age'  => ['type' => 'integer'],
        ],
        'required'             => ['name', 'age'],
        'additionalProperties' => false,
    ])
    ->call();

$data = json_decode($response->getText(), true);   // ['name' => 'Tom', 'age' => 40]
```

## Reasoning effort

```php
use Conduit\Enum\ThinkingEffort;

$response = $client->chat()
    ->model('claude-opus-5')
    ->effort(ThinkingEffort::High, bSummary: true)
    ->content([Content::text('Prove that sqrt(2) is irrational.')])
    ->call();

foreach ($response->getOutputs() as $block) {
    if ($block->isThinking()) {
        echo "[reasoning] ", $block->getThinking(), "\n";
    }
}
```

Levels: `Low`, `Medium`, `High`, `XHigh`, `Max`.

## Images

```php
$response = (new LLMClient($openAiKey))
    ->setAIProvider(AIProvider::OpenAI)
    ->image()
    ->model('gpt-image-1')
    ->prompt('An isometric city at dusk')
    ->size(1024, 1024)
    ->call();

file_put_contents('city.png', base64_decode($response->getImageData()));
$w = $response->getWidth();    // 1024
$h = $response->getHeight();   // 1024
```

Pass `->images([$url, ...])` to edit existing images instead of generating fresh
ones. Anthropic has no image API — calling `image()` on it yields an error
response (see below).

---

## Reading the response

A `ChatResponse` is a sequence of `ChatOutput` blocks, not a single string.

```php
$response->getText();          // first text block
$response->getTexts();         // all text blocks
$response->getOutputs();       // every block, in order
$response->getFunctionCalls(); // ChatOutput[] where isFunctionCall()
$response->getMcpCalls();      // ChatOutput[] where isMcpCall()
$response->getMcpResults();    // ChatOutput[] where isMcpResult()
$response->getWebSearches();
$response->getImages();

$response->getModel();
$response->getInputTokens();
$response->getOutputTokens();
$response->__toArray();        // fully JSON-serialisable
```

Each block is typed — check before you read:

```php
foreach ($response->getOutputs() as $block) {
    match (true) {
        $block->isText()        => print($block->getText()),
        $block->isFunctionCall() => printf("call %s(%s)\n", $block->getName(), $block->getArgumentsRaw()),
        $block->isMcpResult()   => print($block->getMcpOutput()),
        $block->isRefusal()     => print("refused: " . $block->getRefusal()),
        default                 => null,
    };
}
```

`Conduit\Enum\OutputType` is the full list: `Text`, `FunctionCall`, `WebSearch`,
`WebFetch`, `Image`, `Refusal`, `Thinking`, `McpCall`, `McpResult`,
`McpListTools`, `McpApprovalRequest`.

---

## Error handling

Provider-side failures never throw out of `call()` — they land in the response:

```php
$response = $client->chat()->model('claude-opus-5')->content([Content::text('hi')])->call();

if ($response->hasErrors()) {
    $e = $response->getFirstError();               // the Throwable itself

    if ($e instanceof Conduit\Exception\ApiException) {
        error_log("HTTP {$e->iStatusCode}: {$e->getMessage()}");
        // $e->aResponseBody holds the decoded provider payload
    }

    return;
}
```

The collection holds exception objects, not strings:

| Method | Returns |
| --- | --- |
| `hasErrors()` | `bool` |
| `getErrors()` | `Throwable[]` |
| `getFirstError()` | `?Throwable` |
| `getErrorMessages()` | `string[]` |

### Exception types (`Conduit\Exception\`)

| Type | Base | Cause |
| --- | --- | --- |
| `ConduitException` | *interface* | marker — `catch` this for "any Conduit failure" |
| `TransportException` | `RuntimeException` | provider never reached (cURL / TLS / timeout) |
| `ApiException` | `RuntimeException` | HTTP ≥ 400; carries `int $iStatusCode`, `?array $aResponseBody` |
| `UnsupportedCapabilityException` | `LogicException` | provider can't serve the endpoint (Anthropic + images, Google) |
| `ConfigurationException` | `InvalidArgumentException` | empty API key, or endpoint used before `setAIProvider()` |

`TransportException` and `ApiException` on retryable statuses (`429`, `500`,
`502`, `503`, `529`) are retried up to 3 times with exponential backoff before
they surface. `ConfigurationException` is thrown eagerly from `LLMClient` — it's
a programming error, fix the call site.

---

## Architecture

```
LLMClient ── setAIProvider(AIProvider) ─────────────┐
    │  chat()/image()                               │
    ▼                                               ▼
Chat / Image endpoint ── call() ──► AdapterFactory ──► LLMAdapter
    (neutral payload)                                    ├─ OpenAIAdapter
                                                         └─ AnthropicAdapter
                                                              │
                                          neutral response ◄──┘  (normalized array)
                                                    │
                                                    ▼
                                        ChatResponse / ImageResponse
                                        (ChatOutput / ImageOutput blocks)
```

The endpoints only ever build the neutral payload. Each adapter owns the full
round trip for its provider: request-body assembly, provider-specific headers,
the HTTP call (shared retry/backoff in `AbstractLLMAdapter`), and normalization
of the answer into the array the response objects hydrate from.

### Adding a provider

1. Add a case to `Conduit\Enum\AIProvider`.
2. Write `Conduit\Adapter\YourAdapter extends AbstractLLMAdapter` implementing
   `chat()` and `image()` (throw `UnsupportedCapabilityException` from whichever
   it can't serve).
3. Add the `match` arm in `Conduit\Factory\AdapterFactory`.

No endpoint, entity or response code changes.

### Project layout

```
src/
  Adapter/     AbstractLLMAdapter, OpenAIAdapter, AnthropicAdapter
  Client/      LLMClient
  Contract/    LLMAdapter, Castable, Hydratable, Output, ErrorCollectionInterface
  Endpoint/    AbstractLLMEndpoint, Chat, Image
  Entity/      Content, Context, Tool          (stateless request-building factories)
  Enum/        AIProvider, ThinkingEffort, OutputType, ToolType
  Exception/   ConduitException + concrete types
  Factory/     AdapterFactory
  Response/    AbstractAIResponse, ChatResponse, ChatOutput, ImageResponse, ImageOutput
  Support/     HasErrors, HasTools, HasImageData   (shared traits)
```

---

## License

MIT.
