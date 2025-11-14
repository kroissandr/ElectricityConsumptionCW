<?php
// src/Repository/EnergyPredictionRepository.php

namespace App\Repository;

use App\Entity\EnergyPrediction;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EnergyPrediction>
 */
class EnergyPredictionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EnergyPrediction::class);
    }

    /**
     * Сохраняет прогноз
     */
    public function save(EnergyPrediction $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Удаляет прогноз
     */
    public function remove(EnergyPrediction $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Находит все прогнозы, отсортированные по дате
     */
    public function findAllOrderedByDate(): array
    {
        return $this->createQueryBuilder('p')
            ->orderBy('p.predictedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Находит прогнозы по использованной модели
     */
    public function findByModel(string $model): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.modelUsed = :model')
            ->setParameter('model', $model)
            ->orderBy('p.predictedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Находит прогнозы по сезону
     */
    public function findBySeason(string $season): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.season = :season')
            ->setParameter('season', $season)
            ->orderBy('p.predictedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Получает статистику точности прогнозов
     */
    public function getAccuracyStats(): array
    {
        return $this->createQueryBuilder('p')
            ->select([
                'p.modelUsed',
                'AVG(p.confidence) as avg_confidence',
                'COUNT(p.id) as total_predictions',
                'MIN(p.predictedAt) as first_prediction',
                'MAX(p.predictedAt) as last_prediction'
            ])
            ->groupBy('p.modelUsed')
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * Находит последние N прогнозов
     */
    public function findRecentPredictions(int $limit = 10): array
    {
        return $this->createQueryBuilder('p')
            ->orderBy('p.predictedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Очищает старые прогнозы (например, старше 30 дней)
     */
    public function cleanupOldPredictions(int $days = 30): int
    {
        $date = new \DateTime("-$days days");

        return $this->createQueryBuilder('p')
            ->delete()
            ->where('p.predictedAt < :date')
            ->setParameter('date', $date)
            ->getQuery()
            ->execute();
    }
}
