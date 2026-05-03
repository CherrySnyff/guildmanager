<?php

namespace Guild\Manager\Cli\Command;

use Guild\Manager\Service\Guild\GuildWorkflow;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use XF\Cli\Command\AbstractCommand;

/** CLI: создание гильдии с лидером и стартовыми полями. */
class CreateGuild extends AbstractCommand
{
    protected function configure(): void
    {
        $this
            ->setName('guild-manager:create-guild')
            ->setDescription('Create a guild via GuildWorkflow.')
            ->addArgument('actor-user-id', InputArgument::REQUIRED, 'User ID of actor.')
            ->addArgument('title', InputArgument::REQUIRED, 'Guild title.')
            ->addArgument('description', InputArgument::OPTIONAL, 'Guild description.', '');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $actorUserId = (int)$input->getArgument('actor-user-id');
        $title = (string)$input->getArgument('title');
        $description = (string)$input->getArgument('description');

        /** @var \XF\Entity\User|null $actor */
        $actor = $this->app->em()->find('XF:User', $actorUserId);
        if (!$actor) {
            $output->writeln('Actor user not found.');
            return 1;
        }

        /** @var GuildWorkflow $workflow */
        $workflow = $this->app->service('Guild\Manager:Guild\GuildWorkflow');
        $guild = $workflow->createGuild($actor, $title, $description);

        $output->writeln("Guild created. ID: {$guild->guild_id}, title: {$guild->title}");
        return 0;
    }
}
