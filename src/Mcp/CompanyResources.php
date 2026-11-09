<?php

declare(strict_types=1);

namespace App\Mcp;

use App\Model\Criterion;
use App\Model\Situation;
use App\Repository\CompanyRepository;
use Mcp\Capability\Attribute\McpResource;

/**
 * What every member of the company may read, as MCP resources: the offer sheet the calls are built on, the catalogue
 * of call situations and the scorecard the coach grades against. A client can attach them to a conversation, to
 * prepare a meeting or explain a grade.
 */
final readonly class CompanyResources
{
    public function __construct(
        private CurrentMember $me,
        private CompanyRepository $companies,
    ) {
    }

    #[McpResource(uri: 'practicall://offer', name: 'offer', title: 'The offer sheet', description: 'What the company sells, its terms, references, limits and the classic objections: what the personas know and the coach checks.', mimeType: 'text/plain')]
    public function offer(): string
    {
        $this->me->member();
        $company = $this->companies->get();

        return "{$company->name}\n\n{$company->offer}";
    }

    /**
     * @return array<string, mixed>
     */
    #[McpResource(uri: 'practicall://situations', name: 'situations', title: 'The call situations', description: 'The catalogue the surprise calls are drawn from: each situation with who calls whom, its pitch and its objectives.', mimeType: 'application/json')]
    public function situations(): array
    {
        $this->me->member();

        return ['situations' => array_values(array_map(static fn (Situation $s): array => $s->toArray(), $this->companies->get()->situations()))];
    }

    /**
     * @return array<string, mixed>
     */
    #[McpResource(uri: 'practicall://scorecard', name: 'scorecard', title: 'The scorecard', description: 'The criteria the coach grades every call against, 0 to 10, with what each one looks for. Their keys are the focus of new_call.', mimeType: 'application/json')]
    public function scorecard(): array
    {
        $this->me->member();

        return ['criteria' => array_values(array_map(static fn (Criterion $c): array => ['key' => $c->key, 'label' => $c->label, 'looks_for' => $c->hint], $this->companies->get()->scorecard()))];
    }
}
