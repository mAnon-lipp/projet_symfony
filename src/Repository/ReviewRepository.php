<?php

namespace App\Repository;

use App\Entity\Album;
use App\Entity\Review;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Review>
 */
class ReviewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Review::class);
    }

    /**
     * @return Review[]
     */
    public function findRecentPublic(int $limit = 4): array
    {
        return $this->createQueryBuilder('review')
            ->leftJoin('review.album_id', 'album')
            ->leftJoin('album.artist', 'artist')
            ->leftJoin('review.user_id', 'user')
            ->addSelect('album', 'artist', 'user')
            ->andWhere('review.deleted_at IS NULL')
            ->orderBy('review.created_at', 'DESC')
            ->addOrderBy('review.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array{average: float|null, count: int}
     */
    public function getRatingSummaryForAlbum(Album $album): array
    {
        $summary = $this->createQueryBuilder('review')
            ->select('AVG(review.rating) AS rating_average')
            ->addSelect('COUNT(review.id) AS rating_count')
            ->andWhere('review.album_id = :album')
            ->andWhere('review.deleted_at IS NULL')
            ->andWhere('review.rating IS NOT NULL')
            ->setParameter('album', $album)
            ->getQuery()
            ->getSingleResult();

        return [
            'average' => $summary['rating_average'] !== null ? (float) $summary['rating_average'] : null,
            'count' => (int) $summary['rating_count'],
        ];
    }

    /**
     * @return Paginator<Review>
     */
    public function paginateForUser(User $user, int $page, int $pageSize): Paginator
    {
        if ($page < 1 || $pageSize < 1 || $page - 1 > intdiv(PHP_INT_MAX, $pageSize)) {
            throw new \InvalidArgumentException('Page and page size must produce a valid offset.');
        }

        $query = $this->createUserEntriesQueryBuilder($user)
            ->orderBy('review.listened_at', 'DESC')
            ->addOrderBy('review.id', 'DESC')
            ->getQuery()
            ->setFirstResult(($page - 1) * $pageSize)
            ->setMaxResults($pageSize);

        return new Paginator($query, fetchJoinCollection: false);
    }

    /**
     * @return Review[]
     */
    public function findRecentForUser(User $user, int $limit = 4): array
    {
        return $this->createUserEntriesQueryBuilder($user)
            ->orderBy('review.listened_at', 'DESC')
            ->addOrderBy('review.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Returns only the newest favorite entry for each album.
     *
     * @return Review[]
     */
    public function findFavoriteAlbumsForUser(User $user): array
    {
        $newerFavorite = $this->getEntityManager()->createQueryBuilder()
            ->select('newer.id')
            ->from(Review::class, 'newer')
            ->where('newer.user_id = review.user_id')
            ->andWhere('newer.album_id = review.album_id')
            ->andWhere('newer.is_favorite = true')
            ->andWhere('(newer.listened_at > review.listened_at OR (newer.listened_at = review.listened_at AND newer.id > review.id))')
            ->getDQL();

        return $this->createUserEntriesQueryBuilder($user)
            ->andWhere('review.is_favorite = true')
            ->andWhere('NOT EXISTS ('.$newerFavorite.')')
            ->orderBy('review.listened_at', 'DESC')
            ->addOrderBy('review.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    private function createUserEntriesQueryBuilder(User $user): QueryBuilder
    {
        return $this->createQueryBuilder('review')
            ->leftJoin('review.album_id', 'album')
            ->addSelect('album')
            ->leftJoin('album.artist', 'artist')
            ->addSelect('artist')
            ->leftJoin('album.genre', 'genre')
            ->addSelect('genre')
            ->andWhere('review.user_id = :user')
            ->setParameter('user', $user);
    }

//    /**
//     * @return Review[] Returns an array of Review objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('r')
//            ->andWhere('r.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('r.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Review
//    {
//        return $this->createQueryBuilder('r')
//            ->andWhere('r.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
