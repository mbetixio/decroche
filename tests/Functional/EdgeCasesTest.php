<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Demo\DemoCompany;
use App\Entity\Member;
use App\Enum\Language;
use App\Enum\MemberRole;
use App\Onboarding\OnboardingStep;
use App\Repository\CompanyRepository;
use App\Repository\InvitationRepository;
use App\Repository\MemberRepository;
use App\Team\TeamManager;
use App\Tests\Fake\ScriptedModel;
use Doctrine\ORM\EntityManagerInterface;

/**
 * The paths off the happy one: an onboarding visited out of order or by the wrong person, analyses that fail, a
 * rehearsal the model cannot write, an invitation overtaken by an account, and the guards that keep each visitor where
 * they belong.
 */
final class EdgeCasesTest extends FunctionalTestCase
{
    public function testTheOnboardingKeepsEachVisitorOnTheirStep(): void
    {
        $manager = $this->midOnboarding(OnboardingStep::Company);
        $seller = $this->member();

        // Signed out: the sign-in page; a seller: the waiting room; the manager: the step reached, not further.
        $this->client->request('GET', '/');
        self::assertResponseRedirects('/login');
        $this->client->request('GET', '/welcome/offer');
        self::assertResponseRedirects('/login');
        $this->signIn($seller);
        $this->client->request('GET', '/library');
        self::assertResponseRedirects('/welcome/wait');
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();

        $this->signIn($manager);
        $this->client->request('GET', '/welcome/account');
        self::assertResponseRedirects('/welcome/company');
        $this->client->request('GET', '/welcome/offer');
        self::assertResponseRedirects('/welcome/company');

        // Nothing to analyse: neither a website nor a description.
        $this->client->request('GET', '/welcome/company');
        $this->submit('Analyse', ['company[name]' => 'Acme', 'company[website]' => '', 'company[about]' => 'Short.']);
        self::assertSelectorTextContains('main', 'Give a readable website or describe the company in a few lines.');
    }

    public function testTheAnalysisSaysWhatWentWrong(): void
    {
        $manager = $this->midOnboarding(OnboardingStep::Analysis);
        $this->signIn($manager);
        $crawler = $this->client->request('GET', '/welcome/analysis');
        $token = (string) $crawler->filter('[data-analysis-token-value]')->attr('data-analysis-token-value');

        $analyse = function () use ($token): string {
            $this->client->request('POST', '/welcome/analysis/run', ['token' => $token], server: ['HTTP_ACCEPT' => 'application/x-ndjson']);
            $end = $this->lines()[\count($this->lines()) - 1];
            self::assertSame('error', $end['t']);

            return $end['error'];
        };
        self::assertStringContainsString('Give a readable website', $analyse(), 'Nothing to analyse.');

        $company = $this->service(CompanyRepository::class)->get();
        $company->about = 'Acme builds software for retailers and logistics firms across Europe.';
        $this->service(EntityManagerInterface::class)->flush();
        ScriptedModel::$unreachable = true;
        self::assertStringContainsString('try again', $analyse());

        // A sheet or a catalogue too short to use is a failure too.
        ScriptedModel::$unreachable = false;
        ScriptedModel::$answers['sales training tool'] = (string) json_encode(['name' => 'Acme', 'offer' => 'Software.', 'sectors' => []]);
        self::assertStringContainsString('try again', $analyse());
        ScriptedModel::$answers = ['catalogue of situations' => (string) json_encode(['situations' => []])];
        self::assertStringContainsString('try again', $analyse());
    }

    public function testTheTeamStepInvitesOnlyNewcomers(): void
    {
        $manager = $this->midOnboarding(OnboardingStep::Team);
        $this->member();
        $this->signIn($manager);

        $this->client->request('GET', '/welcome/team');
        $this->submit('Create the links', ['invitations[emails]' => "seller@decroche.test\nsam@decroche.test"]);
        self::assertResponseRedirects('/welcome/team');
        self::assertSame(['sam@decroche.test'], array_map(static fn ($i): string => $i->email, $this->service(InvitationRepository::class)->pending()));

        $this->client->followRedirect();
        $this->submit('Continue');
        self::assertResponseRedirects('/welcome/done');
    }

    public function testTheSettingsAnalysisReportsEachOutcome(): void
    {
        $this->onboardedCompany();
        $this->signIn($this->manager());
        // The description is enough to work from: no website is read over the network here.
        $company = $this->service(CompanyRepository::class)->get();
        $company->website = null;
        $before = array_keys($company->situations());
        $this->service(EntityManagerInterface::class)->flush();
        $analyse = function (): void {
            $crawler = $this->client->request('GET', '/settings');
            $this->client->submit($crawler->filter('form[action="/settings/analyze"]')->form());
            self::assertResponseRedirects('/settings#offer');
            $this->client->followRedirect();
        };

        $analyse();
        self::assertSelectorTextContains('.flash.success', 'The website was analysed again.');
        $after = array_keys($this->service(CompanyRepository::class)->get()->situations());
        self::assertSame($before, \array_slice($after, 0, \count($before)), 'The manager\'s situations stay as they were.');
        self::assertGreaterThan(\count($before), \count($after), 'The situations the catalogue lacked were added.');

        ScriptedModel::$unreachable = true;
        $analyse();
        self::assertSelectorTextContains('.flash.error', 'try again', 'The model failed.');

        ScriptedModel::$unreachable = false;
        $company = $this->service(CompanyRepository::class)->get();
        $company->about = '';
        $this->service(EntityManagerInterface::class)->flush();
        $analyse();
        self::assertSelectorTextContains('.flash.error', 'Give a readable website or describe the company in a few lines.');
    }

    public function testARehearsalTheModelCannotWriteIsSaid(): void
    {
        $this->onboardedCompany();
        $this->signIn($this->member());
        // The writer agent, then the plain answer asked instead: both unusable.
        ScriptedModel::$garbled = 2;

        $crawler = $this->client->request('GET', '/prepare');
        $this->client->submit($crawler->selectButton('Write the rehearsal')->form(), [
            'prepare[fromCall]' => '1',
            'prepare[text]' => "CLIENT: Freshcart, Ana speaking.\nSELLER: Hello Ana, you asked for our rates?\nCLIENT: Yes, we lose orders between tools.",
        ]);
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('transcript of a real call', ScriptedModel::$prompts[0]);
        self::assertSelectorExists('form[name=prepare]', 'The form stays, with what was typed.');
    }

    public function testTheLibraryIsFilteredByType(): void
    {
        $this->onboardedCompany();
        $this->service(DemoCompany::class)->loadLibrary(Language::English);
        $this->signIn($this->member());

        $this->client->request('GET', '/library?type=negotiation');
        self::assertResponseIsSuccessful();
        $this->client->request('GET', '/library?type=unknown');
        self::assertResponseIsSuccessful();
    }

    public function testAnInvitationOvertakenByAnAccountCannotBeUsed(): void
    {
        $this->onboardedCompany();
        $team = $this->service(TeamManager::class);
        $invitation = $team->invite('sam@decroche.test', MemberRole::Seller, null);
        $team->create('Sam Early', 'sam@decroche.test', 'password-123', MemberRole::Seller);

        $this->client->request('GET', '/join/'.$invitation->token);
        $this->submit('Join the team', ['join[name]' => 'Sam Late', 'join[password]' => 'password-456']);
        self::assertResponseRedirects('/login');
        self::assertSame('Sam Early', $this->service(MemberRepository::class)->findOneByEmail('sam@decroche.test')?->name);

        // Once accepted, an invitation cannot be accepted again.
        $this->expectExceptionObject(new \DomainException('team.error.invitation_used'));
        $fresh = $team->invite('lou@decroche.test', MemberRole::Seller, null);
        self::assertSame($fresh, $team->invite('lou@decroche.test', MemberRole::Seller, null), 'Invited twice: the same link.');
        $team->accept($fresh, 'Lou', 'password-789');
        $team->accept($fresh, 'Lou', 'password-789');
    }

    public function testASignedInMemberSkipsTheSignInAndManagersKeepTheirRole(): void
    {
        $this->onboardedCompany();
        $manager = $this->signIn($this->manager());

        $this->client->request('GET', '/login');
        self::assertResponseRedirects('/');

        $crawler = $this->client->request('GET', '/team/'.$manager->id);
        self::assertSame(0, $crawler->filter('form[action$="/role"]')->count(), 'No form to change one\'s own role.');
        $this->client->request('POST', '/team/'.$manager->id.'/role', ['_token' => $this->csrfToken('role-'.$manager->id)]);
        self::assertResponseRedirects('/team/'.$manager->id);
        self::assertSame(MemberRole::Manager, $this->service(MemberRepository::class)->findOneByEmail('manager@decroche.test')?->role);
    }

    /**
     * An instance in the middle of its onboarding, at the given step, and its first manager.
     */
    private function midOnboarding(OnboardingStep $step): Member
    {
        $manager = $this->manager();
        $company = $this->service(CompanyRepository::class)->get();
        $company->name = 'Acme';
        $company->language = Language::English;
        $company->reach($step);
        $this->service(EntityManagerInterface::class)->flush();

        return $manager;
    }
}
