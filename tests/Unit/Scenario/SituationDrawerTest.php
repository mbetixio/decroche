<?php

declare(strict_types=1);

namespace App\Tests\Unit\Scenario;

use App\Entity\Company;
use App\Enum\Difficulty;
use App\Enum\Language;
use App\Enum\Tone;
use App\Scenario\Draw;
use App\Scenario\SituationCanvas;
use App\Scenario\SituationDrawer;
use App\Tests\Unit\Fixtures;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Drawing a surprise call with a seeded randomizer: a given situation, recent ones avoided, the dressing taken from
 * the company and the canvas (config/decroche), the tone from the situation and the difficulty around the target.
 */
final class SituationDrawerTest extends TestCase
{
    use Fixtures;

    private static function drawer(int $seed = 42): SituationDrawer
    {
        return new SituationDrawer(new SituationCanvas(\dirname(__DIR__, 3).'/config/decroche'), new Randomizer(new Mt19937($seed)));
    }

    private static function catalogue(Language $language = Language::English): Company
    {
        $company = self::company($language);
        $company->setSituations([self::situation('price_request'), self::situation('unhappy_client'), self::situation('cold_call')]);
        $company->setSectors(['Retail', 'Banking']);

        return $company;
    }

    public function testDrawsTheGivenSituation(): void
    {
        $drawer = self::drawer();
        for ($i = 0; $i < 20; ++$i) {
            self::assertSame('unhappy_client', $drawer->draw(self::catalogue(), key: 'unhappy_client')->situation->key);
        }
    }

    public function testAGivenSituationOverridesTheRecentOnes(): void
    {
        self::assertSame('cold_call', self::drawer()->draw(self::catalogue(), key: 'cold_call', avoid: ['cold_call'])->situation->key);
    }

    public function testAnUnknownGivenSituationFallsBackToADraw(): void
    {
        self::assertContains(self::drawer()->draw(self::catalogue(), key: 'nope')->situation->key, ['price_request', 'unhappy_client', 'cold_call']);
    }

    public function testAvoidsRecentSituations(): void
    {
        $drawer = self::drawer();
        $keys = [];
        for ($i = 0; $i < 30; ++$i) {
            $keys[] = $drawer->draw(self::catalogue(), avoid: ['price_request', 'cold_call'])->situation->key;
        }

        self::assertSame(['unhappy_client'], array_values(array_unique($keys)));
    }

    public function testDrawsAnyWhenAllAreRecent(): void
    {
        $drawer = self::drawer();
        $keys = [];
        for ($i = 0; $i < 60; ++$i) {
            $keys[$drawer->draw(self::catalogue(), avoid: ['price_request', 'unhappy_client', 'cold_call'])->situation->key] = true;
        }

        self::assertCount(3, $keys);
    }

    public function testTheDrawIsReproducibleWithTheSameSeed(): void
    {
        self::assertEquals(self::drawer(7)->draw(self::catalogue()), self::drawer(7)->draw(self::catalogue()));
    }

    public function testTheDressing(): void
    {
        $draw = self::drawer()->draw(self::catalogue(), key: 'unhappy_client');
        $canvas = new SituationCanvas(\dirname(__DIR__, 3).'/config/decroche');

        self::assertInstanceOf(Draw::class, $draw);
        self::assertContains($draw->sector, ['Retail', 'Banking']);
        self::assertContains($draw->size, $canvas->sizes(Language::English));
        self::assertContains($draw->city, $canvas->cities(Language::English));
        self::assertContains($draw->tone, Tone::forSituation('unhappy_client'));
    }

    public function testASectorIsAlwaysGiven(): void
    {
        $company = self::catalogue();
        $company->setSectors([]);

        self::assertSame('business services', self::drawer()->draw($company)->sector);
    }

    public function testTheTargetSetsTheDifficulty(): void
    {
        $drawer = self::drawer();
        $beginner = $veteran = [];
        for ($i = 0; $i < 40; ++$i) {
            $beginner[] = $drawer->draw(self::catalogue(), Difficulty::Approachable)->difficulty;
            $veteran[] = $drawer->draw(self::catalogue(), Difficulty::Tough)->difficulty;
        }

        self::assertNotContains(Difficulty::Tough, $beginner);
        self::assertContains(Difficulty::Approachable, $beginner);
        self::assertNotContains(Difficulty::Approachable, $veteran);
        self::assertContains(Difficulty::Tough, $veteran);
    }

    public function testACompanyWithoutSituations(): void
    {
        $this->expectException(\LogicException::class);

        self::drawer()->draw(new Company());
    }
}
