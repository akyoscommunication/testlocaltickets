<?php

namespace App\Entity;

use App\Repository\AgentRunRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: AgentRunRepository::class)]
class AgentRun
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(inversedBy: 'agentRuns')]
    #[ORM\JoinColumn(nullable: false)]
    private TicketProcessing $ticketProcessing;

    #[ORM\Column(length: 32)]
    private string $runId;

    #[ORM\Column(length: 32)]
    private string $phase;

    #[ORM\Column(length: 64)]
    private string $templateKey;

    #[ORM\Column]
    private int $templateVersion;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $renderedPromptPath = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $requestPath = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $responsePath = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $contextPackPath = null;

    #[ORM\Column(length: 32)]
    private string $status = 'pending';

    #[ORM\Column(nullable: true)]
    private ?int $durationMs = null;

    #[ORM\Column]
    private \DateTimeImmutable $startedAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $finishedAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $errorMessage = null;

    public function __construct(TicketProcessing $ticketProcessing, string $runId, string $phase, string $templateKey, int $templateVersion)
    {
        $this->ticketProcessing = $ticketProcessing;
        $this->runId = $runId;
        $this->phase = $phase;
        $this->templateKey = $templateKey;
        $this->templateVersion = $templateVersion;
        $this->startedAt = new \DateTimeImmutable();
        $ticketProcessing->addAgentRun($this);
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getTicketProcessing(): TicketProcessing
    {
        return $this->ticketProcessing;
    }

    public function setTicketProcessing(TicketProcessing $ticketProcessing): static
    {
        $this->ticketProcessing = $ticketProcessing;

        return $this;
    }

    public function getRunId(): string
    {
        return $this->runId;
    }

    public function getPhase(): string
    {
        return $this->phase;
    }

    public function getTemplateKey(): string
    {
        return $this->templateKey;
    }

    public function getTemplateVersion(): int
    {
        return $this->templateVersion;
    }

    public function getRenderedPromptPath(): ?string
    {
        return $this->renderedPromptPath;
    }

    public function setRenderedPromptPath(?string $renderedPromptPath): static
    {
        $this->renderedPromptPath = $renderedPromptPath;

        return $this;
    }

    public function getRequestPath(): ?string
    {
        return $this->requestPath;
    }

    public function setRequestPath(?string $requestPath): static
    {
        $this->requestPath = $requestPath;

        return $this;
    }

    public function getResponsePath(): ?string
    {
        return $this->responsePath;
    }

    public function setResponsePath(?string $responsePath): static
    {
        $this->responsePath = $responsePath;

        return $this;
    }

    public function getContextPackPath(): ?string
    {
        return $this->contextPackPath;
    }

    public function setContextPackPath(?string $contextPackPath): static
    {
        $this->contextPackPath = $contextPackPath;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getDurationMs(): ?int
    {
        return $this->durationMs;
    }

    public function setDurationMs(?int $durationMs): static
    {
        $this->durationMs = $durationMs;

        return $this;
    }

    public function getStartedAt(): \DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function getFinishedAt(): ?\DateTimeImmutable
    {
        return $this->finishedAt;
    }

    public function setFinishedAt(?\DateTimeImmutable $finishedAt): static
    {
        $this->finishedAt = $finishedAt;

        return $this;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $errorMessage): static
    {
        $this->errorMessage = $errorMessage;

        return $this;
    }
}
