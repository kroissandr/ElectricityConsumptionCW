<?php
// src/Controller/EnergyController.php

namespace App\Controller;

use App\Form\PredictionWithModelType;
use App\Service\EnergyDataService;
use App\Service\EnergyPredictionService;
use App\Form\EnergyConsumptionType;
use App\Form\PredictionType;
use App\Entity\EnergyConsumption;
use App\Repository\EnergyConsumptionRepository;
use App\Repository\EnergyPredictionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class EnergyController extends AbstractController
{
    #[Route('/', name: 'energy_index')]
    public function index(
        EnergyConsumptionRepository $consumptionRepository,
        EnergyPredictionRepository $predictionRepository,
        EnergyPredictionService $predictionService
    ): Response {
        $trainingStats = $predictionService->getTrainingDataStats();
        $modelsStatus = $predictionService->areModelsTrained();

        $recentConsumptions = $consumptionRepository->findRecentConsumptions(5);
        $recentPredictions = $predictionRepository->findRecentPredictions(5);

        return $this->render('energy/index.html.twig', [
            'recent_consumptions' => $recentConsumptions,
            'recent_predictions' => $recentPredictions,
            'training_stats' => $trainingStats,
            'models_trained' => $modelsStatus,
        ]);
    }

    #[Route('/energy/history', name: 'energy_history')]
    public function history(EnergyConsumptionRepository $consumptionRepository): Response
    {
        $consumptions = $consumptionRepository->findAllOrderedByDate();
        $stats = $consumptionRepository->getConsumptionStats();

        return $this->render('energy/history.html.twig', [
            'consumptions' => $consumptions,
            'stats' => $stats,
        ]);
    }

    #[Route('/energy/add', name: 'energy_add')]
    public function add(
        Request $request,
        EnergyDataService $dataService
    ): Response {
        $energyConsumption = new EnergyConsumption();
        $form = $this->createForm(EnergyConsumptionType::class, $energyConsumption);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $dataService->createConsumptionRecord([
                    'area' => $energyConsumption->getArea(),
                    'residents' => $energyConsumption->getResidents(),
                    'season' => $energyConsumption->getSeason(),
                    'temperature' => $energyConsumption->getTemperature(),
                    'consumption' => $energyConsumption->getConsumption(),
                    'notes' => $energyConsumption->getNotes(),
                ]);

                $this->addFlash('success', 'Запись о потреблении энергии успешно добавлена!');

                return $this->redirectToRoute('energy_history');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Ошибка при добавлении записи: ' . $e->getMessage());
            }
        }

        return $this->render('energy/add.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/energy/predict', name: 'energy_predict')]
    public function predict(
        Request $request,
        EnergyPredictionService $predictionService
    ): Response {
        // Проверяем, обучены ли модели
        $modelsTrained = $predictionService->areModelsTrained();

        if (!$modelsTrained['linear_regression'] && !$modelsTrained['support_vector_regression']) {
            $this->addFlash('warning', 'Модели машинного обучения не обучены. Пожалуйста, сначала обучите модель.');
            return $this->redirectToRoute('energy_train');
        }

        $form = $this->createForm(PredictionWithModelType::class);
        $prediction = null;
        $selectedModel = 'linear_regression';

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $selectedModel = $data['model_type'] ?? 'auto';

            try {
                // Определяем какую модель использовать
                if ($selectedModel === 'auto') {
                    // Автовыбор лучшей модели
                    $selectedModel = $predictionService->selectBestModel();
                }

                // Выполняем прогноз с выбранной моделью
                if ($selectedModel === 'support_vector_regression' && $modelsTrained['support_vector_regression']) {
                    $prediction = $predictionService->predictWithSVR([
                        'area' => $data['area'],
                        'residents' => $data['residents'],
                        'season' => $data['season'],
                        'temperature' => $data['temperature'],
                    ]);
                } elseif ($selectedModel === 'linear_regression' && $modelsTrained['linear_regression']) {
                    $prediction = $predictionService->predictConsumption([
                        'area' => $data['area'],
                        'residents' => $data['residents'],
                        'season' => $data['season'],
                        'temperature' => $data['temperature'],
                    ]);
                } else {
                    // Если выбранная модель не обучена, используем доступную
                    if ($modelsTrained['linear_regression']) {
                        $prediction = $predictionService->predictConsumption($data);
                        $selectedModel = 'linear_regression';
                    } elseif ($modelsTrained['support_vector_regression']) {
                        $prediction = $predictionService->predictWithSVR($data);
                        $selectedModel = 'support_vector_regression';
                    }
                }

                $this->addFlash('success', 'Прогноз успешно выполнен!');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Ошибка при прогнозировании: ' . $e->getMessage());
            }
        }

        return $this->render('energy/predict.html.twig', [
            'form' => $form->createView(),
            'prediction' => $prediction,
            'models_trained' => $modelsTrained,
            'selected_model' => $selectedModel ?? 'linear_regression'
        ]);
    }

    #[Route('/energy/train', name: 'energy_train')]
    public function trainModels(
        Request $request,
        EnergyPredictionService $predictionService,
        EnergyDataService $dataService
    ): Response {
        $trainingStats = $predictionService->getTrainingDataStats();
        $modelsTrained = $predictionService->areModelsTrained();

        $linearResult = null;
        $svrResult = null;
        $bestModelResult = null;

        if ($request->isMethod('POST')) {
            $action = $request->request->get('action');

            try {
                switch ($action) {
                    case 'train_linear':
                        $linearResult = $predictionService->trainLinearRegression();
                        $this->addFlash('success', 'Линейная регрессия успешно обучена!');
                        break;

                    case 'train_svr':
                        $svrResult = $predictionService->trainSVR();
                        $this->addFlash('success', 'Support Vector Regression успешно обучена!');
                        break;

                    case 'train_best':
                        $bestModelResult = $predictionService->trainBestModel();
                        $modelName = $bestModelResult['model'] === 'linear_regression' ? 'Линейная регрессия' : 'Support Vector Regression';
                        $this->addFlash('success', "Автоматически выбрана и обучена лучшая модель: $modelName");
                        break;

                    case 'generate_demo_data':
                        $demoData = $dataService->generateDemoData();
                        foreach ($demoData as $data) {
                            $dataService->createConsumptionRecord($data);
                        }
                        $this->addFlash('success', 'Демо-данные успешно сгенерированы!');
                        break;
                }

                // Обновляем статус после обучения
                $modelsTrained = $predictionService->areModelsTrained();
                $trainingStats = $predictionService->getTrainingDataStats();

            } catch (\Exception $e) {
                $this->addFlash('error', 'Ошибка: ' . $e->getMessage());
            }
        }

        // Определяем рекомендованную модель
        $recommendedModel = $predictionService->selectBestModel();

        return $this->render('energy/train.html.twig', [
            'training_stats' => $trainingStats,
            'models_trained' => $modelsTrained,
            'linear_result' => $linearResult,
            'svr_result' => $svrResult,
            'best_model_result' => $bestModelResult,
            'recommended_model' => $recommendedModel
        ]);
    }

    #[Route('/energy/stats', name: 'energy_stats')]
    public function stats(
        EnergyConsumptionRepository $consumptionRepository,
        EnergyPredictionRepository $predictionRepository
    ): Response {
        $consumptionStats = $consumptionRepository->getConsumptionStats();
        $predictionStats = $predictionRepository->getAccuracyStats();

        return $this->render('energy/stats.html.twig', [
            'consumption_stats' => $consumptionStats,
            'prediction_stats' => $predictionStats,
        ]);
    }
}
