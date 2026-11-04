<?php

namespace App\Mcp;

use App\Entity\Seller;
use App\Entity\Training;
use App\Training\Trainer;
use Doctrine\ORM\EntityManagerInterface;
use Mcp\Exception\ToolCallException;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Le commercial au nom duquel le client MCP s'entraîne, et ses sessions.
 */
final readonly class Me
{
    public function __construct(
        private Security $security,
        private EntityManagerInterface $em,
        private Trainer $trainer,
    ) {
    }

    public function seller(): Seller
    {
        $user = $this->security->getUser();

        return $user instanceof Seller ? $user : throw new ToolCallException('Connectez-vous avec votre adresse personnelle (/mcp?key=…), affichée dans « Mon compte ».');
    }

    /**
     * Une session du commercial : celle demandée, sinon celle en cours, sinon la dernière.
     */
    public function training(int $id = 0): Training
    {
        $seller = $this->seller();
        if ($id > 0) {
            $training = $this->em->find(Training::class, $id);
            if (null === $training || $training->seller !== $seller) {
                throw new ToolCallException('Aucune session n°'.$id.' à votre nom.');
            }
            $this->trainer->expire($training);

            return $training;
        }

        return $this->trainer->current($seller)
            ?? $this->em->getRepository(Training::class)->findOneBy(['seller' => $seller], ['startedAt' => 'DESC'])
            ?? throw new ToolCallException('Aucune session : lancez-en une avec start_sparring (scenarios liste les mises en situation).');
    }
}
