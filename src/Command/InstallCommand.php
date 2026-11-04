<?php

namespace App\Command;

use App\Entity\Scenario;
use App\Entity\Seller;
use App\Game\Situations;
use App\Game\Etixio;
use App\Entity\Workspace;
use App\Training\ScenarioWriter;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Crée ou met à jour la base, charge la bibliothèque de scénarios si elle est vide, et crée un compte manager si on le
 * demande.
 */
#[AsCommand('app:install', 'Prépare la base de Sparring : schéma, scénarios de départ, compte manager.')]
final readonly class InstallCommand
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $hasher,
        #[Autowire('%kernel.project_dir%/config/scenarios.yaml')]
        private string $library,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option('E-mail d\'un compte manager à créer')] string $manager = '',
        #[Option('Son nom')] string $name = 'Manager',
        #[Option('Son mot de passe')] string $password = '',
        #[Option('Créer aussi l\'espace de démonstration Etixio (son offre, ses situations, sa bibliothèque)', name: 'etixio')] bool $withEtixio = false,
    ): int {
        (new SchemaTool($this->em))->updateSchema($this->em->getMetadataFactory()->getAllMetadata());
        $io->success('Base à jour.');

        // Les comptes et scénarios d'avant les espaces rejoignent l'espace d'Etixio, prérempli avec son offre.
        $orphans = $this->em->getRepository(Seller::class)->findBy(['workspace' => null]);
        $etixio = $this->em->getRepository(Workspace::class)->findOneBy(['name' => 'Etixio']);
        if ([] !== $orphans || $etixio instanceof Workspace || $withEtixio) {
            if (!$etixio instanceof Workspace) {
                $etixio = new Workspace('Etixio');
                $etixio->website = 'https://etixio.com';
                $etixio->offer = Etixio::OFFER;
                $etixio->situations = Situations::ALL;
                $etixio->sectors = Situations::SECTORS;
                $etixio->analyzedAt = new \DateTimeImmutable();
                $this->em->persist($etixio);
            }
            // Les situations ajoutées depuis au catalogue d'Etixio le rejoignent, sans toucher à celles qu'on a corrigées.
            $etixio->situations += Situations::ALL;
            foreach ($orphans as $seller) {
                $seller->workspace = $etixio;
            }
            foreach ($this->em->getRepository(Scenario::class)->findBy(['workspace' => null]) as $scenario) {
                $scenario->workspace = $etixio;
            }
            $this->em->flush();
            if ([] !== $orphans) {
                $io->success(\sprintf('%d compte(s) rattaché(s) à l\'espace Etixio.', \count($orphans)));
            }
        }

        // La bibliothèque de départ : ajoutée si elle manque, mise à jour (même titre) si elle a changé.
        $list = Yaml::parseFile($this->library);
        $repo = $this->em->getRepository(Scenario::class);
        $added = $updated = 0;
        foreach (\is_array($list) && null !== $etixio ? $list : [] as $d) {
            $fresh = ScenarioWriter::fromArray($d);
            $fresh->workspace = $etixio;
            $existing = $repo->findOneBy(['title' => $fresh->title, 'author' => null, 'situation' => null, 'workspace' => $etixio]);
            if (null === $existing) {
                $this->em->persist($fresh);
                ++$added;
                continue;
            }
            foreach (['category', 'difficulty', 'minutes', 'context', 'goal', 'persona', 'opening', 'brief'] as $field) {
                if ($existing->{$field} !== $fresh->{$field}) {
                    $existing->{$field} = $fresh->{$field};
                    ++$updated;
                }
            }
        }
        $this->em->flush();
        $io->success(\sprintf('Bibliothèque : %d scénario(s) ajouté(s), %d champ(s) mis à jour.', $added, $updated));

        if ('' !== $manager) {
            if ('' === $password) {
                $io->error('Donnez un mot de passe : --password=…');

                return 1;
            }
            $seller = $this->em->getRepository(Seller::class)->findOneBy(['email' => mb_strtolower($manager)]) ?? new Seller($name, $manager, Seller::MANAGER, $etixio);
            if (null === $seller->workspace) {
                $io->error('Un manager se crée dans l\'espace Etixio (--etixio) ; les autres sociétés s\'inscrivent sur /inscription.');

                return 1;
            }
            $seller->role = Seller::MANAGER;
            $seller->setPassword($this->hasher->hashPassword($seller, $password));
            $this->em->persist($seller);
            $this->em->flush();
            $io->success('Compte manager prêt : '.$seller->email);
        }

        return 0;
    }
}
