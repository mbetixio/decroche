<?php

declare(strict_types=1);

namespace App\Mcp;

use Mcp\Capability\Attribute\McpPrompt;

/**
 * Ready-made requests the seller picks in their AI client (a "/" menu in most): each one says which Décroche tools
 * to chain, and what the client should look for in its own connectors (e-mail, calendar, CRM) first. They hold no
 * data: the tools fetch it, with the member's rights.
 */
final readonly class Prompts
{
    /**
     * @param string $meeting who the meeting is with, or how to find it ("Claire at Alpine, Thursday", "my next client call")
     * @param string $mode    "link" (default) to take the call out loud in the browser, "chat" to play it here
     *
     * @return list<array{role: string, content: string}>
     */
    #[McpPrompt(name: 'rehearse_meeting', title: 'Rehearse an upcoming meeting', description: 'Gathers what your e-mails, calendar or notes say about an upcoming client meeting, then has Décroche write a rehearsal of it, with a credible client who hides what you will have to find out.')]
    public function rehearseMeeting(string $meeting, string $mode = 'link'): array
    {
        return [['role' => 'user', 'content' => <<<TXT
            I have a client meeting coming up and I want to rehearse it on Décroche first: {$meeting}.
            1. Look through what you can reach (my e-mails, my calendar, my notes or CRM) for this meeting and this client:
               who they are, their company, the thread so far, what they asked for, what is at stake, the date.
            2. Write a short prospect sheet from it, only facts you found, then show it to me and wait for my go-ahead.
            3. Call Décroche's prepare_call with this sheet as the prospect (mode "{$mode}"); pick the type and difficulty
               that fit the meeting. With a link, give it to me; in the chat, play the call as its instructions say.
            Do not try to guess the hidden brief: finding it is the exercise. After the call, I will ask for the debrief.
            TXT]];
    }

    /**
     * @return list<array{role: string, content: string}>
     */
    #[McpPrompt(name: 'review_my_calls', title: 'Review my calls', description: 'Reads your figures and your latest debriefs, tells you what holds you back, and offers a call that trains your weakest point.')]
    public function reviewMyCalls(): array
    {
        return [['role' => 'user', 'content' => <<<TXT
            Help me see where I stand on Décroche.
            1. Get my figures with my_progress: the trend, the average by criterion, my results by situation, my missions.
            2. Tell me plainly, in a few lines, what I do well and the one skill that holds me back, with the evidence from
               my latest debriefs (debrief, for a call id), quoting what I said.
            3. Offer me a call that trains that skill: new_call with focus set to that criterion's key. Start it only if I say
               yes.
            TXT]];
    }

    /**
     * @return list<array{role: string, content: string}>
     */
    #[McpPrompt(name: 'coach_my_team', title: 'Coach my team', description: 'For managers: who is progressing, who is stuck and on what, and the missions worth giving this week.')]
    public function coachMyTeam(): array
    {
        return [['role' => 'user', 'content' => <<<TXT
            I manage a sales team on Décroche. Give me this week's picture.
            1. Get the team's figures with team, and member_progress for anyone who stands out (stuck, dropping, or
               not playing).
            2. In a short table: each member, their trend, what they need to work on, and a word on why.
            3. Suggest one mission per member who needs it (a situation, a number of calls, a minimum score, a date), and
               give it with give_mission only once I have approved it.
            TXT]];
    }
}
