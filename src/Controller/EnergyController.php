<?php
// src/Controller/EnergyController.php

namespace App\Controller;

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
        // Проверяем, обучена ли модель
        $modelsTrained = $predictionService->areModelsTrained();

        if (!$modelsTrained['linear_regression']) {
            $this->addFlash('warning', 'Модель машинного обучения не обучена. Пожалуйста, сначала обучите модель.');
            return $this->redirectToRoute('energy_train');
        }

        $form = $this->createForm(PredictionType::class);
        $prediction = null;

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            try {
                $prediction = $predictionService->predictConsumption([
                    'area' => $data['area'],
                    'residents' => $data['residents'],
                    'season' => $data['season'],
                    'temperature' => $data['temperature'],
                ]);

                $this->addFlash('success', 'Прогноз успешно выполнен!');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Ошибка при прогнозировании: ' . $e->getMessage());
            }
        }

        return $this->render('energy/predict.html.twig', [
            'form' => $form->createView(),
            'prediction' => $prediction,
            'models_trained' => $modelsTrained,
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
        $randomForestResult = null;

        if ($request->isMethod('POST')) {
            $action = $request->request->get('action');

            try {
                switch ($action) {
                    case 'train_linear':
                        $linearResult = $predictionService->trainLinearRegression();
                        $this->addFlash('success', 'Линейная регрессия успешно обучена!');
                        break;

                    case 'train_random_forest':
                        $randomForestResult = $predictionService->trainRandomForest();
                        $this->addFlash('success', 'Random Forest успешно обучен!');
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

        return $this->render('energy/train.html.twig', [
            'training_stats' => $trainingStats,
            'models_trained' => $modelsTrained,
            'linear_result' => $linearResult,
            'random_forest_result' => $randomForestResult,
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
