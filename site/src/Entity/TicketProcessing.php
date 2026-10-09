<?php

namespace App\Entity;

use App\Repository\TicketProcessingRepository;
use App\Workflow\TicketWorkflow;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: TicketProcessingRepository::class)]
#[ORM\Table(name: 'ticket_processing')]
#[ORM\UniqueConstraint(name: 'uniq_ticket_number', columns: ['ticket_number'])]
class TicketProcessing
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\Column(length: 64)]
    private string $ticketNumber;

    #[ORM\Column(length: 32)]
    private string $inputSource;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $sourceRef = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $assignedNodeId = null;

    #[ORM\Column(length: 32)]
    private string $status = TicketWorkflow::STATUS_RECEIVED;

    #[ORM\Column]
    private int $loopCount = 0;

    #[ORM\Column(type: Types::JSON)]
    private array $payload = [];

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $analysisSummary = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $resolvedProjectKey = null;

    #[ORM\Column(length: 16, nullable: true)]
    private ?string $repoResolution = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $lastResolution = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $finishedAt = null;

    /** @var Collection<int, AgentRun> */
    #[ORM\OneToMany(targetEntity: AgentRun::class, mappedBy: 'ticketProcessing', cascade: ['persist'])]
    private Collection $agentRuns;

    public function __construct(string $ticketNumber, string $inputSource)
    {
        $this->ticketNumber = $ticketNumber;
        $this->inputSource = $inputSource;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
        $this->agentRuns = new ArrayCollection();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getTicketNumber(): string
    {
        return $this->ticketNumber;
    }

    public function getInputSource(): string
    {
        return $this->inputSource;
    }

    public function setInputSource(string $inputSource): static
    {
        $this->inputSource = $inputSource;

        return $this;
    }

    public function getSourceRef(): ?string
    {
        return $this->sourceRef;
    }

    public function setSourceRef(?string $sourceRef): static
    {
        $this->sourceRef = $sourceRef;

        return $this;
    }

    public function getAssignedNodeId(): ?string
    {
        return $this->assignedNodeId;
    }

    public function setAssignedNodeId(?string $assignedNodeId): static
    {
        $this->assignedNodeId = $assignedNodeId;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        $this->touch();

        return $this;
    }

    public function getLoopCount(): int
    {
        return $this->loopCount;
    }

    public function setLoopCount(int $loopCount): static
    {
        $this->loopCount = $loopCount;

        return $this;
    }

    public function incrementLoopCount(): static
    {
        ++$this->loopCount;

        return $this;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function setPayload(array $payload): static
    {
        $this->payload = $payload;

        return $this;
    }

    public function getAnalysisSummary(): ?array
    {
        return $this->analysisSummary;
    }

    public function setAnalysisSummary(?array $analysisSummary): static
    {
        $this->analysisSummary = $analysisSummary;

        return $this;
    }

    public function getResolvedProjectKey(): ?string
    {
        return $this->resolvedProjectKey;
    }

    public function setResolvedProjectKey(?string $resolvedProjectKey): static
    {
        $this->resolvedProjectKey = $resolvedProjectKey;

        return $this;
    }

    public function getRepoResolution(): ?string
    {
        return $this->repoResolution;
    }

    public function setRepoResolution(?string $repoResolution): static
    {
        $this->repoResolution = $repoResolution;

        return $this;
    }

    public function getLastResolution(): ?string
    {
        return $this->lastResolution;
    }

    public function setLastResolution(?string $lastResolution): static
    {
        $this->lastResolution = $lastResolution;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
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

    /** @return Collection<int, AgentRun> */
    public function getAgentRuns(): Collection
    {
        return $this->agentRuns;
    }

    public function addAgentRun(AgentRun $agentRun): static
    {
        if (!$this->agentRuns->contains($agentRun)) {
            $this->agentRuns->add($agentRun);
            $agentRun->setTicketProcessing($this);
        }

        return $this;
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
