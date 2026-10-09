<?php

namespace App\Repository;

use App\Entity\AppConfiguration;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<AppConfiguration> */
class AppConfigurationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AppConfiguration::class);
    }

    public function getSingleton(): ?AppConfiguration
    {
        return $this->findOneBy([], ['id' => 'ASC']);
    }
}
