<?php

namespace App\Repository;

use App\Entity\AvatarReminder;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<AvatarReminder> */
class AvatarReminderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AvatarReminder::class);
    }

    /** @return AvatarReminder[] Active (unresolved) reminders for an avatar, ordered by due date ASC */
    public function findActiveForAvatar(string $avatarKey): array
    {
        return $this->createQueryBuilder('r')
            ->addSelect('u')
            ->join('r.author', 'u')
            ->where('r.avatarKey = :key')
            ->andWhere('r.resolvedAt IS NULL')
            ->setParameter('key', $avatarKey)
            ->orderBy('r.reminderAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return AvatarReminder[] Resolved reminders for an avatar, ordered by resolved date DESC */
    public function findResolvedForAvatar(string $avatarKey): array
    {
        return $this->createQueryBuilder('r')
            ->addSelect('u', 'rb')
            ->join('r.author', 'u')
            ->leftJoin('r.resolvedBy', 'rb')
            ->where('r.avatarKey = :key')
            ->andWhere('r.resolvedAt IS NOT NULL')
            ->setParameter('key', $avatarKey)
            ->orderBy('r.resolvedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return AvatarReminder[] All active reminders globally, ordered by due date ASC */
    public function findAllActive(int $limit = 0): array
    {
        $qb = $this->createQueryBuilder('r')
            ->addSelect('u')
            ->join('r.author', 'u')
            ->where('r.resolvedAt IS NULL')
            ->orderBy('r.reminderAt', 'ASC');

        if ($limit > 0) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    public function countAllActive(): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.resolvedAt IS NULL')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
