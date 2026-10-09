# Workers macOS (launchd)

Deux agents `launchd` pour le MVP local :

| Fichier | Rôle |
|---------|------|
| `com.akyos.ticket.messenger.plist` | `messenger:consume async scheduler_default` (jobs async + poll IMAP planifié) |

## Installation

1. Adapter `WorkingDirectory` et chemins de logs dans le plist si le projet n’est pas dans `~/Desktop/ticket/site`.
2. Activer le poll IMAP dans **Paramètres** (ou `IMAP_POLL_ENABLED=1` dans `.env.local`).
3. Copier le plist :

```bash
cp deploy/launchd/com.akyos.ticket.messenger.plist ~/Library/LaunchAgents/
launchctl load ~/Library/LaunchAgents/com.akyos.ticket.messenger.plist
```

Le worker doit consommer **`async`** et **`scheduler_default`** : le scheduler Symfony 7 n’a plus de commande `scheduler:consume` ; c’est le transport `scheduler_default` qui déclenche le poll IMAP toutes les 2 minutes.

## Commandes utiles

```bash
launchctl list | grep akyos.ticket
launchctl unload ~/Library/LaunchAgents/com.akyos.ticket.messenger.plist
launchctl kickstart -k gui/$(id -u)/com.akyos.ticket.messenger
```

Les workers redémarrent après reconnexion / reboot Mac si les plist restent dans `LaunchAgents`.
