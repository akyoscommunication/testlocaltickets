<?php

namespace App\Agent;

interface AgentRunnerInterface
{
    public function getName(): string;

    /**
     * Exécute l'agent et écrit le fichier résultat attendu.
     */
    public function run(AgentRunRequest $request): AgentRunResult;
}
