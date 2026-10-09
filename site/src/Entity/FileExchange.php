<?php

namespace App\Entity;

use App\Repository\FileExchangeRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: FileExchangeRepository::class)]
class FileExchange
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\Column(length: 64)]
    private string $ticketNumber;

    #[ORM\Column(length: 512)]
    private string $logicalPath;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $contentHash = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $processedAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $ticketNumber, string $logicalPath)
    {
        $this->ticketNumber = $ticketNumber;
        $this->logicalPath = $logicalPath;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getTicketNumber(): string
    {
        return $this->ticketNumber;
    }

    public function getLogicalPath(): string
    {
        return $this->logicalPath;
    }

    public function getContentHash(): ?string
    {
        return $this->contentHash;
    }

    public function setContentHash(?string $contentHash): static
    {
        $this->contentHash = $contentHash;

        return $this;
    }

    public function getProcessedAt(): ?\DateTimeImmutable
    {
        return $this->processedAt;
    }

    public function markProcessed(): static
    {
        $this->processedAt = new \DateTimeImmutable();

        return $this;
    }
}
