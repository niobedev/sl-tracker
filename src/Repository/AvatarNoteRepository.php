<?php

namespace App\Repository;

use App\Entity\AvatarNote;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<AvatarNote> */
class AvatarNoteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AvatarNote::class);
    }

    /** @return AvatarNote[] */
    public function findForAvatar(string $avatarKey): array
    {
        return $this->createQueryBuilder('n')
            ->addSelect('u')
            ->join('n.author', 'u')
            ->where('n.avatarKey = :key')
            ->setParameter('key', $avatarKey)
            ->orderBy('n.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
