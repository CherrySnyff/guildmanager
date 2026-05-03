<?php

namespace Guild\Manager\Cli\Command;

use Guild\Manager\Service\Guild\GuildWorkflow;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use XF\Cli\Command\AbstractCommand;

/** CLI: передача лидерства другому пользователю. */
class TransferLeader extends AbstractCommand
{
    protected function configure(): void
    {
        $this
            ->setName('guild-manager:transfer-leader')
            ->setDescription('Transfer guild leadership via GuildWorkflow.')
            ->addArgument('guild-id', InputArgument::REQUIRED, 'Guild ID.')
            ->addArgument('actor-user-id', InputArgument::REQUIRED, 'User ID of actor.')
            ->addArgument('new-leader-user-id', InputArgument::REQUIRED, 'User ID of new leader.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $guildId = (int)$input->getArgument('guild-id');
        $actorUserId = (int)$input->getArgument('actor-user-id');
        $newLeaderUserId = (int)$input->getArgument('new-leader-user-id');

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

        /** @var \XF\Entity\User|null $newLeader */
        $newLeader = $this->app->em()->find('XF:User', $newLeaderUserId);
        if (!$newLeader) {
            $output->writeln('New leader user not found.');
            return 1;
        }

        /** @var GuildWorkflow $workflow */
        $workflow = $this->app->service('Guild\Manager:Guild\GuildWorkflow');
        $workflow->transferLeadership($guild, $actor, $newLeader);

        $output->writeln("Guild leader changed. Guild {$guild->guild_id} -> {$newLeader->username} ({$newLeader->user_id})");
        return 0;
    }
}
