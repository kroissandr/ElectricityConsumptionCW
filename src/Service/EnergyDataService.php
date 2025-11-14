<?php
// src/Service/EnergyDataService.php

namespace App\Service;

use App\Entity\EnergyConsumption;
use App\Repository\EnergyConsumptionRepository;

class EnergyDataService
{
    private EnergyConsumptionRepository $consumptionRepository;

    public function __construct(EnergyConsumptionRepository $consumptionRepository)
    {
        $this->consumptionRepository = $consumptionRepository;
    }

    /**
     * Создает новую запись о потреблении
     */
    public function createConsumptionRecord(array $data): EnergyConsumption
    {
        $consumption = new EnergyConsumption();
        $consumption->setArea($data['area']);
        $consumption->setResidents($data['residents']);
        $consumption->setSeason($data['season']);
        $consumption->setTemperature($data['temperature']);
        $consumption->setConsumption($data['consumption']);
        $consumption->setNotes($data['notes'] ?? null);

        $this->consumptionRepository->save($consumption, true);

        return $consumption;
    }

    /**
     * Валидирует данные потребления
     */
    public function validateConsumptionData(array $data): array
    {
        $errors = [];

        if (!isset($data['area']) || $data['area'] <= 0) {
            $errors[] = 'Area must be a positive number';
        }

        if (!isset($data['residents']) || $data['residents'] <= 0) {
            $errors[] = 'Number of residents must be a positive integer';
        }

        if (!isset($data['season']) || !in_array($data['season'], ['winter', 'spring', 'summer', 'autumn'])) {
            $errors[] = 'Season must be one of: winter, spring, summer, autumn';
        }

        if (!isset($data['temperature']) || $data['temperature'] < -50 || $data['temperature'] > 50) {
            $errors[] = 'Temperature must be between -50 and 50 degrees';
        }

        if (!isset($data['consumption']) || $data['consumption'] <= 0) {
            $errors[] = 'Consumption must be a positive number';
        }

        return $errors;
    }

    /**
     * Генерирует демо-данные для тестирования
     */
    public function generateDemoData(): array
    {
        $demoData = [];

        $seasons = ['winter', 'spring', 'summer', 'autumn'];

        for ($i = 0; $i < 50; $i++) {
            $area = rand(30, 200);
            $residents = rand(1, 6);
            $season = $seasons[array_rand($seasons)];

            // Температура в зависимости от сезона
            $temperature = match($season) {
                'winter' => rand(-10, 5),
                'spring' => rand(5, 18),
                'summer' => rand(18, 35),
                'autumn' => rand(5, 15),
                default => 15
            };

            // Базовое потребление + вариации
            $baseConsumption = $area * 0.5 + $residents * 100;
            $seasonMultiplier = match($season) {
                'winter' => 1.4, // больше отопления
                'summer' => 1.2, // кондиционеры
                default => 1.0
            };

            $consumption = $baseConsumption * $seasonMultiplier * (0.8 + (mt_rand(0, 40) / 100));

            $demoData[] = [
                'area' => $area,
                'residents' => $residents,
                'season' => $season,
                'temperature' => $temperature,
                'consumption' => round($consumption, 2),
                'notes' => 'Demo data'
            ];
        }

        return $demoData;
    }
}
