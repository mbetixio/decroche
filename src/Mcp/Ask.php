<?php

declare(strict_types=1);

namespace App\Mcp;

use Mcp\Exception\ToolCallException;
use Mcp\Schema\Elicitation\ElicitationSchema;
use Mcp\Schema\Enum\ElicitAction;
use Mcp\Server\ClientGateway;

/**
 * Asks the person at the keyboard, not their AI: a form their client shows before a tool acts (an MCP elicitation).
 * The AI fills it in from the conversation; the person corrects it, then accepts or cancels. A client that cannot
 * show forms is not asked, and the tool goes on with what the AI gave it.
 *
 * On a stateless client (2026-07-28) the call is replayed once the form is answered, so a tool asks before it acts.
 */
final class Ask
{
    /**
     * @return array<string, mixed>|null the answers, or null when the client cannot show forms
     *
     * @throws ToolCallException when the person cancels or declines: nothing is done
     */
    public static function form(ClientGateway $client, string $message, ElicitationSchema $schema): ?array
    {
        if (!$client->supportsElicitation()) {
            return null;
        }
        $answer = $client->elicit($message, $schema);
        if (ElicitAction::Accept !== $answer->action) {
            throw new ToolCallException('Cancelled by the user: nothing was done.');
        }

        return $answer->content ?? [];
    }
}
