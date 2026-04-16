<?php

namespace App\Repository;

use App\Entity\TrackedAvatar;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TrackedAvatar>
 */
class TrackedAvatarRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TrackedAvatar::class);
    }

    public function findEnabled(): array
    {
        return $this->createQueryBuilder('ta')
            ->where('ta.trackingEnabled = :enabled')
            ->setParameter('enabled', true)
            ->getQuery()
            ->getResult();
    }

    public function findAllWithProfile(): array
    {
        return $this->createQueryBuilder('ta')
            ->leftJoin('ta.notificationChannel', 'nc')
            ->addSelect('nc')
            ->orderBy('ta.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findOneWithProfile(string $avatarKey): ?TrackedAvatar
    {
        return $this->createQueryBuilder('ta')
            ->leftJoin('ta.notificationChannel', 'nc')
            ->addSelect('nc')
            ->where('ta.avatarKey = :key')
            ->setParameter('key', strtolower($avatarKey))
            ->getQuery()
            ->getOneOrNullResult();
    }
}
