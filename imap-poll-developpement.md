# Écouter une boîte IMAP et réagir à chaque nouveau mail

Guide pour mettre en place, dans un projet Symfony (ou PHP), un système qui **surveille une boîte IMAP** et **appelle une fonction métier pour chaque nouveau mail**, avec toutes les données utiles (sujet, corps, expéditeur, pièces jointes, etc.).

Ce n’est **pas** une écoute push temps réel (IMAP IDLE). C’est un **poll périodique** : toutes les N minutes on demande au serveur les mails non lus, on traite chacun, puis on les marque comme lus.

---

## Idée générale

```
Scheduler (toutes les N min)
        │
        ▼
  Poll IMAP → mails UNSEEN
        │
        ▼
  Pour chaque mail :
    1. Extraire les données
    2. Appeler ton handler métier (callback / message Messenger / service)
    3. Marquer le mail comme lu (\\Seen)
```

Le handler métier est **projet-dépendant** : créer un ticket, une commande, une notification, etc. Le poll IMAP, lui, reste générique.

---

## 1. Dépendances

### Composer

```bash
composer require webklex/php-imap
```

Client IMAP en PHP pur — **pas besoin** de l’extension native `ext-imap`.  
Doc : [php-imap.com](https://www.php-imap.com/).

### Extensions PHP

`openssl`, `mbstring`, `iconv`, `json`, `fileinfo`, `libxml`, `zip`.

### Stack Symfony recommandée

| Package | Rôle |
|---------|------|
| `symfony/scheduler` | Déclencher le poll toutes les N minutes |
| `symfony/messenger` | Exécuter le poll (et le traitement) hors requête HTTP |
| `symfony/console` | Commande manuelle pour tester |

Alternative sans Scheduler : un cron qui lance `php bin/console app:poll-imap`.

---

## 2. Configuration minimale

```env
IMAP_HOST=imap.example.com
IMAP_PORT=993
IMAP_USER=
IMAP_PASSWORD=
IMAP_FOLDER=INBOX
```

- Port **993** + encryption `ssl` (IMAPS) dans la majorité des cas.
- Gmail / Microsoft : souvent App Password ou OAuth — pas couvert ici (auth user/password classique).

---

## 3. Connexion + récupération des non lus

```php
use Webklex\PHPIMAP\ClientManager;

$cm = new ClientManager();
$client = $cm->make([
    'host' => $host,
    'port' => (int) $port,
    'encryption' => 'ssl',
    'validate_cert' => true,
    'username' => $user,
    'password' => $password,
    'protocol' => 'imap',
]);

$client->connect();
$folder = $client->getFolder($folderName); // ex. INBOX

// Uniquement les mails non lus
$messages = $folder->messages()->unseen()->get();
```

Critère IMAP : `UNSEEN` (flag `\Seen` absent).  
C’est le critère adapté en production : chaque mail nouveau = un mail pas encore lu par ton outil.

> Astuce Webklex : si le fetch marque automatiquement comme lu, utilise `->leaveUnread()` sur la query pour lire sans poser `\Seen` avant d’avoir traité le mail, puis marque explicitement après succès.

---

## 4. Extraire les données du mail

Pour chaque `$message` (objet Webklex) :

| Donnée | Accès typique |
|--------|----------------|
| Message-ID | `$message->getMessageId()` |
| UID IMAP | `$message->getUid()` |
| Sujet | `$message->getSubject()` |
| Corps texte | `$message->getTextBody()` |
| Corps HTML | `$message->getHTMLBody()` |
| Expéditeur | `$message->getFrom()` (collection d’adresses) |
| Destinataires | `$message->getTo()`, `getCc()`, … |
| Date | `$message->getDate()` |
| Pièces jointes | `$message->getAttachments()` → nom, mime, contenu binaire |

Exemple de payload normalisé à passer au métier :

```php
$payload = [
    'message_id' => (string) $message->getMessageId(),
    'uid' => $message->getUid(),
    'subject' => (string) $message->getSubject(),
    'body_text' => (string) $message->getTextBody(),
    'body_html' => (string) $message->getHTMLBody(),
    'from' => $message->getFrom()?->first()?->mail,
    'date' => $message->getDate()?->toString(),
    'attachments' => [],
];

foreach ($message->getAttachments() as $attachment) {
    $payload['attachments'][] = [
        'filename' => (string) ($attachment->getName() ?: 'piece-jointe'),
        'mime' => (string) ($attachment->getContentType() ?: 'application/octet-stream'),
        'content' => $attachment->getContent(), // binaire ; ou base64 si tu sérialises
    ];
}
```

Ensuite : **ton code** — `handleNewMail($payload)`, dispatch Messenger, etc.

---

## 5. Après traitement réussi : marquer comme lu

```php
$message->setFlag('Seen');
```

Ainsi le prochain poll ne le reverra plus via `unseen()`.

**Important** : ne marque comme lu **qu’après** un traitement réussi. Sinon un crash entre lecture et métier fait perdre le mail.

Variante possible : déplacer le mail vers un dossier `Processed` au lieu de (ou en plus de) `\Seen`.

---

## 6. Brancher ça sur un scheduler Symfony

### Message + handler

```php
// Message marqueur
final class PollImapMessage {}

// Handler
public function __invoke(PollImapMessage $message): void
{
    foreach ($this->imapMailbox->fetchUnseen() as $payload) {
        // métier projet
        $this->onNewMail($payload);
        // ou : $this->bus->dispatch(new ProcessIncomingMailMessage($payload));
    }
}
```

Le service `imapMailbox` encapsule connexion + `unseen()` + mapping + `setFlag('Seen')`.

### Schedule

```php
$schedule->add(RecurringMessage::every('2 minutes', new PollImapMessage()));
```

### Messenger

Route le message de poll (et idéalement le traitement métier) vers un transport async.

### Worker (souvent oublié)

```bash
php bin/console messenger:consume async scheduler_default
```

- `scheduler_default` → émet `PollImapMessage` selon le rythme  
- `async` → exécute le handler  

Sans process permanent qui consomme **les deux**, rien ne tourne.

Avec un simple cron :

```bash
*/2 * * * * cd /path/to/app && php bin/console app:poll-imap
```

---

## 7. Structure de code minimale (cible)

```
src/
  Imap/
    IncomingMail.php          # DTO (données du mail)
    ImapMailbox.php           # connect + unseen + map + markSeen
  Message/
    PollImapMessage.php
  MessageHandler/
    PollImapMessageHandler.php   # appelle ImapMailbox, puis ton métier
  Schedule.php                   # RecurringMessage
  Command/
    PollImapCommand.php          # test manuel
```

`ImapMailbox` ne connaît pas ton métier. Le handler (ou un service dédié) décide quoi faire du DTO.

---

## 8. Checklist

1. [ ] `composer require webklex/php-imap` (+ extensions ; **pas** `ext-imap`)
2. [ ] Config host / port / user / password / folder
3. [ ] Service : connect → `messages()->unseen()->get()`
4. [ ] Mapping vers un DTO / array stable
5. [ ] Callback métier **par mail**
6. [ ] `setFlag('Seen')` seulement après succès
7. [ ] Déclenchement : Scheduler + Messenger **ou** cron + commande console
8. [ ] Worker (ou cron) réellement démarré en prod
9. [ ] Commande CLI de test avant d’activer le rythme

---

## 9. Pièges fréquents

| Symptôme | Cause probable |
|----------|----------------|
| Aucun poll | Worker sans `scheduler_default`, ou cron absent |
| Mails jamais traités | Autre client les a déjà marqués lus |
| Mails retraités en boucle | Oubli de `setFlag('Seen')` (ou traitement en erreur avant le flag) |
| Mail « perdu » | Flag posé avant le succès métier |
| « Il faut ext-imap » | Mauvais tutoriel (`imap_open`) — ici c’est Webklex |
| SSL / connexion | Mauvais port (143 vs 993), cert, firewall |

---

## 10. Hors scope (mais possible ensuite)

- IMAP **IDLE** (push quasi temps réel)
- OAuth2 (Gmail / Microsoft 365)
- Envoi de réponses (SMTP / Mailer)
- Déduplication applicative en plus de `\Seen` (utile si plusieurs process pollent la même boîte)

---

## Référence d’implémentation dans ce repo

Même schéma (Scheduler → `PollImapMessage` → service IMAP → dispatch métier) dans :

- `site/src/Input/ImapInputSource.php`
- `site/src/MessageHandler/PollImapMessageHandler.php`
- `site/src/Schedule.php`

Adapte le critère de sélection (`unseen()` + `setFlag('Seen')`) et branche ton propre handler sur le payload mail.
