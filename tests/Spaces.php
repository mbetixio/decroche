<?php

namespace App\Tests;

use App\Entity\Seller;
use App\Entity\Workspace;
use App\Game\Etixio;
use App\Game\Situations;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Les espaces des tests : celui d'Etixio, prêt à jouer (son offre, ses situations), et ses membres déjà présentés.
 */
trait Spaces
{
    private function etixio(): Workspace
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $space = $em->getRepository(Workspace::class)->findOneBy(['name' => 'Etixio']);
        if (null === $space) {
            $space = new Workspace('Etixio');
            $space->offer = Etixio::OFFER;
            $space->situations = Situations::ALL;
            $space->sectors = Situations::SECTORS;
            $em->persist($space);
            $em->flush();
        }

        return $space;
    }

    private function member(string $name, string $email, string $role = Seller::SELLER, ?Workspace $space = null): Seller
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $seller = $em->getRepository(Seller::class)->findOneBy(['email' => mb_strtolower($email)]);
        if (null === $seller) {
            $seller = new Seller($name, $email, $role, $space ?? $this->etixio());
            $seller->profile = 'Commercial chez Etixio depuis deux ans, surtout des PME, à l\'aise en découverte.';
            $em->persist($seller);
            $em->flush();
        }

        return $seller;
    }
}
