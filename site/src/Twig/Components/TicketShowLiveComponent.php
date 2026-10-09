<?php

namespace App\Twig\Components;

use App\Agent\AgentOutcomeVocabulary;
use App\Entity\TicketProcessing;
use App\Message\RunCodeAgentMessage;
use App\Message\RunTriageAgentMessage;
use App\FileStorage\TicketFileStorage;
use App\Repository\TicketProcessingRepository;
use App\Service\AgentLoopGuard;
use App\Service\ServiceExecutionRecorder;
use App\Workflow\TicketWorkflow;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsLiveComponent]
final class TicketShowLiveComponent
{
    use DefaultActionTrait;

    #[LiveProp]
    public string $ticketNumber = '';

    #[LiveProp(writable: true)]
    public string $activeTab = 'mail';

    #[LiveProp]
    public ?string $flashMessage = null;

    #[LiveProp]
    public ?string $flashType = null;

    public function __construct(
        private readonly TicketProcessingRepository $tickets,
        private readonly TicketFileStorage $files,
        private readonly TicketWorkflow $ticketWorkflow,
        private readonly AgentLoopGuard $loopGuard,
        private readonly ServiceExecutionRecorder $serviceRecorder,
        private readonly MessageBusInterface $bus,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function getTicket(): ?TicketProcessing
    {
        return $this->tickets->findOneBy(['ticketNumber' => $this->ticketNumber]);
    }

    /** @return list<string> */
    public function getTabs(): array
    {
        return ['mail', 'prompts', 'analyse', 'services', 'fichiers', 'logs', 'actions'];
    }

    public function getMaxLoops(): int
    {
        return $this->loopGuard->getMaxLoops();
    }

    public function getServiceExecutionCount(): int
    {
        if ('' === $this->ticketNumber) {
            return 0;
        }

        return $this->serviceRecorder->countExecutions($this->ticketNumber);
    }

    /** @return list<string> */
    public function getTicketFilePaths(): array
    {
        if ('' === $this->ticketNumber) {
            return [];
        }

        return $this->files->listFiles($this->ticketNumber);
    }

    public function isPreviewableImage(string $path): bool
    {
        return (bool) preg_match('/\.(png|jpe?g|gif|webp)$/i', $path);
    }

    public function humanActionLabel(?string $action): string
    {
        if (null === $action || '' === $action || 'none' === $action) {
            return '';
        }

        return AgentOutcomeVocabulary::humanActionLabel($action);
    }

    #[LiveAction]
    public function switchTab(#[LiveArg] string $tab): void
    {
        if (in_array($tab, $this->getTabs(), true)) {
            $this->activeTab = $tab;
        }
    }

    #[LiveAction]
    public function relancerTriage(): void
    {
        $ticket = $this->getTicket();
        if (null === $ticket) {
            return;
        }

        try {
            if ($this->ticketWorkflow->can($ticket, TicketWorkflow::TRANSITION_RETRY_AGENT)) {
                $this->ticketWorkflow->apply($ticket, TicketWorkflow::TRANSITION_RETRY_AGENT);
            } elseif ($this->ticketWorkflow->can($ticket, TicketWorkflow::TRANSITION_QUEUE_AGENT)) {
                $this->ticketWorkflow->apply($ticket, TicketWorkflow::TRANSITION_QUEUE_AGENT);
            } else {
                throw new \DomainException('Relance triage impossible depuis l’état '.$ticket->getStatus());
            }
            $ticket->incrementLoopCount();
            $this->em->flush();
        } catch (\Throwable $e) {
            $this->setFlash('error', $e->getMessage());

            return;
        }

        $this->bus->dispatch(new RunTriageAgentMessage($this->ticketNumber));
        $this->setFlash('success', 'Triage relancé — le worker traite la file.');
    }

    #[LiveAction]
    public function relancerCode(): void
    {
        $ticket = $this->getTicket();
        if (null === $ticket || null === $ticket->getResolvedProjectKey()) {
            $this->setFlash('error', 'Projet non résolu — impossible de relancer l’analyse code.');

            return;
        }

        $contextPack = 'context-packs/'.$ticket->getResolvedProjectKey().'/pack.json';
        $this->bus->dispatch(new RunCodeAgentMessage($this->ticketNumber, $contextPack));
        $this->setFlash('success', 'Analyse code relancée.');
    }

    #[LiveAction]
    public function marquerIgnore(): void
    {
        $ticket = $this->getTicket();
        if (null === $ticket) {
            return;
        }

        try {
            $this->ticketWorkflow->apply($ticket, TicketWorkflow::TRANSITION_IGNORE);
            $ticket->setFinishedAt(new \DateTimeImmutable());
            $this->em->flush();
        } catch (\Throwable $e) {
            $this->setFlash('error', $e->getMessage());

            return;
        }
        $this->setFlash('success', 'Ticket marqué comme ignoré.');
    }

    #[LiveAction]
    public function archiver(): void
    {
        $ticket = $this->getTicket();
        if (null === $ticket) {
            return;
        }

        try {
            $this->ticketWorkflow->apply($ticket, TicketWorkflow::TRANSITION_FINISH);
            $ticket->setFinishedAt(new \DateTimeImmutable());
            $this->em->flush();
        } catch (\Throwable $e) {
            $this->setFlash('error', $e->getMessage());

            return;
        }
        $this->setFlash('success', 'Ticket archivé (finished).');
    }

    private function setFlash(string $type, string $message): void
    {
        $this->flashType = $type;
        $this->flashMessage = $message;
    }
}
