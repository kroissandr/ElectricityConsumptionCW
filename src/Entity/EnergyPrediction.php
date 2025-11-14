<?php
// src/Entity/EnergyPrediction.php

namespace App\Entity;

use App\Repository\EnergyPredictionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EnergyPredictionRepository::class)]
class EnergyPrediction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::FLOAT)]
    private ?float $area = null;

    #[ORM\Column]
    private ?int $residents = null;

    #[ORM\Column(length: 10)]
    private ?string $season = null;

    #[ORM\Column(type: Types::FLOAT)]
    private ?float $temperature = null;

    #[ORM\Column(type: Types::FLOAT)]
    private ?float $predictedConsumption = null; // прогнозируемое потребление

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $confidence = null; // точность прогноза (если доступно)

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $predictedAt = null;

    #[ORM\Column(length: 50)]
    private ?string $modelUsed = null; // какая модель использовалась

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $inputData = null; // исходные данные в JSON

    public function __construct()
    {
        $this->predictedAt = new \DateTime();
    }

    // Геттеры и сеттеры
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getArea(): ?float
    {
        return $this->area;
    }

    public function setArea(float $area): static
    {
        $this->area = $area;
        return $this;
    }

    public function getResidents(): ?int
    {
        return $this->residents;
    }

    public function setResidents(int $residents): static
    {
        $this->residents = $residents;
        return $this;
    }

    public function getSeason(): ?string
    {
        return $this->season;
    }

    public function setSeason(string $season): static
    {
        $this->season = $season;
        return $this;
    }

    public function getTemperature(): ?float
    {
        return $this->temperature;
    }

    public function setTemperature(float $temperature): static
    {
        $this->temperature = $temperature;
        return $this;
    }

    public function getPredictedConsumption(): ?float
    {
        return $this->predictedConsumption;
    }

    public function setPredictedConsumption(float $predictedConsumption): static
    {
        $this->predictedConsumption = $predictedConsumption;
        return $this;
    }

    public function getConfidence(): ?float
    {
        return $this->confidence;
    }

    public function setConfidence(?float $confidence): static
    {
        $this->confidence = $confidence;
        return $this;
    }

    public function getPredictedAt(): ?\DateTimeInterface
    {
        return $this->predictedAt;
    }

    public function setPredictedAt(\DateTimeInterface $predictedAt): static
    {
        $this->predictedAt = $predictedAt;
        return $this;
    }

    public function getModelUsed(): ?string
    {
        return $this->modelUsed;
    }

    public function setModelUsed(string $modelUsed): static
    {
        $this->modelUsed = $modelUsed;
        return $this;
    }

    public function getInputData(): ?string
    {
        return $this->inputData;
    }

    public function setInputData(?string $inputData): static
    {
        $this->inputData = $inputData;
        return $this;
    }
}
