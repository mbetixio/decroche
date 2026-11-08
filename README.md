# Sparring

**A flight simulator for sales calls.** Your sellers pick up the phone and talk, out loud, to an AI client who plays
one of *your* real situations: a price request from your website, an unhappy client, a renewal with a discount request…
The client has a hidden brief (their real budget, their real pain, who decides, what would get a yes) that the seller
must uncover. When they hang up, a coach grades the call against your own scorecard, reveals the hidden brief, quotes
the best and worst lines, and lets them replay any moment.

Sparring is built with **Symfony 8.1**, **Symfony AI** and the **MCP SDK**. Its MCP server lets a seller train from
Claude (or any MCP client): *"Give me a call to practise"* returns a signed link that opens the phone screen, already
signed in; *"How is my team doing?"* gives a manager the team's figures.

> Built for the Symfony AI & MCP Hackathon. One instance per company: on first boot, a step-by-step onboarding sets it
> up from the company's website, in English or French.

---

## What it does

| | |
|---|---|
| 🎲 **Situations drawn from your offer** | The onboarding reads your website and writes your offer sheet and 14–18 call situations specific to your business. Each "New call" draws one at random and writes a unique scenario around it (a persona, a document, three objectives, a hidden brief), at the seller's level. |
| 📞 **A real call, out loud** | A phone screen, not a chatbot. Speech recognition in the browser, Microsoft Edge neural voices generated server-side, each sentence voiced while the next one is being written (streaming), barge-in, a ringtone, a timer. |
| 🎯 **A debrief that teaches** | A score out of 100 on your scorecard, the call's objectives judged, the hidden brief revealed with what was missed and the question that would have found it, the best and worst lines quoted word for word, three tips, the talk-share and filler words. Replay from any of your lines. |
| 🧑‍💼 **For managers** | The team board, each seller's progress by criterion and by situation, missions ("3 price-request calls scored 70+ by Friday"), invitations, your own library of scenarios. |
| 🤖 **From Claude, through MCP** | Draw or prepare a call and get a call link, play a call in the chat, read your progress; managers read the team, give missions, invite people. The debrief shows as an MCP App. |
| 🌍 **English and French** | The working language chosen at setup drives the whole interface, the personas, their voices and the debriefs. |

## Run it in five minutes

You need an API key for an **OpenAI-compatible** chat model. The defaults use DeepSeek through
[OpenCode Go](https://opencode.ai); OpenAI, Mistral, OpenRouter or a local Ollama work too (see
[Configuration](#configuration)).

### With Docker

```bash
git clone https://github.com/<you>/sparring.git && cd sparring
AI_API_KEY=your-key docker compose up -d --build
```

Open <http://localhost:7077>: the onboarding starts. Pick a language, create your manager account, then either give
your company's website (the analysis takes a minute or two) or click **Load the example** to play right away with a
software services company and its 24 situations.

To skip the onboarding entirely, start with `SPARRING_DEMO=1`: the example company and a manager are created, and the
manager's credentials are printed by `docker compose logs`.

### Without Docker

Requires PHP 8.5 with `pdo_sqlite` and `intl`, Composer, and Python 3 for the voices.

```bash
git clone https://github.com/<you>/sparring.git && cd sparring
composer install
python3 -m venv var/edge-tts && var/edge-tts/bin/pip install edge-tts==7.3.1   # optional: neural voices
echo "AI_API_KEY=your-key" > .env.local
php bin/console app:install --demo        # prints the demo manager's e-mail and password
symfony serve                             # or: php -S 127.0.0.1:8000 -t public
```

Without `--demo`, `app:install` only creates the database and the first visit opens the onboarding.

**Use Chrome, Edge or Safari** (speech recognition) and allow the microphone. With no microphone, the keypad on the
phone screen lets you type your lines.

## Train from Claude (MCP)

1. In Sparring, open **My account → Train from Claude → Create my MCP address**.
2. In Claude, add a custom connector with that address (`https://your-instance/mcp?key=…`).
3. Ask: *"Give me a Sparring call to practise"*, *"Rehearse my meeting tomorrow with the CIO of Acme, here is what I
   know…"*, *"How am I doing?"*, or as a manager *"Who on my team struggles with objections? Give them a mission."*

| Tool | What it does |
|---|---|
| `situations` | The company's catalogue of call situations. |
| `new_call` | Draws a situation and writes the call; returns the briefing and a **signed call link** (or, with `mode: chat`, starts the call in the conversation). |
| `prepare_call` | Writes a rehearsal of a real meeting from a prospect sheet. |
| `library`, `call_link`, `start_call` | The managers' scenarios, a call link for one, or playing it in the chat. |
| `reply`, `hang_up` | Relay the seller's words to the persona word for word; hang up and have the coach debrief. |
| `debrief` | **MCP App**: the graded debrief, rendered in the conversation. |
| `my_progress` | Level, scores, criteria, results by situation, missions, last calls. |
| `team`, `member_progress`, `give_mission`, `invite` | For managers. |

The persona's hidden brief never leaves the server before the debrief: the client's model cannot cheat for the seller.

## How Symfony AI and the MCP SDK are used

- **One model, many roles.** `App\Ai\LanguageModel` wraps a Symfony AI *generic* platform (`config/packages/ai.yaml`),
  so any OpenAI-compatible API works. The same model plays the persona (streamed), writes scenarios and offer sheets
  (JSON), and coaches (JSON, low temperature).
- **Streaming end to end.** The persona's reply is a stream of `TextDelta`s (`Persona::reply()`), filtered of its
  control markers (`[devoiler:budget]`, `[fin]`) on the fly, cut into sentences (`SentenceSplitter`), each voiced in
  parallel by edge-tts (`TurnStreamer`), and sent to the browser as NDJSON as soon as its voice is ready.
- **MCP tools as plain services.** `src/Mcp/*Tools.php` are services whose methods carry `#[McpTool]`; the schema comes
  from the signatures. A personal key authenticates the member (`McpKeyAuthenticator`, a stateless firewall).
- **Call links.** `CallLinks` uses Symfony's `LoginLinkHandler` (signed, 15 minutes, a few uses) with a target path:
  the model hands the seller a link, and the phone screen opens already signed in.
- **An MCP App.** `DebriefApp` (`#[AsMcpApp]`) renders the debrief with the site's own Twig partial and stylesheet.
- **Tests without the network.** `tests/Fake/ScriptedModel` builds a Symfony AI `MockPlatformFactory` platform that
  answers each kind of prompt, so the whole suite (onboarding, a streamed call, the MCP server) runs offline.

## Architecture

```
src/
  Ai/           LanguageModel (Symfony AI), Data (typed reading of the model's JSON)
  Call/         CallManager (start, talk, expire, finish, abandon, replay), TurnStreamer, SentenceSplitter
    Persona/    the persona's prompt, reply and marker filter
    Coach/      the coach's prompt, the debrief and its scoring
    Speech/     talk share, questions, filler words, pace
  Scenario/     situation canvas and drawer, scenario writer and factory, surprise calls
  Company/      website reader, offer analyst, example company, team and missions
  Progress/     levels and XP, a member's figures, the team board, missions
  Voice/        edge-tts synthesis, voice casting per language, gender and tone
  Mcp/          the MCP tools, the debrief app, call links
  Onboarding/   the steps and the guard that steers first-boot visitors
  Controller/   thin controllers; Form/ typed models validated with constraints
  Entity/ Enum/ Model/ Repository/  the domain
templates/      Twig, with anonymous Twig components; assets/ Stimulus controllers and one CSS design system
translations/   English and French; config/sparring/{en,fr}/ the example company and situation canvas
```

## Quality

```bash
php vendor/bin/phpunit                    # unit and functional tests, offline
vendor/bin/phpstan analyse                # level max, with the Symfony and Doctrine extensions
vendor/bin/php-cs-fixer fix --dry-run     # Symfony coding standard
```

## Configuration

| Variable | Default | |
|---|---|---|
| `AI_API_KEY` | — | The model provider's key (required). |
| `AI_BASE_URL` | `https://opencode.ai/zen/go/v1` | Any OpenAI-compatible endpoint, e.g. `https://api.openai.com/v1`. |
| `AI_MODEL` | `deepseek-v4.1-flash` | e.g. `gpt-5.4-mini`. A fast model keeps the calls lively. |
| `AI_COMPLETIONS_PATH` | `/chat/completions` | |
| `AI_HEADERS` | `{}` | Extra HTTP headers, as JSON. |
| `DEFAULT_URI` | `http://127.0.0.1:8000` | The instance's public address, for links made outside a request. |
| `MCP_ALLOWED_HOSTS` | `localhost,127.0.0.1,…` | Host names the MCP endpoint answers on: add your domain. |
| `TRUSTED_PROXIES` | — | Your reverse proxy, e.g. `private_ranges`. |
| `EDGE_TTS`, `VOICES_DIR` | `var/edge-tts/bin/edge-tts`, `var/voices` | Without edge-tts, the browser's own voice speaks. |
