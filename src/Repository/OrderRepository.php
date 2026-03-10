<?php

namespace App\Repository;

use App\Entity\Order;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Order>
 */
class OrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Order::class);
    }

    /**
     * @return Order[] Returns an array of Order objects sorted by date
     */
    public function findAllSortedByDate(string $sort = 'DESC'): array
    {
        return $this->createQueryBuilder('o')
            ->orderBy('o.orderDate', $sort)
            ->getQuery()
            ->getResult();
    }
}

