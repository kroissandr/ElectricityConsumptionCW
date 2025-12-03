<?php
// src/Controller/PredictionController.php

namespace App\Controller;

use App\Service\EnergyPredictionService;
use App\Repository\EnergyPredictionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/predictions')]
class PredictionController extends AbstractController
{
    #[Route('/predict', name: 'api_energy_predict', methods: ['POST'])]
    public function predict(
        Request $request,
        EnergyPredictionService $predictionService
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        // Валидация обязательных полей
        $requiredFields = ['area', 'residents', 'season', 'temperature'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                return $this->json([
                    'error' => "Missing required field: $field"
                ], 400);
            }
        }

        try {
            $modelType = $data['model_type'] ?? 'linear_regression';
            $prediction = $predictionService->predictConsumption($data, $modelType);

            return $this->json([
                'success' => true,
                'prediction' => [
                    'id' => $prediction->getId(),
                    'predicted_consumption' => $prediction->getPredictedConsumption(),
                    'confidence' => $prediction->getConfidence(),
                    'model_used' => $prediction->getModelUsed(),
                    'predicted_at' => $prediction->getPredictedAt()->format('Y-m-d H:i:s'),
                ],
                'input_data' => [
                    'area' => $prediction->getArea(),
                    'residents' => $prediction->getResidents(),
                    'season' => $prediction->getSeason(),
                    'temperature' => $prediction->getTemperature(),
                ]
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/history', name: 'api_predictions_history', methods: ['GET'])]
    public function predictionHistory(
        EnergyPredictionRepository $predictionRepository,
        Request $request
    ): JsonResponse {
        $limit = $request->query->getInt('limit', 10);
        $model = $request->query->get('model');

        $predictions = $model
            ? $predictionRepository->findByModel($model)
            : $predictionRepository->findRecentPredictions($limit);

        $data = [];
        foreach ($predictions as $prediction) {
            $data[] = [
                'id' => $prediction->getId(),
                'area' => $prediction->getArea(),
                'residents' => $prediction->getResidents(),
                'season' => $prediction->getSeason(),
                'temperature' => $prediction->getTemperature(),
                'predicted_consumption' => $prediction->getPredictedConsumption(),
                'confidence' => $prediction->getConfidence(),
                'model_used' => $prediction->getModelUsed(),
                'predicted_at' => $prediction->getPredictedAt()->format('Y-m-d H:i:s'),
            ];
        }

        return $this->json([
            'success' => true,
            'predictions' => $data,
            'total' => count($data)
        ]);
    }

    #[Route('/models/status', name: 'api_models_status', methods: ['GET'])]
    public function modelsStatus(EnergyPredictionService $predictionService): JsonResponse
    {
        $modelsTrained = $predictionService->areModelsTrained();
        $trainingStats = $predictionService->getTrainingDataStats();

        return $this->json([
            'success' => true,
            'models_trained' => $modelsTrained,
            'training_data' => $trainingStats
        ]);
    }

    #[Route('/models/train/{modelType}', name: 'api_train_model', methods: ['POST'])]
    public function trainModel(
        string $modelType,
        EnergyPredictionService $predictionService
    ): JsonResponse {
        try {
            switch ($modelType) {
                case 'linear_regression':
                    $result = $predictionService->trainLinearRegression();
                    break;
                case 'random_forest':
                    $result = $predictionService->trainRandomForest();
                    break;
                default:
                    return $this->json([
                        'success' => false,
                        'error' => "Unknown model type: $modelType"
                    ], 400);
            }

            return $this->json([
                'success' => true,
                'model' => $result['model'],
                'accuracy' => $result['accuracy'],
                'training_samples' => $result['training_samples'],
                'test_samples' => $result['test_samples']
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/models/train/svr', name: 'api_train_svr', methods: ['POST'])]
    public function trainSVR(
        EnergyPredictionService $predictionService
    ): JsonResponse {
        try {
            $result = $predictionService->trainSVR();

            return $this->json([
                'success' => true,
                'model' => $result['model'],
                'accuracy' => $result['accuracy'],
                'kernel' => $result['kernel'] ?? 'RBF',
                'parameters' => $result['parameters'] ?? [],
                'training_samples' => $result['training_samples'],
                'test_samples' => $result['test_samples'],
                'mean_absolute_error' => $result['mean_absolute_error'],
                'root_mean_squared_error' => $result['root_mean_squared_error']
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/predict/svr', name: 'api_energy_predict_svr', methods: ['POST'])]
    public function predictWithSVR(
        Request $request,
        EnergyPredictionService $predictionService
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        // Валидация обязательных полей
        $requiredFields = ['area', 'residents', 'season', 'temperature'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                return $this->json([
                    'error' => "Missing required field: $field"
                ], 400);
            }
        }

        try {
            $prediction = $predictionService->predictWithSVR($data);

            return $this->json([
                'success' => true,
                'model' => 'support_vector_regression',
                'prediction' => [
                    'id' => $prediction->getId(),
                    'predicted_consumption' => $prediction->getPredictedConsumption(),
                    'confidence' => $prediction->getConfidence(),
                    'model_used' => $prediction->getModelUsed(),
                    'predicted_at' => $prediction->getPredictedAt()->format('Y-m-d H:i:s'),
                ],
                'input_data' => [
                    'area' => $prediction->getArea(),
                    'residents' => $prediction->getResidents(),
                    'season' => $prediction->getSeason(),
                    'temperature' => $prediction->getTemperature(),
                ]
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/models/compare', name: 'api_compare_models', methods: ['GET'])]
    public function compareModels(
        EnergyPredictionService $predictionService
    ): JsonResponse {
        $modelsTrained = $predictionService->areModelsTrained();
        $trainingStats = $predictionService->getTrainingDataStats();

        return $this->json([
            'success' => true,
            'models_available' => [
                'linear_regression' => [
                    'trained' => $modelsTrained['linear_regression'],
                    'description' => 'Линейная регрессия - быстрая, интерпретируемая модель для линейных зависимостей',
                    'best_for' => 'Небольшие линейные наборы данных',
                    'complexity' => 'Низкая'
                ],
                'support_vector_regression' => [
                    'trained' => $modelsTrained['support_vector_regression'],
                    'description' => 'Метод опорных векторов (SVR) - мощная модель для нелинейных зависимостей',
                    'best_for' => 'Нелинейные данные, сложные зависимости',
                    'complexity' => 'Высокая',
                    'kernel' => 'RBF (Радиально-базисная функция)'
                ]
            ],
            'training_data' => $trainingStats,
            'recommended_model' => $predictionService->selectBestModel()
        ]);
    }
}
