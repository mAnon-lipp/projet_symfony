<?php

namespace App\Repository;

use App\Entity\Album;
use App\Entity\Artist;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Album>
 */
class AlbumRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Album::class);
    }

    public function findOneByTitleAndArtist(string $title, Artist $artist): ?Album
    {
        return $this->createQueryBuilder('album')
            ->andWhere('LOWER(TRIM(album.title)) = LOWER(TRIM(:title))')
            ->andWhere('album.artist = :artist')
            ->setParameter('title', $title)
            ->setParameter('artist', $artist)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return Album[]
     */
    public function search(?string $query = null, ?int $genreId = null, ?int $year = null, int $limit = 50): array
    {
        $builder = $this->createQueryBuilder('album')
            ->leftJoin('album.artist', 'artist')
            ->leftJoin('album.genre', 'genre')
            ->addSelect('artist', 'genre')
            ->orderBy('album.title', 'ASC')
            ->setMaxResults($limit);

        if ($query !== null && trim($query) !== '') {
            $builder
                ->andWhere('(LOWER(album.title) LIKE LOWER(:query) OR LOWER(artist.name) LIKE LOWER(:query))')
                ->setParameter('query', '%' . trim($query) . '%');
        }

        if ($genreId !== null) {
            $builder
                ->andWhere('genre.id = :genreId')
                ->setParameter('genreId', $genreId);
        }

        if ($year !== null) {
            $start = new \DateTimeImmutable(sprintf('%d-01-01', $year));
            $end = $start->modify('+1 year');
            $builder
                ->andWhere('album.release_date >= :yearStart')
                ->andWhere('album.release_date < :yearEnd')
                ->setParameter('yearStart', $start)
                ->setParameter('yearEnd', $end);
        }

        return $builder->getQuery()->getResult();
    }

    /**
     * @return Album[]
     */
    public function findForAutocomplete(string $query, int $limit = 5): array
    {
        return $this->createQueryBuilder('album')
            ->leftJoin('album.artist', 'artist')
            ->addSelect('artist')
            ->andWhere('(LOWER(album.title) LIKE LOWER(:query) OR LOWER(artist.name) LIKE LOWER(:query))')
            ->setParameter('query', '%' . trim($query) . '%')
            ->orderBy('album.title', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Album[]
     */
    public function findAlbumsForAutocomplete(string $query, int $limit = 8): array
    {
        return $this->createQueryBuilder('album')
            ->leftJoin('album.artist', 'artist')
            ->addSelect('artist')
            ->andWhere('LOWER(album.title) LIKE LOWER(:query)')
            ->setParameter('query', '%' . trim($query) . '%')
            ->orderBy('album.title', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Album[]
     */
    public function findLatest(int $limit = 1): array
    {
        return $this->createQueryBuilder('album')
            ->leftJoin('album.artist', 'artist')
            ->leftJoin('album.genre', 'genre')
            ->addSelect('artist', 'genre')
            ->orderBy('album.created_at', 'DESC')
            ->addOrderBy('album.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Album[]
     */
    public function findMostReviewed(int $limit = 10): array
    {
        return $this->createQueryBuilder('album')
            ->leftJoin('album.artist', 'artist')
            ->leftJoin('album.genre', 'genre')
            ->innerJoin('album.reviews', 'review', 'WITH', 'review.deleted_at IS NULL')
            ->addSelect('artist', 'genre')
            ->groupBy('album.id, artist.id, genre.id')
            ->orderBy('COUNT(review.id)', 'DESC')
            ->addOrderBy('album.created_at', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

//    /**
//     * @return Album[] Returns an array of Album objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('a')
//            ->andWhere('a.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('a.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Album
//    {
//        return $this->createQueryBuilder('a')
//            ->andWhere('a.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
