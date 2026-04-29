<?php

namespace App\Service;

use App\Entity\Child;
use App\Entity\ChildGuardian;
use App\Entity\Event;
use App\Entity\Guardian;
use App\Entity\Note;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;

class NotificationService
{
    public function __construct(
        private MailerInterface $mailer,
        private string $fromEmail,
        private string $fromName,
        private string $appBaseUrl,
    ) {}

    /** Notifie les autres gardiens quand un événement est créé */
    public function notifyNewEvent(Event $event, Guardian $author): void
    {
        $child    = $event->getChild();
        $guardian = $author;

        foreach ($child->getChildGuardians() as $cg) {
            $recipient = $cg->getGuardian();
            if (!$recipient || $recipient->getId() === $guardian->getId()) continue;
            if (!$cg->isInviteAccepted()) continue;
            if (!$event->isVisibleBy($recipient)) continue;

            $this->send(
                $recipient,
                "📅 Nouvel événement pour {$child->getFirstName()}",
                $this->renderEventEmail($event, $child, $author, $recipient)
            );
        }
    }

    /** Notifie les autres gardiens quand une note est ajoutée */
    public function notifyNewNote(Note $note, Guardian $author): void
    {
        $child = $note->getChild();

        foreach ($child->getChildGuardians() as $cg) {
            $recipient = $cg->getGuardian();
            if (!$recipient || $recipient->getId() === $author->getId()) continue;
            if (!$cg->isInviteAccepted()) continue;
            if (!$note->isVisibleBy($recipient)) continue;

            $this->send(
                $recipient,
                "💬 Nouvelle note pour {$child->getFirstName()}",
                $this->renderNoteEmail($note, $child, $author, $recipient)
            );
        }
    }

    /** Envoie le lien d'invitation par email */
    public function sendInvitation(ChildGuardian $cg, Guardian $invitedBy): void
    {
        $email    = $cg->getInviteEmail();
        $child    = $cg->getChild();
        $token    = $cg->getInviteToken();
        $link     = $this->appBaseUrl . '/invite/accept/' . $token;

        $roleLabels = [
            'parent'      => 'parent',
            'nounou'      => 'nounou',
            'grandparent' => 'grand-parent',
            'other'       => 'gardien',
        ];
        $roleLabel = $roleLabels[$cg->getRole()] ?? 'gardien';

        $html = $this->layout(
            "Invitation Grooty",
            "
            <h2 style='font-family:Georgia,serif;font-size:1.4rem;font-weight:400;color:#1C1C1A;margin:0 0 1rem;'>
                {$invitedBy->getFirstName()} vous invite sur Grooty 🌿
            </h2>
            <p style='color:#3D3D38;line-height:1.6;'>
                <strong>{$invitedBy->getFullName()}</strong> vous a ajouté en tant que <strong>{$roleLabel}</strong>
                pour <strong>{$child->getFirstName()}</strong> sur Grooty, l'agenda familial partagé.
            </p>
            <p style='color:#3D3D38;line-height:1.6;margin-top:1rem;'>
                Cliquez sur le bouton ci-dessous pour créer votre compte (ou vous connecter) et accéder au calendrier.
            </p>
            <div style='text-align:center;margin:2rem 0;'>
                <a href='{$link}'
                   style='background:#3D5A47;color:white;padding:.8rem 2rem;border-radius:100px;
                          text-decoration:none;font-weight:600;font-size:1rem;
                          display:inline-block;'>
                    Rejoindre le calendrier de {$child->getFirstName()}
                </a>
            </div>
            <p style='color:#8A8578;font-size:.85rem;'>
                Ou copiez ce lien : <a href='{$link}' style='color:#3D5A47;'>{$link}</a>
            </p>
            "
        );

        $message = (new Email())
            ->from(new Address($this->fromEmail, $this->fromName))
            ->to($email)
            ->subject("Invitation à rejoindre le calendrier de {$child->getFirstName()} sur Grooty")
            ->html($html);

        try {
            $this->mailer->send($message);
        } catch (\Exception $e) {
            // Log silencieux en dev — ne pas bloquer l'app
        }
    }

    private function send(Guardian $recipient, string $subject, string $html): void
    {
        if (!$recipient->getEmail()) return;

        $message = (new Email())
            ->from(new Address($this->fromEmail, $this->fromName))
            ->to(new Address($recipient->getEmail(), $recipient->getFullName()))
            ->subject($subject)
            ->html($html);

        try {
            $this->mailer->send($message);
        } catch (\Exception $e) {
            // Log silencieux — ne pas bloquer l'app
        }
    }

    private function renderEventEmail(Event $event, Child $child, Guardian $author, Guardian $recipient): string
    {
        $typeLabels = [
            'garde'    => '🏠 Garde',
            'activite' => '🏃 Activité',
            'medical'  => '🏥 Médical',
            'vacances' => '🌴 Vacances',
            'autre'    => '📌 Autre',
        ];
        $type  = $typeLabels[$event->getType()] ?? '📌';
        $date  = $event->getStartAt()->format('d/m/Y');
        $link  = $this->appBaseUrl . '/train/' . $child->getId();

        return $this->layout(
            "Nouvel événement — {$child->getFirstName()}",
            "
            <h2 style='font-family:Georgia,serif;font-size:1.4rem;font-weight:400;color:#1C1C1A;margin:0 0 .5rem;'>
                Nouvel événement pour {$child->getFirstName()} 📅
            </h2>
            <p style='color:#8A8578;font-size:.85rem;margin:0 0 1.5rem;'>
                Ajouté par {$author->getFullName()}
            </p>
            <div style='background:#F2EDE4;border-radius:14px;padding:1.2rem;margin-bottom:1.5rem;'>
                <div style='font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#8A8578;margin-bottom:.3rem;'>{$type}</div>
                <div style='font-family:Georgia,serif;font-size:1.2rem;color:#1C1C1A;margin-bottom:.4rem;'>{$event->getTitle()}</div>
                <div style='font-size:.88rem;color:#8A8578;'>📅 {$date}</div>
                " . ($event->getDescription() ? "<div style='font-size:.88rem;color:#3D3D38;margin-top:.6rem;line-height:1.5;'>{$event->getDescription()}</div>" : "") . "
            </div>
            <div style='text-align:center;'>
                <a href='{$link}'
                   style='background:#3D5A47;color:white;padding:.7rem 1.8rem;border-radius:100px;
                          text-decoration:none;font-weight:600;font-size:.95rem;display:inline-block;'>
                    Voir le calendrier de {$child->getFirstName()}
                </a>
            </div>
            "
        );
    }

    private function renderNoteEmail(Note $note, Child $child, Guardian $author, Guardian $recipient): string
    {
        $link = $this->appBaseUrl . '/children/' . $child->getId() . '/notes';

        return $this->layout(
            "Nouvelle note — {$child->getFirstName()}",
            "
            <h2 style='font-family:Georgia,serif;font-size:1.4rem;font-weight:400;color:#1C1C1A;margin:0 0 .5rem;'>
                {$author->getFirstName()} a écrit une note 💬
            </h2>
            <p style='color:#8A8578;font-size:.85rem;margin:0 0 1.5rem;'>
                À propos de {$child->getFirstName()}
            </p>
            " . ($note->getContent() ? "
            <div style='background:#F2EDE4;border-radius:14px;padding:1.2rem;margin-bottom:1.5rem;
                        border-left:4px solid #3D5A47;'>
                <p style='font-size:.95rem;color:#3D3D38;line-height:1.6;margin:0;'>
                    {$note->getContent()}
                </p>
            </div>
            " : "") . "
            <div style='text-align:center;'>
                <a href='{$link}'
                   style='background:#3D5A47;color:white;padding:.7rem 1.8rem;border-radius:100px;
                          text-decoration:none;font-weight:600;font-size:.95rem;display:inline-block;'>
                    Voir les notes de {$child->getFirstName()}
                </a>
            </div>
            "
        );
    }

    private function layout(string $title, string $content): string
    {
        return "
        <!DOCTYPE html>
        <html lang='fr'>
        <head><meta charset='UTF-8'><title>{$title}</title></head>
        <body style='margin:0;padding:0;background:#FAF7F2;font-family:\"DM Sans\",Arial,sans-serif;'>
            <div style='max-width:560px;margin:2rem auto;background:white;border-radius:20px;
                        border:1px solid #E8DFD0;overflow:hidden;box-shadow:0 4px 20px rgba(28,28,26,.08);'>
                <div style='background:#3D5A47;padding:1.2rem 2rem;'>
                    <span style='font-family:Georgia,serif;font-size:1.4rem;color:white;'>
                        Gr<span style='color:#FAD7A0;font-style:italic;'>oo</span>ty
                    </span>
                </div>
                <div style='padding:2rem;'>
                    {$content}
                </div>
                <div style='padding:1.2rem 2rem;background:#FAF7F2;border-top:1px solid #E8DFD0;text-align:center;'>
                    <p style='font-size:.75rem;color:#8A8578;margin:0;line-height:1.6;'>
                        Vous recevez cet email car vous êtes gardien sur Grooty.<br>
                        <a href='{$this->appBaseUrl}' style='color:#3D5A47;'>grooty.fr</a>
                    </p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
}
