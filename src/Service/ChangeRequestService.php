<?php
namespace App\Service;

use App\Entity\Event;
use App\Entity\EventChangeApproval;
use App\Entity\EventChangeRequest;
use App\Entity\EventHistory;
use App\Entity\Guardian;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ChangeRequestService
{
    public function __construct(
        private EntityManagerInterface $em,
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urlGenerator,
        private string $fromEmail,
        private string $fromName,
        private string $appBaseUrl,
    ) {}

    /**
     * Crée une demande de validation et/ou envoie des notifications
     * 
     * @param array $notifyGuardianIds   IDs des gardiens à notifier
     * @param array $validateGuardianIds IDs des gardiens qui doivent valider
     */
    public function handle(
        Event $event,
        Guardian $author,
        string $action,
        array $snapshot,
        array $notifyGuardianIds,
        array $validateGuardianIds
    ): ?EventChangeRequest {
        $changeRequest = null;

        // ── VALIDATION ──
        if (!empty($validateGuardianIds)) {
            $changeRequest = new EventChangeRequest();
            $changeRequest->setEvent($event);
            $changeRequest->setRequestedBy($author);
            $changeRequest->setAction($action);
            $changeRequest->setEventSnapshot($snapshot);

            foreach ($validateGuardianIds as $guardianId) {
                $guardian = $this->em->find(Guardian::class, (int)$guardianId);
                if (!$guardian) continue;

                $approval = new EventChangeApproval();
                $approval->setChangeRequest($changeRequest);
                $approval->setGuardian($guardian);
                $this->em->persist($approval);

                // Email avec boutons valider/refuser
                $this->sendValidationEmail($approval, $event, $author, $action, $snapshot);
            }

            $this->em->persist($changeRequest);

            // Marquer l'event comme pending si update/delete
            if (in_array($action, [EventChangeRequest::ACTION_UPDATE, EventChangeRequest::ACTION_DELETE])) {
                $event->setStatus('pending');
                $event->setPendingSnapshot($snapshot);
            }

            // Historique
            $this->addHistory($event, $author, EventHistory::ACTION_PROPOSED,
                "Modification proposée par {$author->getFullName()} — en attente de validation", $snapshot);
        }

        // ── NOTIFICATION SIMPLE ──
        $notifyOnly = array_diff($notifyGuardianIds, $validateGuardianIds);
        foreach ($notifyOnly as $guardianId) {
            $guardian = $this->em->find(Guardian::class, (int)$guardianId);
            if (!$guardian) continue;
            $this->sendNotificationEmail($guardian, $event, $author, $action, $snapshot);
        }

        if (!empty($notifyOnly)) {
            $this->addHistory($event, $author, EventHistory::ACTION_NOTIFIED,
                "Notification envoyée à " . count($notifyOnly) . " gardien(s)", null);
        }

        // Historique de l'action si pas de validation
        if (empty($validateGuardianIds)) {
            $actionLabel = match($action) {
                'create' => "Créé par {$author->getFullName()}",
                'update' => "Modifié par {$author->getFullName()}",
                'delete' => "Supprimé par {$author->getFullName()}",
                default  => $action,
            };
            $this->addHistory($event, $author, $action, $actionLabel, $snapshot);
        }

        return $changeRequest;
    }

    /**
     * Traite la réponse d'un gardien (approbation ou refus)
     */
    public function processResponse(EventChangeApproval $approval, string $response): void
    {
        if (!$approval->isPending()) return;

        $approval->setStatus($response);
        $approval->setRespondedAt(new \DateTime());

        $request  = $approval->getChangeRequest();
        $event    = $request->getEvent();
        $guardian = $approval->getGuardian();

        $this->addHistory($event, $guardian,
            $response === 'approved' ? EventHistory::ACTION_APPROVED : EventHistory::ACTION_REJECTED,
            ($response === 'approved' ? '✅ Validé' : '❌ Refusé') . " par {$guardian?->getFullName()}",
            null
        );

        // Vérifier si tous ont répondu
        if ($request->hasRejection()) {
            // Au moins un refus → annuler
            $request->setStatus(EventChangeRequest::STATUS_REJECTED);
            $request->setResolvedAt(new \DateTime());
            $event->setStatus('active');
            $event->setPendingSnapshot(null);
            $this->notifyResolution($request, false);

        } elseif ($request->allApproved()) {
            // Tous approuvés → appliquer
            $request->setStatus(EventChangeRequest::STATUS_APPROVED);
            $request->setResolvedAt(new \DateTime());

            if ($request->getAction() === EventChangeRequest::ACTION_DELETE) {
                $this->addHistory($event, null, EventHistory::ACTION_DELETED,
                    "Supprimé après validation", $event->toSnapshot());
                $this->em->remove($event);
            } else {
                $this->applySnapshot($event, $request->getEventSnapshot());
                $event->setStatus('active');
                $event->setPendingSnapshot(null);
                $this->addHistory($event, null, EventHistory::ACTION_UPDATED,
                    "Modification appliquée après validation complète", $request->getEventSnapshot());
            }
            $this->notifyResolution($request, true);
        }

        $this->em->flush();
    }

    private function applySnapshot(Event $event, array $snapshot): void
    {
        if (isset($snapshot['title']))       $event->setTitle($snapshot['title']);
        if (isset($snapshot['type']))        $event->setType($snapshot['type']);
        if (isset($snapshot['description'])) $event->setDescription($snapshot['description']);
        if (isset($snapshot['allDay']))      $event->setAllDay($snapshot['allDay']);
        if (isset($snapshot['startAt']))     $event->setStartAt(new \DateTime($snapshot['startAt']));
        if (isset($snapshot['endAt']))       $event->setEndAt(new \DateTime($snapshot['endAt']));
        if (array_key_exists('visibleTo', $snapshot)) $event->setVisibleTo($snapshot['visibleTo']);
    }

    private function sendValidationEmail(EventChangeApproval $approval, Event $event, Guardian $author, string $action, array $snapshot): void
    {
        $guardian = $approval->getGuardian();
        if (!$guardian?->getEmail()) return;

        $approveUrl = $this->appBaseUrl . '/event/approval/' . $approval->getToken() . '/approve';
        $rejectUrl  = $this->appBaseUrl . '/event/approval/' . $approval->getToken() . '/reject';

        $actionLabel = match($action) {
            'create' => 'créer',
            'update' => 'modifier',
            'delete' => 'supprimer',
            default  => $action,
        };

        $childName  = $event->getChild()->getFirstName();
        $eventTitle = $snapshot['title'] ?? $event->getTitle();
        $startAt    = $snapshot['startAt'] ?? $event->getStartAt()?->format('d/m/Y');
        $desc       = $snapshot['description'] ?? $event->getDescription();

        $html = "<!DOCTYPE html><html lang='fr'><head><meta charset='UTF-8'></head>
        <body style='margin:0;padding:0;background:#FAF7F2;font-family:Arial,sans-serif;'>
        <div style='max-width:560px;margin:2rem auto;background:white;border-radius:20px;border:1px solid #E8DFD0;overflow:hidden;'>
            <div style='background:#3D5A47;padding:1.2rem 2rem;'>
                <span style='font-family:Georgia,serif;font-size:1.4rem;color:white;'>
                    Gr<span style='color:#FAD7A0;font-style:italic;'>oo</span>ty
                </span>
            </div>
            <div style='padding:2rem;'>
                <h2 style='font-family:Georgia,serif;font-size:1.2rem;font-weight:400;margin:0 0 1rem;'>
                    {$author->getFirstName()} souhaite {$actionLabel} un événement
                </h2>
                <div style='background:#F2EDE4;border-radius:12px;padding:1.2rem;margin-bottom:1.5rem;'>
                    <div style='font-size:.75rem;font-weight:700;text-transform:uppercase;color:#8A8578;margin-bottom:.4rem;'>{$event->getType()}</div>
                    <div style='font-family:Georgia,serif;font-size:1.1rem;color:#1C1C1A;margin-bottom:.3rem;'>{$eventTitle}</div>
                    <div style='font-size:.85rem;color:#8A8578;'>📅 {$startAt}</div>
                    " . ($desc ? "<div style='font-size:.85rem;color:#3D3D38;margin-top:.6rem;line-height:1.5;'>{$desc}</div>" : "") . "
                </div>
                <p style='color:#3D3D38;font-size:.9rem;line-height:1.6;margin-bottom:1.5rem;'>
                    Votre validation est requise pour appliquer ce changement au calendrier de <strong>{$childName}</strong>.
                </p>
                <div style='display:flex;gap:1rem;justify-content:center;margin:2rem 0;'>
                    <a href='{$approveUrl}'
                       style='background:#3D5A47;color:white;padding:.8rem 1.8rem;border-radius:100px;
                              text-decoration:none;font-weight:600;font-size:.95rem;'>
                        ✅ Valider
                    </a>
                    <a href='{$rejectUrl}'
                       style='background:#C4714A;color:white;padding:.8rem 1.8rem;border-radius:100px;
                              text-decoration:none;font-weight:600;font-size:.95rem;'>
                        ❌ Refuser
                    </a>
                </div>
            </div>
            <div style='padding:1rem 2rem;background:#FAF7F2;border-top:1px solid #E8DFD0;text-align:center;'>
                <p style='font-size:.75rem;color:#8A8578;margin:0;'>
                    <a href='{$this->appBaseUrl}' style='color:#3D5A47;'>grooty.fr</a>
                </p>
            </div>
        </div></body></html>";

        $this->send($guardian->getEmail(), $guardian->getFullName(),
            "Validation requise — {$eventTitle} ({$childName})", $html);
    }

    private function sendNotificationEmail(Guardian $guardian, Event $event, Guardian $author, string $action, array $snapshot): void
    {
        if (!$guardian->getEmail()) return;

        $actionLabel = match($action) {
            'create' => 'créé',
            'update' => 'modifié',
            'delete' => 'supprimé',
            default  => $action,
        };

        $childName  = $event->getChild()->getFirstName();
        $eventTitle = $snapshot['title'] ?? $event->getTitle();
        $startAt    = $snapshot['startAt'] ?? $event->getStartAt()?->format('d/m/Y');
        $link       = $this->appBaseUrl . '/train/' . $event->getChild()->getId();

        $html = "<!DOCTYPE html><html lang='fr'><head><meta charset='UTF-8'></head>
        <body style='margin:0;padding:0;background:#FAF7F2;font-family:Arial,sans-serif;'>
        <div style='max-width:560px;margin:2rem auto;background:white;border-radius:20px;border:1px solid #E8DFD0;overflow:hidden;'>
            <div style='background:#3D5A47;padding:1.2rem 2rem;'>
                <span style='font-family:Georgia,serif;font-size:1.4rem;color:white;'>
                    Gr<span style='color:#FAD7A0;font-style:italic;'>oo</span>ty
                </span>
            </div>
            <div style='padding:2rem;'>
                <h2 style='font-family:Georgia,serif;font-size:1.2rem;font-weight:400;margin:0 0 .5rem;'>
                    {$author->getFirstName()} a {$actionLabel} un événement
                </h2>
                <p style='color:#8A8578;font-size:.85rem;margin:0 0 1.2rem;'>Calendrier de {$childName}</p>
                <div style='background:#F2EDE4;border-radius:12px;padding:1.2rem;margin-bottom:1.5rem;'>
                    <div style='font-family:Georgia,serif;font-size:1.1rem;color:#1C1C1A;margin-bottom:.3rem;'>{$eventTitle}</div>
                    <div style='font-size:.85rem;color:#8A8578;'>📅 {$startAt}</div>
                </div>
                <div style='text-align:center;'>
                    <a href='{$link}' style='background:#3D5A47;color:white;padding:.7rem 1.8rem;border-radius:100px;text-decoration:none;font-weight:600;font-size:.9rem;display:inline-block;'>
                        Voir le calendrier
                    </a>
                </div>
            </div>
            <div style='padding:1rem 2rem;background:#FAF7F2;border-top:1px solid #E8DFD0;text-align:center;'>
                <p style='font-size:.75rem;color:#8A8578;margin:0;'><a href='{$this->appBaseUrl}' style='color:#3D5A47;'>grooty.fr</a></p>
            </div>
        </div></body></html>";

        $this->send($guardian->getEmail(), $guardian->getFullName(),
            "{$author->getFirstName()} a {$actionLabel} « {$eventTitle} » ({$childName})", $html);
    }

    private function notifyResolution(EventChangeRequest $request, bool $approved): void
    {
        $event    = $request->getEvent();
        $author   = $request->getRequestedBy();
        if (!$author?->getEmail() || !$event) return;

        $status = $approved ? '✅ validée' : '❌ refusée';
        $title  = $request->getEventSnapshot()['title'] ?? $event->getTitle();

        $html = "<!DOCTYPE html><html lang='fr'><head><meta charset='UTF-8'></head>
        <body style='margin:0;padding:0;background:#FAF7F2;font-family:Arial,sans-serif;'>
        <div style='max-width:560px;margin:2rem auto;background:white;border-radius:20px;border:1px solid #E8DFD0;overflow:hidden;'>
            <div style='background:#3D5A47;padding:1.2rem 2rem;'>
                <span style='font-family:Georgia,serif;font-size:1.4rem;color:white;'>Gr<span style='color:#FAD7A0;font-style:italic;'>oo</span>ty</span>
            </div>
            <div style='padding:2rem;'>
                <h2 style='font-family:Georgia,serif;font-size:1.2rem;font-weight:400;margin:0 0 1rem;'>
                    Votre modification a été {$status}
                </h2>
                <div style='background:#F2EDE4;border-radius:12px;padding:1rem;margin-bottom:1rem;'>
                    <div style='font-family:Georgia,serif;font-size:1rem;'>{$title}</div>
                </div>
                " . (!$approved ? "<p style='color:#C4714A;font-size:.9rem;'>La modification n'a pas été appliquée.</p>" : "<p style='color:#3D5A47;font-size:.9rem;'>La modification a été appliquée au calendrier.</p>") . "
            </div>
            <div style='padding:1rem 2rem;background:#FAF7F2;border-top:1px solid #E8DFD0;text-align:center;'>
                <p style='font-size:.75rem;color:#8A8578;margin:0;'><a href='{$this->appBaseUrl}' style='color:#3D5A47;'>grooty.fr</a></p>
            </div>
        </div></body></html>";

        $this->send($author->getEmail(), $author->getFullName(),
            "Modification {$status} — {$title}", $html);
    }

    private function addHistory(Event $event, ?Guardian $guardian, string $action, string $label, ?array $snapshot): void
    {
        $h = new EventHistory();
        $h->setEvent($event);
        $h->setGuardian($guardian);
        $h->setAction($action);
        $h->setLabel($label);
        $h->setSnapshot($snapshot);
        $this->em->persist($h);
    }

    private function send(string $toEmail, string $toName, string $subject, string $html): void
    {
        try {
            $this->mailer->send(
                (new Email())
                    ->from(new Address($this->fromEmail, $this->fromName))
                    ->to(new Address($toEmail, $toName))
                    ->subject($subject)
                    ->html($html)
            );
        } catch (\Exception $e) {}
    }
}
