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

Supported today: **OpenAI** (Responses API + Images API), **Anthropic**
(Messages API) and **Mistral** (Conversations API). OpenAI and Anthropic
cover chat, tool calling, web search, structured output and reasoning effort;
OpenAI additionally covers image generation. Mistral covers chat, function
calling, structured output and reasoning effort; its built-in tools (web
search, code interpreter, image generation) exist on the Conversations API
but aren't wired up in this adapter yet — see the tool support table below.
Remote **MCP** servers work on OpenAI and Anthropic.

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

There is no provider to select — `LLMClient` only ever hands out `chat()` /
`image()`. The provider is derived from the model id you pass to `model(...)`
(`AIProvider::fromModel()`), so switching provider is just switching the model
id and the key:

```php
use Conduit\Client\LLMClient;
use Conduit\Entity\Content;

$client = new LLMClient($apiKey);

$response = $client->chat()
    ->model('claude-opus-5')            // 'claude-...' → Anthropic
    ->instruction('You answer in one sentence.')
    ->content([Content::text('Why is the sky blue?')])
    ->call();

echo $response;            // first text block — ChatResponse has __toString()
```

```php
$response = (new LLMClient($openAiKey))
    ->chat()
    ->model('gpt-5')                    // 'gpt-...' → OpenAI
    ->instruction('You answer in one sentence.')
    ->content([Content::text('Why is the sky blue?')])
    ->call();
```

For a model id the built-in patterns can't recognize (a custom deployment
name, a fine-tune alias, ...), force the provider explicitly with
`provider(...)`:

```php
use Conduit\Enum\AIProvider;

$response = $client->chat()
    ->model('my-custom-deployment')
    ->provider(AIProvider::OpenAI)
    ->content([Content::text('hi')])
    ->call();
```

---

## Building a request

`chat()` returns a `Conduit\Endpoint\Chat` you configure with fluent setters.
Only `call()` performs the HTTP request.

| Setter | Purpose | Default |
| --- | --- | --- |
| `model(string)` | Model id. Required — decides the provider via `AIProvider::fromModel()`. | — |
| `provider(AIProvider)` | Forces the provider, bypassing `AIProvider::fromModel()`. Only needed for model ids the built-in patterns can't recognize. | derived from model id |
| `instruction(string)` | System prompt. | none |
| `content(array)` | The user turn — blocks from `Content::…`. | `[]` |
| `context(array)` | Prior turns — messages from `Context::…`. | `[]` |
| `user(string)` | Role of the current turn (`user` / `assistant`). OpenAI and Mistral only. | `user` |
| `maxTokens(int)` | Answer length cap. | `1024` |
| `effort(ThinkingEffort, bool $summary = false)` | Reasoning depth; optionally ask for a summary. | `Low` |
| `jsonSchema(array\|JsonSchema)` | Force a JSON Schema on the answer — a raw array or a `JsonSchema::object()...` builder. | none |
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

### Provider support

**Tools** (`Conduit\Entity\Tool`):

| `Tool::` factory     | OpenAI | Anthropic | Mistral | Notes |
| -------------------- | :----: | :-------: | :-----: | ----- |
| `function()`         |   ✅   |    ✅     |   ✅    | custom function calling |
| `webSearch()`        |   ✅   |    ✅     |   ❌    | `user_location` (from `location()`) is applied by OpenAI only; Mistral has a `web_search` connector on the Conversations API, but this adapter doesn't map the neutral shape onto it yet |
| `webFetch()`         |   ❌   |    ✅     |   ❌    | OpenAI folds page fetching into `webSearch()` |
| `imageGeneration()`  |   ✅   |    ❌     |   ❌    | Anthropic has no image capability at all; Mistral has an `image_generation` connector on the Conversations API, not yet wired here |
| `mcp()`              |   ✅   |    ✅     |   ❌    | Anthropic ignores `sRequireApproval`, `aHeaders`, `sDescription`, `sConnectorId`; Mistral's connector model is registration-based (`connector_id`), not a raw URL like `Tool::mcp()` expects |

**Request features:**

| Feature | OpenAI | Anthropic | Mistral | Notes |
| --- | :---: | :---: | :---: | --- |
| `chat()` | ✅ | ✅ | ✅ | |
| `image()` endpoint | ✅ | ❌ | ❌ | Anthropic and Mistral return an error response (`UnsupportedCapabilityException`) |
| `instruction()`, `context()`, `content()` | ✅ | ✅ | ✅ | |
| image / file (PDF) input via `Content::` | ✅ | ✅ | ✅ | Mistral takes files as `document_url` |
| `effort()` + thinking summary | ✅ | ✅ | partial | Mistral maps `effort()` to `reasoning_effort`; the thinking-summary flag has no Mistral equivalent |
| function-call round-trip (`Context::tool()`) | ✅ | ✅ | ✅ | |
| retry + backoff on `429` / `5xx` | ✅ | ✅ | ✅ | shared in `AbstractLLMAdapter` |
| `jsonSchema()` structured output | ✅ | ✅ | ✅ | |
| `user()` role override | ✅ | ❌ | ✅ | Anthropic always sends the turn as `user` |
| `Context::mcpApproval()` | ✅ | ❌ | ❌ | only OpenAI's MCP connector has an approval step |

A whole tool the active provider can't translate at all (an unsupported
`_type`, a malformed definition) is dropped from the request — but not
silently: it's recorded as a warning, see `getWarnings()` below. A supported
tool with an unsupported optional field (e.g. `user_location` on Mistral) still
runs, just without that field — no warning for those. The `image()` endpoint is
the one exception to all of this: on Anthropic and Mistral it returns an error
response outright, nothing is dropped-and-warned.

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

`jsonSchema()` takes a raw JSON Schema array, or a `JsonSchema` builder for
when the schema itself is built in code instead of typed out by hand:

```php
use Conduit\Entity\JsonSchema;
use Conduit\Enum\JsonSchemaType;

$response = $client->chat()
    ->model('gpt-5')
    ->content([Content::text('Extract name and age: "Tom is 40."')])
    ->jsonSchema(
        JsonSchema::object()
            ->property('name', JsonSchemaType::String)
            ->property('age', JsonSchemaType::Integer)
    )
    ->call();

$data = $response->getJson();   // ['name' => 'Tom', 'age' => 40]
```

`property()` takes a `JsonSchemaType` for a plain scalar field — no need for a
`JsonSchema::string()`-style sub-builder unless the property itself nests
(object, array) or needs its own enum/description, in which case pass a full
`JsonSchema` instance instead. `additionalProperties` defaults to `false` and
`property()` marks a property required by default — the strict-mode shape
OpenAI and Mistral enforce for structured outputs. Nest schemas for
objects/arrays (`JsonSchema::array(JsonSchema::string())`), or pass a plain
array to `jsonSchema()` directly if you already have one.

A property that may genuinely not be derivable from the input should stay
`required` but get `bNullable: true` — otherwise the model can't say "not
present" and tends to guess a value that merely fits the type instead:

```php
->property('country_code', JsonSchemaType::String, bNullable: true)
// -> {"type": ["string", "null"]}, still listed in `required`
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
$response->getJson();          // decoded structured answer, from jsonSchema()
$response->getOutputs();       // every block, in order
$response->getFunctionCalls(); // ChatOutput[] where isFunctionCall()
$response->getMcpCalls();      // ChatOutput[] where isMcpCall()
$response->getMcpResults();    // ChatOutput[] where isMcpResult()
$response->getWebSearches();
$response->getImages();

$response->getModel();
$response->getInputTokens();
$response->getOutputTokens();
$response->getWarnings();      // string[] — see "Warnings" below
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

`Conduit\Enum\OutputType` is the full list: `Text`, `Json`, `FunctionCall`, `WebSearch`,
`WebFetch`, `Image`, `Refusal`, `Thinking`, `McpCall`, `McpResult`,
`McpListTools`, `McpApprovalRequest`.

---

## Warnings

A response can be a normal, usable answer and still carry warnings — they
mean part of the request couldn't be translated for the resolved provider (a
tool type it doesn't support, a malformed tool definition) and was skipped
instead of silently vanishing:

```php
$response = $client->chat()
    ->model('mistral-small-latest')
    ->content([Content::text('What is the weather in Berlin?')])
    ->tools([Tool::webSearch()])   // Mistral adapter doesn't translate this (yet)
    ->call();

if ($response->hasWarnings()) {
    foreach ($response->getWarnings() as $sWarning) {
        error_log($sWarning);
        // "Tool type 'web_search' is not supported by the Mistral adapter yet and was skipped."
    }
}
```

| Method | Returns |
| --- | --- |
| `hasWarnings()` | `bool` |
| `getWarnings()` | `string[]` |

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
| `UnsupportedCapabilityException` | `LogicException` | provider can't serve the endpoint (Anthropic/Mistral + images, Google) |
| `ConfigurationException` | `InvalidArgumentException` | empty API key, or a model id `AIProvider::fromModel()` can't resolve (use `provider(...)` for those) |

`TransportException` and `ApiException` on retryable statuses (`429`, `500`,
`502`, `503`, `529` by default) are retried with exponential backoff before
they surface — see [Configuration](#configuration) to change the statuses or
attempt count. `ConfigurationException` is thrown eagerly — it's a
programming error, fix the call site.

---

## Configuration

Cross-cutting behaviour — timeouts, retry policy, whether warnings are
collected at all — lives in `Conduit\Configuration\ConduitConfig`, built once
per `LLMClient` and passed to `LLMClient`'s constructor. It is never a
singleton: two clients (different API keys, different accounts) can carry
different configs.

```php
use Conduit\Configuration\ConduitConfig;
use Conduit\Client\LLMClient;

$config = ConduitConfig::default()
    ->maxAttempts(5)
    ->requestTimeout(60)
    ->warningsEnabled(false);

$client = new LLMClient($apiKey, $config);
```

Every parameter is optional and defaults to the built-in value, so named
arguments work just as well for a one-off config:

```php
$config = new ConduitConfig(iMaxAttempts: 5, bWarningsEnabled: false);
```

Omit the second constructor argument to get `ConduitConfig::default()`
outright — every existing `new LLMClient($apiKey)` call keeps working
unchanged.

| Setter | Getter | Purpose | Default |
| --- | --- | --- | --- |
| `requestTimeout(int)` | `getRequestTimeout(): int` | Seconds to wait for the provider to answer. | `300` |
| `connectTimeout(int)` | `getConnectTimeout(): int` | Seconds to wait for the TCP connection to establish. | `15` |
| `maxAttempts(int)` | `getMaxAttempts(): int` | Attempts (incl. the first) before a retryable failure surfaces. | `3` |
| `retryHttpCodes(array)` | `getRetryHttpCodes(): int[]` | HTTP codes treated as transient and retried. | `[429, 500, 502, 503, 529]` |
| `warningsEnabled(bool)` | `isWarningsEnabled(): bool` | Whether adapters collect warnings at all (see [Warnings](#warnings)). | `true` |

---

## Architecture

```
LLMClient ── chat()/image() ──► Chat / Image endpoint
                                     │  model(...) [+ optional provider(...)]
                                     ▼
                                   call() ──► AdapterFactory::make()
                                                    │
                                    AIProvider::fromModel() unless
                                    provider(...) forced one
                                                    │
                                                    ▼
                                               LLMAdapter
                                                    ├─ OpenAIAdapter
                                                    ├─ AnthropicAdapter
                                                    └─ MistralAdapter
                                                         │
                                     neutral response ◄──┘  (normalized array)
                                               │
                                               ▼
                                   ChatResponse / ImageResponse
                                   (ChatOutput / ImageOutput blocks)
```

`LLMClient` is nothing but an access point — it holds the API key and hands
out endpoints, it never picks a provider itself. The endpoints only ever build
the neutral payload; `AdapterFactory` is the one place that turns a model id
into a provider and then into a concrete adapter. Each adapter owns the full
round trip for its provider: request-body assembly, provider-specific headers,
the HTTP call (shared retry/backoff in `AbstractLLMAdapter`), and normalization
of the answer into the array the response objects hydrate from.

### Adding a provider

1. Add a case to `Conduit\Enum\AIProvider` and a matching pattern in its
   `PATTERNS` table so `fromModel()` can recognize that provider's model ids.
2. Write `Conduit\Adapter\YourAdapter extends AbstractLLMAdapter` implementing
   `chat()` and `image()` (throw `UnsupportedCapabilityException` from whichever
   it can't serve).
3. Add the `match` arm in `Conduit\Factory\AdapterFactory`.

No client, endpoint, entity or response code changes.

### Project layout

```
src/
  Adapter/     AbstractLLMAdapter, OpenAIAdapter, AnthropicAdapter, MistralAdapter
  Client/      LLMClient
  Configuration/ ConduitConfig
  Contract/    LLMAdapter, Castable, Hydratable, Output, ErrorCollectionInterface, WarningCollectionInterface
  Endpoint/    AbstractLLMEndpoint, Chat, Image
  Entity/      Content, Context, Tool          (stateless request-building factories)
               JsonSchema                      (fluent structured-output schema builder)
  Enum/        AIProvider, ThinkingEffort, OutputType, ToolType, JsonSchemaType
  Exception/   ConduitException + concrete types
  Factory/     AdapterFactory
  Response/    AbstractAIResponse, ChatResponse, ChatOutput, ImageResponse, ImageOutput
  Support/     HasErrors, HasWarnings, HasTools, HasImageData   (shared traits)
```

---

## License

MIT.
