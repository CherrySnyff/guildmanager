<?php

namespace Guild\Manager\Cli\Command;

use Guild\Manager\Service\Guild\GuildWorkflow;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use XF\Cli\Command\AbstractCommand;

/** CLI: приглашение пользователя в гильдию (создание записи invited). */
class InviteMember extends AbstractCommand
{
    protected function configure(): void
    {
        $this
            ->setName('guild-manager:invite-member')
            ->setDescription('Invite member to guild via GuildWorkflow.')
            ->addArgument('guild-id', InputArgument::REQUIRED, 'Guild ID.')
            ->addArgument('actor-user-id', InputArgument::REQUIRED, 'User ID of actor.')
            ->addArgument('target-user-id', InputArgument::REQUIRED, 'User ID of invited user.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $guildId = (int)$input->getArgument('guild-id');
        $actorUserId = (int)$input->getArgument('actor-user-id');
        $targetUserId = (int)$input->getArgument('target-user-id');

        /** @var \Guild\Manager\Entity\Guild|null $guild */
        $guild = $this->app->em()->find('Guild\Manager:Guild', $guildId);
        if (!$guild) {
            $output->writeln('Guild not found.');
            return 1;
        }

        /** @var \XF\Entity\User|null $actor */
        $actor = $this->app->em()->find('XF:User', $actorUserId);
        if (!$actor) {
            $output->writeln('Actor user not found.');
            return 1;
        }

        /** @var \XF\Entity\User|null $target */
        $target = $this->app->em()->find('XF:User', $targetUserId);
        if (!$target) {
            $output->writeln('Target user not found.');
            return 1;
        }

        /** @var GuildWorkflow $workflow */
        $workflow = $this->app->service('Guild\Manager:Guild\GuildWorkflow');
        $workflow->inviteMember($guild, $actor, $target);

        $output->writeln("Invite created. Guild {$guild->guild_id}, user {$target->user_id}.");
        return 0;
    }
}
