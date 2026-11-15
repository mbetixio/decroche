<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Repository\CallRepository;
use App\Repository\CompanyRepository;
use App\Repository\MemberRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * The console commands: the install (the real migrations on an empty database, with or without the demo), and the
 * voice cache pruning.
 */
final class CommandTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // The install starts from nothing: no table, the migrations create them.
        $em = $this->service(EntityManagerInterface::class);
        $em->getConnection()->executeStatement('PRAGMA foreign_keys = OFF');
        new SchemaTool($em)->dropDatabase();
        $em->getConnection()->executeStatement('PRAGMA foreign_keys = ON');
    }

    public function testAnInstallWithoutTheDemoLeavesTheOnboarding(): void
    {
        $tester = $this->command('app:install');
        $tester->execute([], ['interactive' => false]);

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('the onboarding sets it up', $tester->getDisplay());
        self::assertFalse($this->service(MemberRepository::class)->hasAny());

        // No language yet: the Pocket TTS server waits for the onboarding.
        $language = $this->command('app:language');
        $language->execute([]);
        self::assertSame(1, $language->getStatusCode());
        self::assertSame('', $language->getDisplay());
    }

    public function testAFailedMigrationStopsTheInstall(): void
    {
        // A database the migrations cannot run on: a table already there, under a shape of its own.
        $connection = $this->service(EntityManagerInterface::class)->getConnection();
        $schema = new SchemaTool($this->service(EntityManagerInterface::class));
        $schema->dropSchema($this->service(EntityManagerInterface::class)->getMetadataFactory()->getAllMetadata());
        $connection->executeStatement('DROP TABLE IF EXISTS doctrine_migration_versions');
        $connection->executeStatement('CREATE TABLE company (odd TEXT)');

        $tester = $this->command('app:install');
        $tester->execute(['--demo' => true], ['interactive' => false]);

        self::assertNotSame(0, $tester->getStatusCode());
        self::assertStringContainsString('could not be migrated', $tester->getDisplay());
    }

    public function testTheDemoInstallLoadsTheCompanyItsTeamAndCalls(): void
    {
        $tester = $this->command('app:install');
        $tester->execute(['--demo' => true, '--language' => 'fr', '--password' => 'demo-password-1'], ['interactive' => false]);

        $tester->assertCommandIsSuccessful();
        $display = $tester->getDisplay();
        self::assertStringContainsString('Etixio (French)', $display);
        self::assertStringContainsString('6 debriefed calls', $display);
        self::assertTrue($this->service(CompanyRepository::class)->get()->isOnboarded());
        self::assertCount(6, $this->service(CallRepository::class)->all());
        $language = $this->command('app:language');
        $language->execute([]);
        $language->assertCommandIsSuccessful();
        self::assertSame("fr\n", $language->getDisplay());

        // Run again: the demo is not loaded twice.
        $again = $this->command('app:install');
        $again->execute(['--demo' => true], ['interactive' => false]);
        self::assertStringContainsString('already has members', $again->getDisplay());
        self::assertCount(6, $this->service(CallRepository::class)->all());
    }

    public function testOldVoicesArePruned(): void
    {
        $dir = (string) self::getContainer()->getParameter('kernel.project_dir').'/var/voices_test';
        @mkdir($dir, 0o775, true);
        file_put_contents($dir.'/old.mp3', str_repeat('x', 1024));
        touch($dir.'/old.mp3', time() - 40 * 86400);

        $tester = $this->command('app:prune-voices');
        $tester->execute(['--days' => '30']);

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('1 voice file(s) deleted', $tester->getDisplay());
        self::assertFileDoesNotExist($dir.'/old.mp3');
    }

    private function command(string $name): CommandTester
    {
        return new CommandTester(new Application(self::$kernel)->find($name));
    }
}
