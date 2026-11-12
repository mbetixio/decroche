<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Repository\CompanyRepository;
use App\Repository\ScenarioRepository;
use App\Tests\Fake\ScriptedModel;

/**
 * What a manager changes after the onboarding: the company, its situations and scorecard, a library scenario, and
 * the rehearsal of a real meeting any member can write.
 */
final class SettingsTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->onboardedCompany();
        $this->signIn($this->manager());
    }

    public function testTheCompanyIsRenamed(): void
    {
        $crawler = $this->client->request('GET', '/settings');
        $this->client->submit($crawler->filter('form[name=company]')->form(), ['company[name]' => 'Acme Retail']);
        self::assertResponseRedirects('/settings');
        self::assertSame('Acme Retail', $this->service(CompanyRepository::class)->get()->name);
    }

    public function testTheSituationsAreEditedButNeverAllRemoved(): void
    {
        $before = \count($this->service(CompanyRepository::class)->get()->situations());
        $this->post('/settings/situations', 'situations', static function (array $values): array {
            foreach (array_keys($values['situations']['situations']) as $i) {
                unset($values['situations']['situations'][$i]['keep']);
            }

            return $values;
        });
        self::assertSelectorTextContains('main', 'Keep at least one situation.');
        self::assertCount($before, $this->service(CompanyRepository::class)->get()->situations());

        $this->post('/settings/situations', 'situations', static function (array $values): array {
            $values['situations']['situations'][0]['label'] = 'A price request, the hard way';
            unset($values['situations']['situations'][1]['keep']);

            return $values;
        });
        self::assertResponseRedirects('/settings/situations');
        $situations = array_values($this->service(CompanyRepository::class)->get()->situations());
        self::assertCount($before - 1, $situations);
        self::assertSame('A price request, the hard way', $situations[0]->label);
    }

    public function testTheScorecardKeepsTwoToEightCriteria(): void
    {
        $this->post('/settings/scorecard', 'scorecard', static function (array $values): array {
            $values['scorecard']['criteria'] = [['label' => 'Rapport', 'hint' => '']];

            return $values;
        });
        self::assertSelectorTextContains('main', 'The scorecard has 2 to 8 criteria.', 'The message is translated, not its key.');

        $this->post('/settings/scorecard', 'scorecard', static function (array $values): array {
            $values['scorecard']['criteria'] = [['label' => 'Rapport', 'hint' => 'builds trust'], ['label' => 'Closing', 'hint' => '']];

            return $values;
        });
        self::assertResponseRedirects('/settings/scorecard');
        self::assertSame(['Rapport', 'Closing'], array_values(array_map(static fn ($c): string => $c->label, $this->service(CompanyRepository::class)->get()->scorecard())));
    }

    public function testAScenarioIsEdited(): void
    {
        $this->client->request('GET', '/library/new');
        $this->submit('Save', ['scenario' => [
            'title' => 'The CFO who wants a discount', 'type' => 'negotiation', 'difficulty' => '3', 'minutes' => '8',
            'context' => 'The CFO calls about the renewal.', 'goal' => 'Renew without discount.', 'personaName' => 'Paul Durand',
            'personaRole' => 'CFO', 'personaCompany' => 'Acme', 'personaPersonality' => 'Cold, precise.', 'opening' => 'Paul Durand. About the renewal.',
            'brief' => ['pain' => 'His budget was cut by 20%.'],
        ]]);
        $scenario = $this->service(ScenarioRepository::class)->findOneBy(['title' => 'The CFO who wants a discount']);
        self::assertNotNull($scenario);

        $this->client->request('GET', '/library/'.$scenario->id.'/edit');
        $this->submit('Save', ['scenario[goal]' => 'Renew at full price, for two years.']);
        self::assertResponseRedirects('/library/'.$scenario->id);
        $this->client->followRedirect();
        self::assertSelectorTextContains('main', 'Renew at full price, for two years.');
    }

    public function testALibraryScenarioGetsTheClientsFiles(): void
    {
        $values = ['scenario' => [
            'title' => 'The CFO who wants a discount', 'type' => 'negotiation', 'difficulty' => '3', 'minutes' => '8',
            'context' => 'The CFO calls about the renewal.', 'goal' => 'Renew without discount.', 'personaName' => 'Paul Durand',
            'personaRole' => 'CFO', 'personaCompany' => 'Acme', 'personaPersonality' => 'Cold, precise.', 'opening' => 'Paul Durand. About the renewal.',
            'brief' => ['pain' => 'His budget was cut by 20%.'],
        ]];
        $this->client->request('GET', '/library/new');
        $this->submit('Save', $values);
        $scenario = $this->service(ScenarioRepository::class)->findOneBy(['title' => 'The CFO who wants a discount']);
        self::assertSame(['tickets'], array_keys($scenario->workspace()->sources()), 'Written with the scenario.');

        // Saved again unchanged: the files stay; with a new brief, they are written again.
        ScriptedModel::$prompts = [];
        $this->client->request('GET', '/library/'.$scenario->id.'/edit');
        $this->submit('Save', ['scenario[goal]' => 'Renew at full price.']);
        self::assertSame([], ScriptedModel::$prompts);
        $this->client->request('GET', '/library/'.$scenario->id.'/edit');
        $this->submit('Save', ['scenario[brief][pain]' => 'His budget was cut by 40%.']);
        self::assertCount(1, ScriptedModel::$prompts);

        // Without the model, the scenario is saved all the same, without files.
        ScriptedModel::$unreachable = true;
        $values['scenario']['title'] = 'Another CFO';
        $this->client->request('GET', '/library/new');
        $this->submit('Save', $values);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.flash.error', 'files could not be written');
        self::assertTrue($this->service(ScenarioRepository::class)->findOneBy(['title' => 'Another CFO'])->workspace()->isEmpty());
    }

    public function testARealMeetingIsRehearsed(): void
    {
        $crawler = $this->client->request('GET', '/prepare');
        $this->client->submit($crawler->selectButton('Write the rehearsal')->form(), [
            'prepare[text]' => 'Tomorrow 10am, Ana Diaz, operations director at Freshcart (retail, 120 people), asked for our rates after losing orders between tools.',
        ]);
        self::assertResponseRedirects();
        self::assertStringContainsString('Freshcart', ScriptedModel::$prompts[array_key_last(ScriptedModel::$prompts)]);
        $this->client->followRedirect();
        self::assertSelectorTextContains('h1', 'A price request from the website');
    }

    public function testATooShortMeetingIsRefused(): void
    {
        $crawler = $this->client->request('GET', '/prepare');
        $this->client->submit($crawler->selectButton('Write the rehearsal')->form(), ['prepare[text]' => 'Ana, tomorrow']);
        self::assertSame([], ScriptedModel::$prompts, 'The model is not asked.');
    }

    /**
     * Submits a page's form with its values changed: rows of a collection can be removed, which a crawler form cannot.
     *
     * @param \Closure(array<string, mixed>): array<string, mixed> $change
     */
    private function post(string $page, string $form, \Closure $change): void
    {
        $crawler = $this->client->request('GET', $page);
        $found = $crawler->filter('form[name='.$form.']')->form();
        $this->client->request($found->getMethod(), $found->getUri(), $change($found->getPhpValues()));
    }
}
