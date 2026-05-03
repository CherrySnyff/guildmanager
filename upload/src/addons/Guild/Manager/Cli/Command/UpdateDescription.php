<?php

namespace Guild\Manager\Cli\Command;

use Guild\Manager\Service\Guild\GuildWorkflow;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use XF\Cli\Command\AbstractCommand;

/** CLI: массовое или точечное обновление поля description гильдии. */
class UpdateDescription extends AbstractCommand
{
    protected function configure(): void
    {
        $this
            ->setName('guild-manager:update-description')
            ->setDescription('Update guild description via GuildWorkflow.')
            ->addArgument('guild-id', InputArgument::REQUIRED, 'Guild ID.')
            ->addArgument('actor-user-id', InputArgument::REQUIRED, 'User ID of actor.')
            ->addArgument('description', InputArgument::REQUIRED, 'Guild description in BBCode.')
            ->addArgument('change-note', InputArgument::OPTIONAL, 'Change note for history log.', '');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $guildId = (int)$input->getArgument('guild-id');
        $actorUserId = (int)$input->getArgument('actor-user-id');
        $description = (string)$input->getArgument('description');
        $changeNote = (string)$input->getArgument('change-note');

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

        /** @var GuildWorkflow $workflow */
        $workflow = $this->app->service('Guild\Manager:Guild\GuildWorkflow');
        $log = $workflow->updateDescription($guild, $actor, $description, $changeNote);

        $output->writeln(
            "Description updated. Guild {$guild->guild_id}, log {$log->description_log_id}, by user {$actor->user_id}."
        );
        return 0;
    }
}
