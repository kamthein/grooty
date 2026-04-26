# Grooty 🪺

Agenda familial partagé — calendrier, notes et photos entre parents, nounous et grands-parents.

## Installation rapide

```bash
# 1. Dépendances
composer install

# 2. Config locale
cp .env .env.local
# Éditer .env.local : DATABASE_URL avec ton port MAMP (3306 ou 8889)

# 3. BDD + migration
make install

# 4. Serveur
symfony server:start
```

Ouvrir http://127.0.0.1:8000

## Stack

| | |
|---|---|
| Backend | Symfony 7.0 / PHP 8.1+ |
| BDD | MySQL 8 (MAMP port 8889) |
| Templates | Twig |
| Calendrier | FullCalendar 6 (CDN) |
| Uploads | Local `public/uploads/events/` |
| Fonts | Google Fonts CDN |

## Structure

```
src/
  Controller/   SecurityController, DashboardController, ChildController,
                EventController, NoteController, ProfileController, SharedController
  Entity/       Guardian, Child, ChildGuardian, Event, EventImage, Note, Attachment
  Form/         RegisterType, ChildType, EventType, InviteGuardianType, ProfileType
  Repository/   un repo par entité
  Security/     ChildVoter (VIEW / EDIT / ADMIN)
  Service/      LocalUploadService (uploads photos)

templates/
  base.html.twig          layout principal
  base/dashboard          accueil avec notes interactives
  child/                  CRUD enfants
  event/                  création/édition événements
  note/                   page notes standalone
  profile/                profil utilisateur
  security/               login / register
  shared/train            vue calendrier petit train

migrations/
  Version20250420000001   schéma complet en une migration
```

## Routes principales

| Route | URL | Description |
|---|---|---|
| app_dashboard | / | Accueil + notes |
| app_train | /train/{childId} | Calendrier train |
| app_event_new | /children/{id}/events/new | Créer événement |
| app_note_new | /children/{id}/notes/new | POST AJAX note |
| app_note_list | /children/{id}/notes/list | GET JSON notes |
| app_child_index | /children | Liste enfants |
| app_profile | /profile | Mon profil |

## Réinitialiser la BDD

```bash
make reset
```

## Ajouter PHP MAMP au PATH (une seule fois)

```bash
echo 'export PATH="/Applications/MAMP/bin/php/php8.3.30/bin:$PATH"' >> ~/.zshrc
source ~/.zshrc
```
