<?php
namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

#[AsCommand(name: 'app:test-mail', description: 'Test envoi email Brevo')]
class TestMailCommand extends Command
{
    public function __construct(private MailerInterface $mailer) { parent::__construct(); }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $email = (new Email())
            ->from($_ENV['MAILER_FROM_EMAIL'] ?? 'test@grooty.fr')
            ->to($_ENV['MAILER_FROM_EMAIL'] ?? 'test@grooty.fr')
            ->subject('🌿 Test Grooty — ' . date('H:i:s'))
            ->html('<h2>Grooty fonctionne !</h2><p>Email envoyé le ' . date('d/m/Y à H:i') . '</p>');

        try {
            $this->mailer->send($email);
            $output->writeln('<info>✓ Email envoyé avec succès !</info>');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln('<error>✗ Erreur : ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }
}
