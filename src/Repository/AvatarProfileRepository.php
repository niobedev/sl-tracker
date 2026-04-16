<?php

namespace App\Repository;

use App\Entity\AvatarProfile;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AvatarProfile>
 */
class AvatarProfileRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AvatarProfile::class);
    }

    /**
     * Fetch raw image bytes via DBAL, bypassing Doctrine's BLOB→stream conversion
     * which can produce an already-exhausted stream resource in PHP-FPM.
     */
    public function findImageData(string $avatarKey): ?string
    {
        $data = $this->getEntityManager()
            ->getConnection()
            ->fetchOne('SELECT image_data FROM avatar_profile WHERE avatar_key = ?', [$avatarKey]);

        return ($data !== false && $data !== null && $data !== '') ? $data : null;
    }
}
