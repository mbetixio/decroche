<?php

declare(strict_types=1);

namespace App\Scenario;

use App\Ai\LanguageModel;
use App\Ai\ModelException;
use App\Ai\Output\Workspace as WorkspaceOutput;
use App\Entity\Scenario;
use App\Enum\BriefSection;
use App\Model\Workspace;
use App\Repository\CompanyRepository;
use Psr\Clock\ClockInterface;

/**
 * Writes the persona's workspace (their calendar and their own files) for a scenario that has none: one a manager
 * wrote by hand, or one of the example library. A drawn call or a rehearsal gets it from the scenario writer, in the
 * same go.
 */
final readonly class WorkspaceWriter
{
    public function __construct(
        private LanguageModel $model,
        private CompanyRepository $companies,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @throws ModelException when the model's answer cannot make a workspace
     */
    public function write(Scenario $scenario): Workspace
    {
        $company = $this->companies->get();
        $persona = $scenario->persona();
        $brief = implode("\n", array_filter(array_map(static fn (BriefSection $s): string => '' !== ($text = $scenario->brief($s)) ? "- {$s->value}: {$text}" : '', BriefSection::cases())));
        $system = <<<PROMPT
            You prepare a role-play to train the sellers of {$company->name}: a simulated client will look things up in
            their own files and calendar during the call. Write that workspace, true to who they are and to the
            situation, consistent with their hidden brief. Every text value is written in {$company->language->englishName()}.
            {$this->money($company->offer)}
            The workspace: {$this->hint()}
            PROMPT;
        $user = "The client: {$persona->name}, {$persona->role} at {$persona->company}. {$persona->personality}\n"
            ."The situation: {$scenario->title}. {$scenario->context}\n"
            .('' !== $scenario->setup() ? "From their side: {$scenario->setup()}\n" : '')
            .(null !== $scenario->material ? "The document the seller has:\n{$scenario->material}\n" : '')
            ."Their hidden brief:\n{$brief}";

        return Workspace::fromArray($this->model->structured($system, $user, WorkspaceOutput::class, 0.8, 'writer')->toArray());
    }

    /**
     * The persona's own systems, which they consult during the call with their tools: generic records, so that any
     * business fits (a shop's stock, a software firm's tickets, a factory's suppliers…).
     */
    /**
     * The offer, for the amounts in the files to match it (see ScenarioWriter::MONEY).
     */
    private function money(string $offer): string
    {
        return "The offer the seller sells, for reference:\n{$offer}\n".ScenarioWriter::MONEY;
    }

    public function hint(): string
    {
        $from = $this->clock->now()->modify('+1 weekday');
        $to = $from->modify('+9 weekdays');

        return '{"calendar": [{"when": "YYYY-MM-DD HH:MM", "minutes": 60, "what": "what it is"}, … 8 to 20 busy slots, only on working days (Monday to Friday) from '
            .$from->format('l Y-m-d').' to '.$to->format('l Y-m-d').', between 08:00 and 18:00, true to their job],'
            .' "sources": [{"key": "snake_case", "label": "its name", "description": "what it holds", "entries": [{"title": "reference and subject", "when": "YYYY-MM-DD or empty", "details": [{"name": "field", "value": "precise value"}], "note": "the text of the record, or empty"}, … at least 3 and up to 8 records in EVERY source]}, … 2 to 4 sources]}'
            .' — the persona\'s own files, as their systems hold them, chosen for their job and this situation: support tickets, stock levels, suppliers, orders, invoices, e-mails, CRM notes, project status, contracts, schedules, whatever their business uses. Each source holds at least three records, concrete (references, quantities, amounts, dates, names), consistent with the hidden brief and with each other, holding what a good seller could get them to look up. Never shown to the seller.';
    }
}
