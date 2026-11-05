<?php

namespace App\Controller;

use App\Entity\Seller;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

/**
 * Les contrôleurs du site, qui savent quel commercial est connecté.
 */
abstract class Controller extends AbstractController
{
    protected function me(): Seller
    {
        $user = $this->getUser();

        return $user instanceof Seller ? $user : throw $this->createAccessDeniedException();
    }

    protected function space(): \App\Entity\Workspace
    {
        return $this->me()->space();
    }

    protected function checkToken(string $id, ?string $token): void
    {
        if (!$this->isCsrfTokenValid($id, (string) $token)) {
            throw $this->createAccessDeniedException('Le formulaire a expiré : rechargez la page.');
        }
    }
}
