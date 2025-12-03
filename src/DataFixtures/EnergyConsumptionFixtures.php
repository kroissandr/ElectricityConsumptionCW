<?php
// src/DataFixtures/EnergyConsumptionFixtures.php

namespace App\DataFixtures;

use App\Entity\EnergyConsumption;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class EnergyConsumptionFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        echo "📊 Загрузка демо-данных потребления...\n";

        $seasons = [
            'winter' => ['temp_min' => -10, 'temp_max' => 5, 'multiplier' => 1.4],
            'spring' => ['temp_min' => 5, 'temp_max' => 18, 'multiplier' => 1.0],
            'summer' => ['temp_min' => 18, 'temp_max' => 35, 'multiplier' => 1.2],
            'autumn' => ['temp_min' => 5, 'temp_max' => 15, 'multiplier' => 1.1],
        ];

        // Генерация 50 демо-записей
        for ($i = 1; $i <= 50; $i++) {
            $season = array_rand($seasons);
            $seasonData = $seasons[$season];

            $area = rand(30, 200);
            $residents = rand(1, 6);
            $temperature = rand($seasonData['temp_min'], $seasonData['temp_max']);

            // Расчет потребления: базовая формула + сезонный коэффициент
            $baseConsumption = $area * 0.5 + $residents * 100;
            $consumption = $baseConsumption * $seasonData['multiplier'] * (0.8 + (rand(0, 40) / 100));

            $energy = new EnergyConsumption();
            $energy->setArea($area);
            $energy->setResidents($residents);
            $energy->setSeason($season);
            $energy->setTemperature($temperature);
            $energy->setConsumption(round($consumption, 2));

            // Добавляем заметки к некоторым записям
            if ($i % 4 == 0) {
                $notes = [
                    'Был в отпуске',
                    'Работал обогреватель',
                    'Использовался кондиционер',
                    'Приезжали гости',
                    'Энергосберегающий режим',
                    'Ремонтные работы'
                ];
                $energy->setNotes($notes[array_rand($notes)]);
            }

            // Устанавливаем случайную дату за последний год
            $randomDays = rand(0, 365);
            $createdAt = new \DateTime();
            $createdAt->modify("-$randomDays days");
            $energy->setCreatedAt($createdAt);

            $manager->persist($energy);

            if ($i % 10 == 0) {
                echo "✅ Создано $i записей...\n";
            }
        }

        // Добавляем несколько специальных случаев
        $specialCases = [
            // Минимальное потребление
            [
                'area' => 35,
                'residents' => 1,
                'season' => 'spring',
                'temperature' => 15,
                'consumption' => 85.5,
                'notes' => 'Минимальное потребление: маленькая квартира'
            ],
            // Максимальное потребление
            [
                'area' => 195,
                'residents' => 6,
                'season' => 'winter',
                'temperature' => -8,
                'consumption' => 992.7,
                'notes' => 'Максимальное потребление: большой дом зимой'
            ],
            // Типичная семья
            [
                'area' => 85,
                'residents' => 3,
                'season' => 'autumn',
                'temperature' => 10,
                'consumption' => 325.8,
                'notes' => 'Семья из 3 человек, типичное потребление'
            ],
            // Летнее с кондиционером
            [
                'area' => 120,
                'residents' => 4,
                'season' => 'summer',
                'temperature' => 32,
                'consumption' => 580.3,
                'notes' => 'Жаркое лето, постоянная работа кондиционера'
            ]
        ];

        foreach ($specialCases as $case) {
            $energy = new EnergyConsumption();
            $energy->setArea($case['area']);
            $energy->setResidents($case['residents']);
            $energy->setSeason($case['season']);
            $energy->setTemperature($case['temperature']);
            $energy->setConsumption($case['consumption']);
            $energy->setNotes($case['notes']);
            $energy->setCreatedAt(new \DateTime());

            $manager->persist($energy);
        }

        $manager->flush();

        echo "🎉 Успешно загружено " . (50 + count($specialCases)) . " записей потребления!\n";
        echo "📈 Статистика:\n";
        echo "   - Общее количество: " . (50 + count($specialCases)) . " записей\n";
        echo "   - Диапазон площади: 35-195 м²\n";
        echo "   - Жильцы: 1-6 человек\n";
        echo "   - Температура: от -10°C до +35°C\n";
        echo "   - Потребление: от 85.5 до 992.7 кВт·ч\n";
    }
}
