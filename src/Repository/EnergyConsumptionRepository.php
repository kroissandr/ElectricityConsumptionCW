<?php
// src/Repository/EnergyConsumptionRepository.php

namespace App\Repository;

use App\Entity\EnergyConsumption;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EnergyConsumption>
 */
class EnergyConsumptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EnergyConsumption::class);
    }

    /**
     * Сохраняет запись потребления энергии
     */
    public function save(EnergyConsumption $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Удаляет запись потребления энергии
     */
    public function remove(EnergyConsumption $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Находит все записи, отсортированные по дате создания
     */
    public function findAllOrderedByDate(): array
    {
        return $this->createQueryBuilder('e')
            ->orderBy('e.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Находит записи по сезону
     */
    public function findBySeason(string $season): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.season = :season')
            ->setParameter('season', $season)
            ->orderBy('e.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Получает данные для обучения ML модели
     * Возвращает массив с признаками и целевой переменной
     */
    public function findTrainingData(): array
    {
        $data = $this->createQueryBuilder('e')
            ->select('e.area', 'e.residents', 'e.season', 'e.temperature', 'e.consumption')
            ->getQuery()
            ->getArrayResult();

        $trainingData = [];
        foreach ($data as $record) {
            // Преобразуем сезон в числовое значение
            $seasonValue = $this->seasonToNumber($record['season']);

            $trainingData[] = [
                'features' => [
                    $record['area'],
                    $record['residents'],
                    $seasonValue,
                    $record['temperature']
                ],
                'target' => $record['consumption']
            ];
        }

        return $trainingData;
    }

    /**
     * Получает статистику по потреблению
     */
    public function getConsumptionStats(): array
    {
        return $this->createQueryBuilder('e')
            ->select([
                'AVG(e.consumption) as avg_consumption',
                'MIN(e.consumption) as min_consumption',
                'MAX(e.consumption) as max_consumption',
                'COUNT(e.id) as total_records',
                'e.season'
            ])
            ->groupBy('e.season')
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * Преобразует сезон в числовое значение для ML
     */
    private function seasonToNumber(string $season): int
    {
        return match($season) {
            'winter' => 0,
            'spring' => 1,
            'summer' => 2,
            'autumn' => 3,
            default => 0
        };
    }

    /**
     * Находит записи в диапазоне дат
     */
    public function findByDateRange(\DateTime $startDate, \DateTime $endDate): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.createdAt BETWEEN :start AND :end')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->orderBy('e.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findRecentConsumptions(int $limit = 10): array
    {
        return $this->createQueryBuilder('e')
            ->orderBy('e.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
