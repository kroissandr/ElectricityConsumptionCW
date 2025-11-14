<?php
// src/Service/EnergyPredictionService.php

namespace App\Service;

use App\Entity\EnergyConsumption;
use App\Entity\EnergyPrediction;
use App\Repository\EnergyConsumptionRepository;
use App\Repository\EnergyPredictionRepository;
use Phpml\Regression\LeastSquares;
use Phpml\Dataset\ArrayDataset;
use Phpml\CrossValidation\RandomSplit;
use Phpml\Metric\Regression;
use Phpml\ModelManager;
use Phpml\Preprocessing\Normalizer;

class EnergyPredictionService
{
    private EnergyConsumptionRepository $consumptionRepository;
    private EnergyPredictionRepository $predictionRepository;
    private string $modelStoragePath;
    private ?LeastSquares $linearModel = null;
    private array $normalizerMeans = [];
    private array $normalizerStddevs = [];

    public function __construct(
        EnergyConsumptionRepository $consumptionRepository,
        EnergyPredictionRepository $predictionRepository
    ) {
        $this->consumptionRepository = $consumptionRepository;
        $this->predictionRepository = $predictionRepository;

        $this->modelStoragePath = dirname(__DIR__, 2) . '/var/ml_models/';

        if (!is_dir($this->modelStoragePath)) {
            mkdir($this->modelStoragePath, 0777, true);
        }
    }

    /**
     * Подготавливает данные для обучения модели
     */
    public function prepareTrainingData(): array
    {
        $trainingData = $this->consumptionRepository->findTrainingData();

        if (empty($trainingData)) {
            throw new \RuntimeException('No training data available. Please add some energy consumption records first.');
        }

        $samples = [];
        $targets = [];

        foreach ($trainingData as $data) {
            $samples[] = $data['features'];
            $targets[] = $data['target'];
        }

        // Нормализуем данные для улучшения производительности
        $this->normalizeSamples($samples);

        return [
            'samples' => $samples,
            'targets' => $targets,
            'count' => count($samples),
        ];
    }

    /**
     * Нормализует образцы данных (Z-score нормализация)
     */
    private function normalizeSamples(array &$samples): void
    {
        if (empty($samples)) {
            return;
        }

        $featureCount = count($samples[0]);
        $this->normalizerMeans = [];
        $this->normalizerStddevs = [];

        // Вычисляем средние и стандартные отклонения для каждого признака
        for ($i = 0; $i < $featureCount; $i++) {
            $values = array_column($samples, $i);
            $this->normalizerMeans[$i] = array_sum($values) / count($values);

            $variance = 0.0;
            foreach ($values as $value) {
                $variance += pow($value - $this->normalizerMeans[$i], 2);
            }
            $this->normalizerStddevs[$i] = sqrt($variance / count($values));

            // Избегаем деления на ноль
            if ($this->normalizerStddevs[$i] == 0) {
                $this->normalizerStddevs[$i] = 1.0;
            }
        }

        // Применяем нормализацию
        foreach ($samples as &$sample) {
            for ($i = 0; $i < $featureCount; $i++) {
                $sample[$i] = ($sample[$i] - $this->normalizerMeans[$i]) / $this->normalizerStddevs[$i];
            }
        }
    }

    /**
     * Нормализует один образец данных
     */
    private function normalizeSample(array $sample): array
    {
        $normalized = [];
        foreach ($sample as $i => $value) {
            if (isset($this->normalizerMeans[$i]) && isset($this->normalizerStddevs[$i])) {
                $normalized[] = ($value - $this->normalizerMeans[$i]) / $this->normalizerStddevs[$i];
            } else {
                $normalized[] = $value;
            }
        }
        return $normalized;
    }

    /**
     * Обучает линейную регрессионную модель
     */
    public function trainLinearRegression(): array
    {
        $data = $this->prepareTrainingData();
        $samples = $data['samples'];
        $targets = $data['targets'];

        // Разделяем данные на тренировочные и тестовые (70% тренировка, 30% тест)
        $dataset = new RandomSplit(new ArrayDataset($samples, $targets), 0.7);

        $this->linearModel = new LeastSquares();
        $this->linearModel->train(
            $dataset->getTrainSamples(),
            $dataset->getTrainLabels()
        );

        // Оцениваем модель на тестовых данных
        $testSamples = $dataset->getTestSamples();
        $testTargets = $dataset->getTestLabels();

        $predictions = $this->linearModel->predict($testSamples);
        $accuracy = $this->calculateAccuracy($testTargets, $predictions);

        // Сохраняем модель и параметры нормализации
        $this->saveModel($this->linearModel, 'linear_regression');
        $this->saveNormalizerParameters();

        return [
            'model' => 'linear_regression',
            'accuracy' => $accuracy,
            'training_samples' => count($dataset->getTrainSamples()),
            'test_samples' => count($testSamples),
            'features' => ['area', 'residents', 'season', 'temperature'],
            'mean_absolute_error' => $this->calculateMAE($testTargets, $predictions),
            'root_mean_squared_error' => $this->calculateRMSE($testTargets, $predictions)
        ];
    }

    /**
     * Прогнозирует потребление электроэнергии
     */
    public function predictConsumption(array $inputData): EnergyPrediction
    {
        // Преобразуем входные данные
        $features = $this->prepareFeatures($inputData);

        // Загружаем модель и параметры нормализации
        $model = $this->loadModel('linear_regression');
        $this->loadNormalizerParameters();

        if (!$model) {
            throw new \RuntimeException("Model not found. Please train the model first.");
        }

        // Нормализуем входные данные
        $normalizedFeatures = $this->normalizeSample($features);

        // Делаем прогноз
        $predictionValue = $model->predict([$normalizedFeatures])[0];

        // Денормализуем результат (если нужно) - в данном случае оставляем как есть
        $finalPrediction = max(0, $predictionValue); // Потребление не может быть отрицательным

        // Создаем и сохраняем объект прогноза
        $energyPrediction = new EnergyPrediction();
        $energyPrediction->setArea($inputData['area']);
        $energyPrediction->setResidents($inputData['residents']);
        $energyPrediction->setSeason($inputData['season']);
        $energyPrediction->setTemperature($inputData['temperature']);
        $energyPrediction->setPredictedConsumption(round($finalPrediction, 2));
        $energyPrediction->setModelUsed('linear_regression');
        $energyPrediction->setInputData(json_encode($inputData));
        $energyPrediction->setConfidence($this->calculatePredictionConfidence($finalPrediction));

        $this->predictionRepository->save($energyPrediction, true);

        return $energyPrediction;
    }

    /**
     * Подготавливает признаки для модели
     */
    private function prepareFeatures(array $inputData): array
    {
        $seasonMapping = [
            'winter' => 0,
            'spring' => 1,
            'summer' => 2,
            'autumn' => 3
        ];

        return [
            floatval($inputData['area']),
            intval($inputData['residents']),
            $seasonMapping[$inputData['season']] ?? 0,
            floatval($inputData['temperature'])
        ];
    }

    /**
     * Сохраняет параметры нормализации
     */
    private function saveNormalizerParameters(): void
    {
        $normalizerData = [
            'means' => $this->normalizerMeans,
            'stddevs' => $this->normalizerStddevs
        ];

        file_put_contents(
            $this->modelStoragePath . 'normalizer.json',
            json_encode($normalizerData)
        );
    }

    /**
     * Загружает параметры нормализации
     */
    private function loadNormalizerParameters(): void
    {
        $filePath = $this->modelStoragePath . 'normalizer.json';

        if (!file_exists($filePath)) {
            return;
        }

        $normalizerData = json_decode(file_get_contents($filePath), true);

        if ($normalizerData) {
            $this->normalizerMeans = $normalizerData['means'] ?? [];
            $this->normalizerStddevs = $normalizerData['stddevs'] ?? [];
        }
    }

    /**
     * Рассчитывает точность модели (R² score)
     */
    private function calculateAccuracy(array $actual, array $predicted): float
    {
        try {
            return Regression::r2Score($actual, $predicted);
        } catch (\Exception $e) {
            // Если R² не может быть рассчитан, используем альтернативную метрику
            return $this->calculateAlternativeAccuracy($actual, $predicted);
        }
    }

    /**
     * Альтернативная метрика точности
     */
    private function calculateAlternativeAccuracy(array $actual, array $predicted): float
    {
        $n = count($actual);
        $sumActual = array_sum($actual);
        $meanActual = $sumActual / $n;

        $ssTotal = 0;
        $ssResidual = 0;

        for ($i = 0; $i < $n; $i++) {
            $ssTotal += pow($actual[$i] - $meanActual, 2);
            $ssResidual += pow($actual[$i] - $predicted[$i], 2);
        }

        if ($ssTotal == 0) {
            return 1.0; // Perfect fit if all values are the same
        }

        return 1 - ($ssResidual / $ssTotal);
    }

    /**
     * Рассчитывает среднюю абсолютную ошибку (MAE)
     */
    private function calculateMAE(array $actual, array $predicted): float
    {
        $n = count($actual);
        $sum = 0;

        for ($i = 0; $i < $n; $i++) {
            $sum += abs($actual[$i] - $predicted[$i]);
        }

        return $sum / $n;
    }

    /**
     * Рассчитывает среднеквадратичную ошибку (RMSE)
     */
    private function calculateRMSE(array $actual, array $predicted): float
    {
        $n = count($actual);
        $sum = 0;

        for ($i = 0; $i < $n; $i++) {
            $sum += pow($actual[$i] - $predicted[$i], 2);
        }

        return sqrt($sum / $n);
    }

    /**
     * Рассчитывает уверенность прогноза
     */
    private function calculatePredictionConfidence(float $prediction): float
    {
        // Простая эвристика: чем выше прогноз, тем меньше уверенность (относительно)
        $baseConfidence = 0.85;

        if ($prediction > 1000) {
            return max(0.6, $baseConfidence - ($prediction - 1000) / 5000);
        }

        return $baseConfidence;
    }

    /**
     * Сохраняет модель в файл
     */
    private function saveModel($model, string $modelName): void
    {
        $modelManager = new ModelManager();
        $filePath = $this->modelStoragePath . $modelName . '.model';
        $modelManager->saveToFile($model, $filePath);
    }

    /**
     * Загружает модель из файла
     */
    private function loadModel(string $modelName)
    {
        $filePath = $this->modelStoragePath . $modelName . '.model';

        if (!file_exists($filePath)) {
            return null;
        }

        $modelManager = new ModelManager();
        return $modelManager->restoreFromFile($filePath);
    }

    /**
     * Проверяет, обучены ли модели
     */
    public function areModelsTrained(): array
    {
        $linearTrained = file_exists($this->modelStoragePath . 'linear_regression.model');
        $normalizerTrained = file_exists($this->modelStoragePath . 'normalizer.json');

        return [
            'linear_regression' => $linearTrained,
            'normalizer' => $normalizerTrained
        ];
    }

    /**
     * Получает статистику по данным для обучения
     */
    public function getTrainingDataStats(): array
    {
        try {
            $trainingData = $this->prepareTrainingData();
            $stats = $this->consumptionRepository->getConsumptionStats();

            return [
                'total_records' => $trainingData['count'],
                'season_stats' => $stats,
                'features_count' => count($trainingData['samples'][0] ?? []),
                'last_training' => $this->getLastTrainingTime()
            ];
        } catch (\RuntimeException $e) {
            return [
                'total_records' => 0,
                'season_stats' => [],
                'features_count' => 0,
                'last_training' => []
            ];
        }
    }

    /**
     * Получает время последнего обучения моделей
     */
    private function getLastTrainingTime(): array
    {
        $models = ['linear_regression.model', 'normalizer.json'];
        $lastTraining = [];

        foreach ($models as $model) {
            $filePath = $this->modelStoragePath . $model;
            if (file_exists($filePath)) {
                $lastTraining[$model] = filemtime($filePath);
            }
        }

        return $lastTraining;
    }

    /**
     * Очищает все обученные модели
     */
    public function clearModels(): void
    {
        $files = glob($this->modelStoragePath . '*.model');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        $jsonFile = $this->modelStoragePath . 'normalizer.json';
        if (file_exists($jsonFile)) {
            unlink($jsonFile);
        }
    }
}
