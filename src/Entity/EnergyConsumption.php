<?php
// src/Entity/EnergyConsumption.php

namespace App\Entity;

use App\Repository\EnergyConsumptionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EnergyConsumptionRepository::class)]
class EnergyConsumption
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::FLOAT)]
    private ?float $area = null; // площадь помещения в м²

    #[ORM\Column]
    private ?int $residents = null; // количество жильцов

    #[ORM\Column(length: 10)]
    private ?string $season = null; // сезон: winter, spring, summer, autumn

    #[ORM\Column(type: Types::FLOAT)]
    private ?float $temperature = null; // средняя температура

    #[ORM\Column(type: Types::FLOAT)]
    private ?float $consumption = null; // потребление электроэнергии в кВт·ч

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null; // дополнительные заметки

    public function __construct()
    {
        $this->createdAt = new \DateTime();
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

    public function getConsumption(): ?float
    {
        return $this->consumption;
    }

    public function setConsumption(float $consumption): static
    {
        $this->consumption = $consumption;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;
        return $this;
    }
}
