<?php
namespace App\Controller;

use App\Repository\GuardianRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ResetPasswordController extends AbstractController
{
    #[Route('/forgot-password', name: 'app_forgot_password', methods: ['GET', 'POST'])]
    public function request(
        Request $request,
        GuardianRepository $repo,
        EntityManagerInterface $em,
        MailerInterface $mailer,
        UrlGeneratorInterface $urlGenerator
    ): Response {
        if ($request->isMethod('POST')) {
            $email   = $request->request->get('email', '');
            $guardian = $repo->findOneBy(['email' => $email]);

            // Toujours afficher le même message (sécurité)
            if ($guardian) {
                $token   = bin2hex(random_bytes(32));
                $expires = new \DateTime('+1 hour');
                $guardian->setResetToken($token);
                $guardian->setResetTokenExpiresAt($expires);
                $em->flush();

                $link = $urlGenerator->generate('app_reset_password', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL);
                $appBaseUrl = $_ENV['APP_BASE_URL'] ?? 'https://grooty.fr';

                $html = "
                <!DOCTYPE html><html lang='fr'><head><meta charset='UTF-8'></head>
                <body style='margin:0;padding:0;background:#FAF7F2;font-family:Arial,sans-serif;'>
                    <div style='max-width:560px;margin:2rem auto;background:white;border-radius:20px;border:1px solid #E8DFD0;overflow:hidden;'>
                        <div style='background:#3D5A47;padding:1.2rem 2rem;'>
                            <span style='font-family:Georgia,serif;font-size:1.4rem;color:white;'>
                                Gr<span style='color:#FAD7A0;font-style:italic;'>oo</span>ty
                            </span>
                        </div>
                        <div style='padding:2rem;'>
                            <h2 style='font-family:Georgia,serif;font-size:1.3rem;font-weight:400;margin:0 0 1rem;'>
                                Réinitialisation de votre mot de passe
                            </h2>
                            <p style='color:#3D3D38;line-height:1.6;margin-bottom:1.5rem;'>
                                Vous avez demandé à réinitialiser votre mot de passe sur Grooty.
                                Cliquez sur le bouton ci-dessous — le lien est valable <strong>1 heure</strong>.
                            </p>
                            <div style='text-align:center;margin:2rem 0;'>
                                <a href='{$link}'
                                   style='background:#3D5A47;color:white;padding:.8rem 2rem;border-radius:100px;
                                          text-decoration:none;font-weight:600;font-size:1rem;display:inline-block;'>
                                    Réinitialiser mon mot de passe
                                </a>
                            </div>
                            <p style='color:#8A8578;font-size:.85rem;'>
                                Si vous n'avez pas demandé cela, ignorez cet email.
                            </p>
                        </div>
                        <div style='padding:1rem 2rem;background:#FAF7F2;border-top:1px solid #E8DFD0;text-align:center;'>
                            <p style='font-size:.75rem;color:#8A8578;margin:0;'>
                                <a href='{$appBaseUrl}' style='color:#3D5A47;'>grooty.fr</a>
                            </p>
                        </div>
                    </div>
                </body></html>";

                $message = (new Email())
                    ->from(new Address($_ENV['MAILER_FROM_EMAIL'] ?? 'noreply@grooty.fr', 'Grooty'))
                    ->to($guardian->getEmail())
                    ->subject('Réinitialisation de votre mot de passe Grooty')
                    ->html($html);

                try { $mailer->send($message); } catch (\Exception $e) {}
            }

            $this->addFlash('success', 'Si un compte existe avec cet email, vous recevrez un lien de réinitialisation.');
            return $this->redirectToRoute('app_forgot_password');
        }

        return $this->render('security/forgot_password.html.twig');
    }

    #[Route('/reset-password/{token}', name: 'app_reset_password', methods: ['GET', 'POST'])]
    public function reset(
        string $token,
        Request $request,
        GuardianRepository $repo,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher
    ): Response {
        $guardian = $repo->findOneBy(['resetToken' => $token]);

        if (!$guardian || !$guardian->getResetTokenExpiresAt() || $guardian->getResetTokenExpiresAt() < new \DateTime()) {
            $this->addFlash('error', 'Lien invalide ou expiré.');
            return $this->redirectToRoute('app_forgot_password');
        }

        if ($request->isMethod('POST')) {
            $password = $request->request->get('password', '');
            $confirm  = $request->request->get('confirm', '');

            if (strlen($password) < 6) {
                $this->addFlash('error', 'Le mot de passe doit faire au moins 6 caractères.');
                return $this->render('security/reset_password.html.twig', ['token' => $token]);
            }

            if ($password !== $confirm) {
                $this->addFlash('error', 'Les mots de passe ne correspondent pas.');
                return $this->render('security/reset_password.html.twig', ['token' => $token]);
            }

            $guardian->setPassword($hasher->hashPassword($guardian, $password));
            $guardian->setResetToken(null);
            $guardian->setResetTokenExpiresAt(null);
            $em->flush();

            $this->addFlash('success', 'Mot de passe mis à jour ! Vous pouvez vous connecter.');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/reset_password.html.twig', ['token' => $token]);
    }
}
