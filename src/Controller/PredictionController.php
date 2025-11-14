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
}
