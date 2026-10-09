<?php

namespace App\Twig\Components;

use App\Entity\TicketProcessing;
use App\Form\ResolveProjectFormType;
use App\Message\ExecuteCloneRepoMessage;
use App\Repository\TicketProcessingRepository;
use App\Service\ProjectResolverService;
use App\Workflow\TicketWorkflow;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
final class ResolveProjectLiveComponent
{
    use DefaultActionTrait;
    use ComponentWithFormTrait;

    #[LiveProp]
    public string $ticketNumber = '';

    public function __construct(
        private readonly TicketProcessingRepository $tickets,
        private readonly ProjectResolverService $projects,
        private readonly MessageBusInterface $bus,
        private readonly EntityManagerInterface $em,
        private readonly FormFactoryInterface $formFactory,
    ) {
    }

    public function getTicket(): ?TicketProcessing
    {
        return $this->tickets->findOneBy(['ticketNumber' => $this->ticketNumber]);
    }

    public function isVisible(): bool
    {
        $ticket = $this->getTicket();
        if (null === $ticket) {
            return false;
        }

        return TicketWorkflow::STATUS_WAITING_FOR_HUMAN === $ticket->getStatus()
            && null === $ticket->getResolvedProjectKey();
    }

    protected function instantiateForm(): FormInterface
    {
        return $this->formFactory->create(ResolveProjectFormType::class, null, [
            'project_keys' => $this->projects->getProjectKeys(),
        ]);
    }

    #[LiveAction]
    public function validateAndClone(): void
    {
        $this->submitForm();
        $ticket = $this->getTicket();
        if (null === $ticket) {
            return;
        }

        $projectKey = (string) ($this->getForm()->getData()['project_key'] ?? '');
        if (!$this->projects->isValidProjectKey($projectKey)) {
            return;
        }

        $ticket->setResolvedProjectKey($projectKey);
        $ticket->setRepoResolution('manual');
        $this->em->flush();

        $this->bus->dispatch(new ExecuteCloneRepoMessage($this->ticketNumber, $projectKey));
        $this->resetForm();
    }
}
