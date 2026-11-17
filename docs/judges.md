# Décroche: a guide for judges

Décroche trains sales reps on calls they will really face. A seller calls an AI client out loud. The client has a
hidden brief and their own files, so the seller has to find things out by asking the right questions. When the seller
hangs up, a coach agent grades the call on the company's scorecard. From Claude, over MCP, the seller can also rehearse
tomorrow's real meeting, starting from its e-mail thread.

This page is the short path: what to try, then where to look in the code. The [README](../README.md) has the rest.

## What makes a call feel real

- **The client knows things you don't.** Each call has a hidden brief: the real budget, the real pain, who decides,
  the objections, what would get a yes. The client only gives it away to the right questions. The brief never
  leaves the server while the call is on, neither in the page nor over MCP, so no model on the seller's side can
  whisper the answers.
- **The client has a life of their own.** Every call comes with the client's workspace, written for their business:
  a calendar, plus two to four sources such as tickets, orders, contracts or e-mails. The persona is a Symfony AI agent
  with tools. Ask about an incident and they look it up and quote their records; propose a meeting and they check
  their calendar, then book it.
- **The calls come from the company itself.** On first start, the onboarding reads the company's website and writes
  its offer sheet and 14 to 18 situations specific to its business: a price request, an unhappy client, a cold call…

## Try it in five minutes

You need Docker and a model key: a [Hugging Face token](https://huggingface.co/settings/tokens) with *Make calls to
Inference Providers* ticked, or the key of any OpenAI-compatible API (see the README's *Configuration*).

```bash
git clone https://github.com/mbetixio/decroche.git && cd decroche
cp .env.docker.dist .env.docker     # paste your key after AI_API_KEY=, and set DECROCHE_DEMO=1
docker compose up -d --build        # about three minutes the first time
docker compose logs decroche | grep -A1 Manager    # the demo manager's e-mail and password
```

Open <http://localhost:7077> in Chrome, Edge or Safari, sign in, then try three things:

1. **Read a debrief that is already there.** The demo team comes with graded calls. Open one and look at the score
   on the company's criteria, the hidden brief revealed with what the seller missed, and the question that would have
   found it. The seller's lines are quoted word for word. You can replay the call from any of them.
2. **Take a call.** Press **New call** and allow the microphone. Ask about their problem, their budget, who decides.
   While you talk, watch the client say *"let me check…"* and look things up in their own files. Hang up to get the
   coach's debrief, about 30 seconds later.
3. **Rehearse from Claude.** In *My account → Train from Claude*, create your MCP address. Add it to Claude Code
   (`claude mcp add --transport http decroche "<address>"`), then paste an e-mail from a prospect and ask Claude to
   prepare the meeting. Claude shows a form first: your goal for the meeting, how tough the client should be, how
   long the call lasts. Then it writes the rehearsal and gives you the link to the call. Ask for *"the debrief of my
   last call"* and it opens as an MCP App, inside the conversation. The README's *Connect it* part covers Claude
   Desktop and claude.ai.

No key at hand? The test suite runs the same flows offline, against a scripted model: the onboarding, a streamed
call, the debrief, the MCP server and its forms.

```bash
composer install && composer coverage    # 463 tests, 100 % of the lines
```

## What Symfony AI and MCP let us do

- **Three agents, one per job, each with its own tools.** The persona has its files, its calendar and its memory of
  earlier calls. The coach checks the seller's earlier debriefs, finds the exact lines, sees what the client did, and
  checks promises against the offer. The writer reads the seller's latest calls so that each new call is different.
  They are three entries in `ai.yaml` and a few `#[AsTool]` services.
- **A game with hidden information, enforced by the server.** The AI on the seller's side (Claude over MCP) plays
  against an AI it cannot read. The tools return the briefing, never the brief, until the debrief. The persona marks
  what it gives away (`[devoiler:budget]`), so the debrief knows which part of the brief the seller uncovered.
- **The person decides, not their AI.** Before an MCP tool acts, Claude shows a form, already filled in from the
  conversation (MCP elicitation). The seller confirms the goal of the rehearsal; the manager confirms an invitation or
  a mission. Over the 2026-07-28 revision the form travels in the result and the call comes back with the answers, so
  nothing is written before the person has accepted.
- **The same app in two places.** The phone screen in the browser and Claude over MCP share the same agents, the same
  calls and the same debrief: rendered as a page on the site, and as an MCP App in Claude.
- **Open models, open voices.** Hugging Face Inference Providers by default, or any OpenAI-compatible API. Kyutai's
  Pocket TTS runs offline next to the app, with 14 French and 19 English voices, voiced sentence by sentence while the
  model is still writing.

## Where to look

| What | Code | Proved by |
|---|---|---|
| Three agents with tools | `config/packages/ai.yaml`, `src/Agent/` | `tests/Functional/AgentToolsTest.php` |
| The client looks things up and books a meeting during the call | `src/Agent/Persona/` | `testThePersonaUsesTheirCalendarAndFilesDuringTheCall` |
| The client remembers the previous call | `src/Agent/Persona/PreviousCalls.php` | `testAnEarlierCallIsRememberedAndItsDebriefRead` |
| Investigate first, then a strict structured answer | `src/Ai/LanguageModel.php` (`work()`), `src/Ai/Output/` | `tests/Unit/Ai/` |
| The reply streamed, cleaned of its markers, voiced sentence by sentence | `src/Call/Persona/ReplyFilter.php`, `src/Call/TurnStreamer.php` | `testACallFromPickUpToDebrief` |
| The hidden brief never leaves the server | `src/Mcp/CallTools.php` | `McpTest::testANewCallComesWithASignedLinkThatSignsTheSellerIn` |
| MCP tools, prompts, resources, and the debrief as an MCP App | `src/Mcp/`, `src/Mcp/DebriefApp.php` | `tests/Functional/McpTest.php` |
| Forms before acting (elicitation) | `src/Mcp/Ask.php` | `tests/Functional/McpFormsTest.php` |
| A personal MCP key per member, redacted from the logs | `src/Security/McpKeyAuthenticator.php` | `McpTest::testAnUnknownKeyIsRefused` |
| The whole suite without the network | `tests/Fake/ScriptedModel.php` | `composer coverage` |

The checks that CI runs on every push: `composer lint`, `composer cs`, `composer stan` (PHPStan at level max) and
`composer coverage` (fails below 100 % of the lines).

## What we ran into with Symfony AI

We built on the 1.0.0-BETA1 pre-release. Four things got in the way. Each time, we worked around the problem in the
app, then went looking upstream: all four had already been raised by others, so we added our case where it was
still open.

| What happened | What we did | Upstream |
|---|---|---|
| An agent cannot use its tools and a structured answer at once: on Hugging Face, gpt-oss refuses the request (`"tools" is incompatible with "response_format"`) and DeepSeek answers the schema without ever calling a tool. | The coach and the writer work in two steps: the agent investigates with its tools and writes notes, then a second call, without tools, writes the structured answer (`LanguageModel::work()`). One extra request, reliable on every provider we tried. | [symfony/ai#2852](https://github.com/symfony/ai/issues/2852), raised for Ollama: [we added the Hugging Face case](https://github.com/symfony/ai/issues/2852#issuecomment-6916090561) and our solution. |
| `getModel()` works in prod but fails in dev and in the tests: there, the bundle wraps each agent in a `TraceableAgent` that does not forward it. | The model names are read from the environment, next to `ai.yaml` (`LanguageModel`). | [symfony/ai#2164](https://github.com/symfony/ai/issues/2164): [we added our case](https://github.com/symfony/ai/issues/2164#issuecomment-6916091088). |
| A tool without parameters is refused by Hugging Face: no `parameters` block is sent for it. | Each tool declares at least one parameter (`RecentCalls::__invoke(int $limit = 12)`). | [symfony/ai#2846](https://github.com/symfony/ai/issues/2846), fixed by [#2848](https://github.com/symfony/ai/pull/2848) after the BETA1. |
| A Hugging Face error only said "Bad Request": the generic platform did not read the provider's `message`. | We relayed the raw answer while debugging to see the real error. | [symfony/ai#2847](https://github.com/symfony/ai/issues/2847), fixed by [#2849](https://github.com/symfony/ai/pull/2849). |

The PHP MCP SDK needed no workaround: tools, prompts, resources, the MCP App, HTTP sessions and the forms
(elicitation) all worked as documented.
