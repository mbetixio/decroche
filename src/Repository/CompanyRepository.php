<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Company;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * The company of this instance: there is exactly one.
 *
 * @extends ServiceEntityRepository<Company>
 */
final class CompanyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Company::class);
    }

    /** Its id once known: most pages ask for the company several times, the identity map then answers. */
    private ?int $id = null;

    /**
     * The company, created empty on first boot.
     */
    public function get(): Company
    {
        $company = (null !== $this->id ? $this->find($this->id) : null) ?? $this->findOneBy([], ['id' => 'ASC']);
        if (null === $company) {
            $company = new Company();
            $this->getEntityManager()->persist($company);
            $this->getEntityManager()->flush();
        }
        $this->id = $company->id;

        return $company;
    }
}
