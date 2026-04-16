<?php

namespace App\Repository;

use App\Entity\NotificationChannel;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NotificationChannel>
 */
class NotificationChannelRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NotificationChannel::class);
    }

    public function findAllEnabled(): array
    {
        return $this->createQueryBuilder('nc')
            ->where('nc.enabled = :enabled')
            ->setParameter('enabled', true)
            ->getQuery()
            ->getResult();
    }

    public function findByType(string $type): array
    {
        return $this->createQueryBuilder('nc')
            ->where('nc.type = :type')
            ->setParameter('type', $type)
            ->getQuery()
            ->getResult();
    }
}
