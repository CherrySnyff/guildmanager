<?php

/**
 * Установка, обновление и удаление аддона Guild Manager.
 *
 * Содержимое:
 * - install*: создание xf_* таблиц и seed xf_guild_level_rule (соответствие уровней числу последователей).
 * - upgradeNNNN*: миграции схемы / разовые пересчёты данных (комментируйте смысл в теле каждого шага).
 * - uninstall: снятие таблиц.
 *
 * Связность с кодом приложения см. классы-сервисы:
 * Aggregator — агрегаты гильдии; OperationManager — бизнес-операции по журналам;
 * Repository\GuildReputationLog — «мировая известность» и таблицы влияния для UI.
 */

namespace Guild\Manager;

use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\Db\Schema\Alter;
use XF\Db\Schema\Create;

/** Реализация жизненного цикла аддона в XenForо (см. блок-комментарий в начале файла). */
class Setup extends AbstractSetup
{
    use StepRunnerInstallTrait;
    use StepRunnerUpgradeTrait;
    use StepRunnerUninstallTrait;

    public function installStep1(): void
    {
        $this->doCreateTables($this->getTables());
    }

    public function installStep2(): void
    {
        $this->seedLevelRules();
    }

    public function upgrade1000030Step1(): void
    {
        // Early alpha compatibility: normalize table set to the latest schema.
        $this->query('DROP TABLE IF EXISTS `xf_guild_member`');
        $this->query('DROP TABLE IF EXISTS `xf_guild_treasury_log`');
        $this->query('DROP TABLE IF EXISTS `xf_guild_reputation_log`');
        $this->query('DROP TABLE IF EXISTS `xf_guild`');

        $this->doCreateTables($this->getTables());
    }

    public function upgrade1000030Step2(): void
    {
        $this->seedLevelRules();
    }

    public function upgrade1000080Step1(): void
    {
        if (!$this->schemaManager()->tableExists('xf_guild_member')) {
            $this->schemaManager()->createTable('xf_guild_member', function (Create $table)
            {
                $table->addColumn('guild_member_id', 'int')->unsigned()->autoIncrement();
                $table->addColumn('guild_id', 'int')->unsigned();
                $table->addColumn('user_id', 'int')->unsigned();
                $table->addColumn('username', 'varchar', 100)->setDefault('');
                $table->addColumn('role', 'enum', ['leader', 'officer', 'member'])->setDefault('member');
                $table->addColumn('member_state', 'enum', ['active', 'invited', 'banned'])->setDefault('active');
                $table->addColumn('joined_date', 'int')->unsigned()->setDefault(0);
                $table->addColumn('last_update', 'int')->unsigned()->setDefault(0);

                $table->addUniqueKey(['guild_id', 'user_id']);
                $table->addKey(['guild_id', 'role']);
                $table->addKey(['guild_id', 'member_state']);
                $table->addKey('user_id');
            });
        }
    }

    public function upgrade1000090Step1(): void
    {
        if ($this->schemaManager()->columnExists('xf_guild', 'member_count')) {
            return;
        }

        $this->schemaManager()->alterTable('xf_guild', function (\XF\Db\Schema\Alter $table)
        {
            $table->addColumn('member_count', 'int')->unsigned()->setDefault(0);
            $table->addKey('member_count');
        });
    }

    public function upgrade1000140Step1(): void
    {
        $this->schemaManager()->alterTable('xf_guild', function (Alter $table)
        {
            $table->changeColumn('description', 'mediumtext');

            if (!$this->schemaManager()->columnExists('xf_guild', 'description_rendered')) {
                $table->addColumn('description_rendered', 'mediumtext')->nullable();
            }
            if (!$this->schemaManager()->columnExists('xf_guild', 'description_update_date')) {
                $table->addColumn('description_update_date', 'int')->unsigned()->setDefault(0);
                $table->addKey('description_update_date');
            }
            if (!$this->schemaManager()->columnExists('xf_guild', 'description_update_user_id')) {
                $table->addColumn('description_update_user_id', 'int')->unsigned()->setDefault(0);
                $table->addKey('description_update_user_id');
            }
        });

        if (!$this->schemaManager()->tableExists('xf_guild_description_log')) {
            $this->schemaManager()->createTable('xf_guild_description_log', function (Create $table)
            {
                $table->addColumn('description_log_id', 'int')->unsigned()->autoIncrement();
                $table->addColumn('guild_id', 'int')->unsigned();
                $table->addColumn('old_description', 'mediumtext')->nullable();
                $table->addColumn('new_description', 'mediumtext')->nullable();
                $table->addColumn('changed_by_user_id', 'int')->unsigned()->setDefault(0);
                $table->addColumn('change_date', 'int')->unsigned()->setDefault(0);
                $table->addColumn('change_note', 'varchar', 255)->setDefault('');

                $table->addKey('guild_id');
                $table->addKey('changed_by_user_id');
                $table->addKey('change_date');
            });
        }
    }

    public function upgrade1000160Step1(): void
    {
        $sm = $this->schemaManager();
        if (!$sm->columnExists('xf_guild', 'members_bbcode') || !$sm->columnExists('xf_guild', 'members_bbcode_rendered')) {
            $sm->alterTable('xf_guild', function (Alter $table) use ($sm)
            {
                if (!$sm->columnExists('xf_guild', 'members_bbcode')) {
                    $table->addColumn('members_bbcode', 'mediumtext')->nullable();
                }
                if (!$sm->columnExists('xf_guild', 'members_bbcode_rendered')) {
                    $table->addColumn('members_bbcode_rendered', 'mediumtext')->nullable();
                }
            });
        }

        if (!$this->schemaManager()->tableExists('xf_guild_storage')) {
            $this->schemaManager()->createTable('xf_guild_storage', function (Create $table)
            {
                $table->addColumn('storage_id', 'int')->unsigned()->autoIncrement();
                $table->addColumn('guild_id', 'int')->unsigned();
                $table->addColumn('item_name', 'varchar', 200)->setDefault('');
                $table->addColumn('item_description', 'mediumtext')->nullable();
                $table->addColumn('rarity', 'enum', ['common', 'uncommon', 'rare', 'unique'])->setDefault('common');
                $table->addColumn('source_url', 'varchar', 500)->setDefault('');
                $table->addColumn('created_by_user_id', 'int')->unsigned()->setDefault(0);
                $table->addColumn('created_date', 'int')->unsigned()->setDefault(0);

                $table->addKey('guild_id');
                $table->addKey('rarity');
            });
        }

        if (!$this->schemaManager()->tableExists('xf_guild_achievement')) {
            $this->schemaManager()->createTable('xf_guild_achievement', function (Create $table)
            {
                $table->addColumn('achievement_id', 'int')->unsigned()->autoIncrement();
                $table->addColumn('guild_id', 'int')->unsigned();
                $table->addColumn('achievement_bbcode', 'mediumtext')->nullable();
                $table->addColumn('achievement_rendered', 'mediumtext')->nullable();
                $table->addColumn('display_order', 'int')->unsigned()->setDefault(0);
                $table->addColumn('created_by_user_id', 'int')->unsigned()->setDefault(0);
                $table->addColumn('created_date', 'int')->unsigned()->setDefault(0);

                $table->addKey('guild_id');
                $table->addKey('display_order');
            });
        }
    }

    public function upgrade1000250Step1(): void
    {
        $sm = $this->schemaManager();
        if (!$sm->columnExists('xf_guild', 'node_id')) {
            $sm->alterTable('xf_guild', function (Alter $table)
            {
                $table->addColumn('node_id', 'int')->unsigned()->setDefault(0);
                $table->addKey('node_id');
            });
        }
    }

    public function upgrade1000607Step1(): void
    {
        $tables = [
            'xf_guild_treasury_log',
            'xf_guild_follower_log',
            'xf_guild_reputation_log'
        ];

        foreach ($tables as $tableName) {
            if (!$this->schemaManager()->tableExists($tableName) || !$this->schemaManager()->columnExists($tableName, 'amount')) {
                continue;
            }

            // Force SIGNED int regardless of previous UNSIGNED state.
            $this->query(
                'ALTER TABLE `' . $tableName . '` MODIFY `amount` INT NOT NULL DEFAULT 0'
            );
        }
    }

    public function upgrade1000636Step1(): void
    {
        if ($this->schemaManager()->tableExists('xf_guild_important_npc')) {
            return;
        }

        $this->schemaManager()->createTable('xf_guild_important_npc', function (Create $table)
        {
            $table->addColumn('important_npc_id', 'int')->unsigned()->autoIncrement();
            $table->addColumn('guild_id', 'int')->unsigned();
            $table->addColumn('npc_name', 'varchar', 150)->setDefault('');
            $table->addColumn('npc_bbcode', 'mediumtext')->nullable();
            $table->addColumn('npc_rendered', 'mediumtext')->nullable();
            $table->addColumn('display_order', 'int')->unsigned()->setDefault(0);
            $table->addColumn('created_by_user_id', 'int')->unsigned()->setDefault(0);
            $table->addColumn('created_date', 'int')->unsigned()->setDefault(0);
            $table->addColumn('last_update', 'int')->unsigned()->setDefault(0);

            $table->addKey('guild_id');
            $table->addKey('display_order');
            $table->addKey('created_date');
        });
    }

    public function uninstallStep1(): void
    {
        $this->doDropTables($this->getTables());
    }

    protected function doCreateTables(array $tables): void
    {
        $sm = $this->schemaManager();
        foreach ($tables as $tableName => $apply) {
            if ($sm->tableExists($tableName)) {
                continue;
            }

            $sm->createTable($tableName, $apply);
        }
    }

    protected function doDropTables(array $tables): void
    {
        $sm = $this->schemaManager();
        foreach (array_keys($tables) as $tableName) {
            if (!$sm->tableExists($tableName)) {
                continue;
            }

            $sm->dropTable($tableName);
        }
    }

    /**
     * Описание схемы БД модулём (ключ = имя таблицы xf_*).
     * xf_guild — карточка и кэши (уровень, казна, последователи, members_bbcode, influence_cache как JSON после не-blob патчей и т.д.).
     * xf_guild_focus — направленности (слоты 1–4 через display_order).
     * xf_guild_member — членство и роли (leader/officer/member).
     * xf_guild_*_log — журналы операций; xf_guild_storage / achievement / important_npc — сущности гильдии.
     */
    protected function getTables(): array
    {
        return [
            'xf_guild' => function (Create $table)
            {
                $table->addColumn('guild_id', 'int')->unsigned()->autoIncrement();
                $table->addColumn('node_id', 'int')->unsigned()->setDefault(0);
                $table->addColumn('thread_id', 'int')->unsigned()->setDefault(0);
                $table->addColumn('title', 'varchar', 100);
                $table->addColumn('description', 'mediumtext')->nullable();
                $table->addColumn('description_rendered', 'mediumtext')->nullable();
                $table->addColumn('description_update_date', 'int')->unsigned()->setDefault(0);
                $table->addColumn('description_update_user_id', 'int')->unsigned()->setDefault(0);
                $table->addColumn('leader_user_id', 'int')->unsigned()->setDefault(0);
                $table->addColumn('leader_username', 'varchar', 100)->setDefault('');
                $table->addColumn('organization_level', 'tinyint')->unsigned()->setDefault(1);
                $table->addColumn('organization_size_label', 'varchar', 50)->setDefault('Small');
                $table->addColumn('member_count', 'int')->unsigned()->setDefault(0);
                $table->addColumn('followers_total', 'int')->unsigned()->setDefault(0);
                $table->addColumn('treasury_balance', 'int')->setDefault(0);
                $table->addColumn('influence_cache', 'mediumblob')->nullable();
                $table->addColumn('created_date', 'int')->unsigned()->setDefault(0);
                $table->addColumn('last_update', 'int')->unsigned()->setDefault(0);
                $table->addColumn('guild_state', 'enum', ['active', 'archived'])->setDefault('active');
                $table->addColumn('members_bbcode', 'mediumtext')->nullable();
                $table->addColumn('members_bbcode_rendered', 'mediumtext')->nullable();

                $table->addKey('thread_id');
                $table->addKey('node_id');
                $table->addKey('leader_user_id');
                $table->addKey('leader_username');
                $table->addKey('description_update_date');
                $table->addKey('description_update_user_id');
                $table->addKey('organization_level');
                $table->addKey('member_count');
                $table->addKey('followers_total');
                $table->addKey('treasury_balance');
                $table->addKey('guild_state');
            },
            'xf_guild_focus' => function (Create $table)
            {
                $table->addColumn('guild_focus_id', 'int')->unsigned()->autoIncrement();
                $table->addColumn('guild_id', 'int')->unsigned();
                $table->addColumn('focus_key', 'varchar', 50);
                $table->addColumn('display_order', 'tinyint')->unsigned()->setDefault(1);
                $table->addColumn('created_date', 'int')->unsigned()->setDefault(0);

                $table->addUniqueKey(['guild_id', 'focus_key']);
                $table->addKey('guild_id');
                $table->addKey('focus_key');
            },
            'xf_guild_member' => function (Create $table)
            {
                $table->addColumn('guild_member_id', 'int')->unsigned()->autoIncrement();
                $table->addColumn('guild_id', 'int')->unsigned();
                $table->addColumn('user_id', 'int')->unsigned();
                $table->addColumn('username', 'varchar', 100)->setDefault('');
                $table->addColumn('role', 'enum', ['leader', 'officer', 'member'])->setDefault('member');
                $table->addColumn('member_state', 'enum', ['active', 'invited', 'banned'])->setDefault('active');
                $table->addColumn('joined_date', 'int')->unsigned()->setDefault(0);
                $table->addColumn('last_update', 'int')->unsigned()->setDefault(0);

                $table->addUniqueKey(['guild_id', 'user_id']);
                $table->addKey(['guild_id', 'role']);
                $table->addKey(['guild_id', 'member_state']);
                $table->addKey('user_id');
            },
            'xf_guild_treasury_log' => function (Create $table)
            {
                $table->addColumn('treasury_log_id', 'int')->unsigned()->autoIncrement();
                $table->addColumn('guild_id', 'int')->unsigned();
                $table->addColumn('character_name', 'varchar', 100)->setDefault('');
                $table->addColumn('source_url', 'varchar', 500)->setDefault('');
                $table->addColumn('amount', 'int')->setDefault(0);
                $table->addColumn('operation_type', 'enum', ['deposit', 'withdraw'])->setDefault('deposit');
                $table->addColumn('reason', 'varchar', 255)->setDefault('');
                $table->addColumn('created_by_user_id', 'int')->unsigned()->setDefault(0);
                $table->addColumn('created_date', 'int')->unsigned()->setDefault(0);

                $table->addKey(['guild_id', 'created_date']);
                $table->addKey('operation_type');
                $table->addKey('amount');
                $table->addKey('created_by_user_id');
            },
            'xf_guild_follower_log' => function (Create $table)
            {
                $table->addColumn('follower_log_id', 'int')->unsigned()->autoIncrement();
                $table->addColumn('guild_id', 'int')->unsigned();
                $table->addColumn('character_name', 'varchar', 100)->setDefault('');
                $table->addColumn('source_url', 'varchar', 500)->setDefault('');
                $table->addColumn('amount', 'int')->setDefault(0);
                $table->addColumn('operation_type', 'enum', ['gain', 'loss'])->setDefault('gain');
                $table->addColumn('event_date_text', 'varchar', 100)->setDefault('');
                $table->addColumn('created_by_user_id', 'int')->unsigned()->setDefault(0);
                $table->addColumn('created_date', 'int')->unsigned()->setDefault(0);

                $table->addKey(['guild_id', 'created_date']);
                $table->addKey('operation_type');
                $table->addKey('amount');
                $table->addKey('created_by_user_id');
            },
            'xf_guild_reputation_log' => function (Create $table)
            {
                $table->addColumn('reputation_log_id', 'int')->unsigned()->autoIncrement();
                $table->addColumn('guild_id', 'int')->unsigned();
                $table->addColumn('region_key', 'enum', ['aramidis', 'union', 'korzus'])->setDefault('aramidis');
                $table->addColumn('character_name', 'varchar', 100)->setDefault('');
                $table->addColumn('source_url', 'varchar', 500)->setDefault('');
                $table->addColumn('amount', 'int')->setDefault(0);
                $table->addColumn('operation_type', 'enum', ['gain', 'loss'])->setDefault('gain');
                $table->addColumn('faction_name', 'varchar', 150)->setDefault('');
                $table->addColumn('created_by_user_id', 'int')->unsigned()->setDefault(0);
                $table->addColumn('created_date', 'int')->unsigned()->setDefault(0);

                $table->addKey(['guild_id', 'created_date']);
                $table->addKey('region_key');
                $table->addKey('faction_name');
                $table->addKey('operation_type');
                $table->addKey('amount');
                $table->addKey('created_by_user_id');
            },
            'xf_guild_vehicle' => function (Create $table)
            {
                $table->addColumn('vehicle_id', 'int')->unsigned()->autoIncrement();
                $table->addColumn('guild_id', 'int')->unsigned();
                $table->addColumn('vehicle_name', 'varchar', 150)->setDefault('');
                $table->addColumn('vehicle_status', 'varchar', 100)->setDefault('');
                $table->addColumn('display_order', 'int')->unsigned()->setDefault(0);
                $table->addColumn('created_date', 'int')->unsigned()->setDefault(0);
                $table->addColumn('last_update', 'int')->unsigned()->setDefault(0);

                $table->addKey('guild_id');
                $table->addKey('vehicle_name');
                $table->addKey('vehicle_status');
            },
            'xf_guild_leader_log' => function (Create $table)
            {
                $table->addColumn('leader_log_id', 'int')->unsigned()->autoIncrement();
                $table->addColumn('guild_id', 'int')->unsigned();
                $table->addColumn('old_user_id', 'int')->unsigned()->setDefault(0);
                $table->addColumn('new_user_id', 'int')->unsigned()->setDefault(0);
                $table->addColumn('changed_by_user_id', 'int')->unsigned()->setDefault(0);
                $table->addColumn('change_date', 'int')->unsigned()->setDefault(0);

                $table->addKey('guild_id');
                $table->addKey('old_user_id');
                $table->addKey('new_user_id');
                $table->addKey('changed_by_user_id');
                $table->addKey('change_date');
            },
            'xf_guild_level_rule' => function (Create $table)
            {
                $table->addColumn('level', 'tinyint')->unsigned();
                $table->addColumn('followers_min', 'int')->unsigned();
                $table->addColumn('followers_max', 'int')->unsigned()->nullable();
                $table->addColumn('size_label', 'varchar', 50)->setDefault('');

                $table->addPrimaryKey('level');
                $table->addKey('followers_min');
                $table->addKey('followers_max');
            },
            'xf_guild_description_log' => function (Create $table)
            {
                $table->addColumn('description_log_id', 'int')->unsigned()->autoIncrement();
                $table->addColumn('guild_id', 'int')->unsigned();
                $table->addColumn('old_description', 'mediumtext')->nullable();
                $table->addColumn('new_description', 'mediumtext')->nullable();
                $table->addColumn('changed_by_user_id', 'int')->unsigned()->setDefault(0);
                $table->addColumn('change_date', 'int')->unsigned()->setDefault(0);
                $table->addColumn('change_note', 'varchar', 255)->setDefault('');

                $table->addKey('guild_id');
                $table->addKey('changed_by_user_id');
                $table->addKey('change_date');
            },
            'xf_guild_storage' => function (Create $table)
            {
                $table->addColumn('storage_id', 'int')->unsigned()->autoIncrement();
                $table->addColumn('guild_id', 'int')->unsigned();
                $table->addColumn('item_name', 'varchar', 200)->setDefault('');
                $table->addColumn('item_description', 'mediumtext')->nullable();
                $table->addColumn('rarity', 'enum', ['common', 'uncommon', 'rare', 'unique'])->setDefault('common');
                $table->addColumn('source_url', 'varchar', 500)->setDefault('');
                $table->addColumn('created_by_user_id', 'int')->unsigned()->setDefault(0);
                $table->addColumn('created_date', 'int')->unsigned()->setDefault(0);

                $table->addKey('guild_id');
                $table->addKey('rarity');
            },
            'xf_guild_achievement' => function (Create $table)
            {
                $table->addColumn('achievement_id', 'int')->unsigned()->autoIncrement();
                $table->addColumn('guild_id', 'int')->unsigned();
                $table->addColumn('achievement_bbcode', 'mediumtext')->nullable();
                $table->addColumn('achievement_rendered', 'mediumtext')->nullable();
                $table->addColumn('display_order', 'int')->unsigned()->setDefault(0);
                $table->addColumn('created_by_user_id', 'int')->unsigned()->setDefault(0);
                $table->addColumn('created_date', 'int')->unsigned()->setDefault(0);

                $table->addKey('guild_id');
                $table->addKey('display_order');
            },
            'xf_guild_important_npc' => function (Create $table)
            {
                $table->addColumn('important_npc_id', 'int')->unsigned()->autoIncrement();
                $table->addColumn('guild_id', 'int')->unsigned();
                $table->addColumn('npc_name', 'varchar', 150)->setDefault('');
                $table->addColumn('npc_bbcode', 'mediumtext')->nullable();
                $table->addColumn('npc_rendered', 'mediumtext')->nullable();
                $table->addColumn('display_order', 'int')->unsigned()->setDefault(0);
                $table->addColumn('created_by_user_id', 'int')->unsigned()->setDefault(0);
                $table->addColumn('created_date', 'int')->unsigned()->setDefault(0);
                $table->addColumn('last_update', 'int')->unsigned()->setDefault(0);

                $table->addKey('guild_id');
                $table->addKey('display_order');
                $table->addKey('created_date');
            },
            'xf_guild_action_log' => function (Create $table)
            {
                $table->addColumn('action_log_id', 'int')->unsigned()->autoIncrement();
                $table->addColumn('guild_id', 'int')->unsigned()->setDefault(0);
                $table->addColumn('log_type', 'varchar', 50)->setDefault('');
                $table->addColumn('action_type', 'enum', ['add', 'update', 'delete'])->setDefault('add');
                $table->addColumn('summary', 'varchar', 500)->setDefault('');
                $table->addColumn('actor_user_id', 'int')->unsigned()->setDefault(0);
                $table->addColumn('event_date', 'int')->unsigned()->setDefault(0);

                $table->addKey(['log_type', 'event_date']);
                $table->addKey('guild_id');
                $table->addKey('actor_user_id');
                $table->addKey('event_date');
            }
        ];
    }

    public function upgrade1000662Step1(): void
    {
        if ($this->schemaManager()->tableExists('xf_guild_action_log')) {
            return;
        }

        $this->schemaManager()->createTable('xf_guild_action_log', function (Create $table)
        {
            $table->addColumn('action_log_id', 'int')->unsigned()->autoIncrement();
            $table->addColumn('guild_id', 'int')->unsigned()->setDefault(0);
            $table->addColumn('log_type', 'varchar', 50)->setDefault('');
            $table->addColumn('action_type', 'enum', ['add', 'update', 'delete'])->setDefault('add');
            $table->addColumn('summary', 'varchar', 500)->setDefault('');
            $table->addColumn('actor_user_id', 'int')->unsigned()->setDefault(0);
            $table->addColumn('event_date', 'int')->unsigned()->setDefault(0);

            $table->addKey(['log_type', 'event_date']);
            $table->addKey('guild_id');
            $table->addKey('actor_user_id');
            $table->addKey('event_date');
        });
    }

    public function upgrade1000663Step1(): void
    {
        if ($this->schemaManager()->tableExists('xf_guild_action_log')) {
            return;
        }

        $this->schemaManager()->createTable('xf_guild_action_log', function (Create $table)
        {
            $table->addColumn('action_log_id', 'int')->unsigned()->autoIncrement();
            $table->addColumn('guild_id', 'int')->unsigned()->setDefault(0);
            $table->addColumn('log_type', 'varchar', 50)->setDefault('');
            $table->addColumn('action_type', 'enum', ['add', 'update', 'delete'])->setDefault('add');
            $table->addColumn('summary', 'varchar', 500)->setDefault('');
            $table->addColumn('actor_user_id', 'int')->unsigned()->setDefault(0);
            $table->addColumn('event_date', 'int')->unsigned()->setDefault(0);

            $table->addKey(['log_type', 'event_date']);
            $table->addKey('guild_id');
            $table->addKey('actor_user_id');
            $table->addKey('event_date');
        });
    }

    /**
     * Пересчёт агрегатов гильдий после изменения логики уровня (кап по мировой известности и стадии размера).
     */
    public function upgrade1000666Step1(): void
    {
        if (!$this->schemaManager()->tableExists('xf_guild')) {
            return;
        }

        $app = \XF::app();
        /** @var \Guild\Manager\Service\Guild\Aggregator $aggregator */
        $aggregator = $app->service('Guild\Manager:Guild\Aggregator');

        foreach ($app->finder('Guild\Manager:Guild')->fetch() as $guild) {
            $aggregator->recalculateAll($guild, false);
            $aggregator->recalculateInfluenceCache($guild);
            $guild->last_update = \XF::$time;
            $guild->save();
        }
    }

    /** Уведомление о капе уровня по известности: логика в Pub\\Controller\\Guild и Aggregator (без схемы). */
    public function upgrade1000667Step1(): void
    {
    }

    /** Подписи уровень/направленности на вкладке описания — только шаблон и Pub\\Controller\\Guild. */
    public function upgrade1000668Step1(): void
    {
    }

    /** Условие подсказки «направленности» на вкладке описания (Pub\\Controller\\Guild). */
    public function upgrade1000669Step1(): void
    {
    }

    /** Стабильный релиз 1.0.0: апгрейд с Alpha 129 (схема без изменений). */
    public function upgrade1000700Step1(): void
    {
    }

    /** Патч 1.0.1 (схема без изменений). */
    public function upgrade1000701Step1(): void
    {
    }

    /** Патч 1.0.2: исправление ACP find-users (GuildCreate без json()). */
    public function upgrade1000702Step1(): void
    {
    }

    /** Патч 1.0.3: FindUsersJson возвращает массив для XF Json renderer. */
    public function upgrade1000703Step1(): void
    {
    }

    protected function seedLevelRules(): void
    {
        $db = $this->db();
        $rules = [
            [1, 1, 2, 'Small'],
            [2, 3, 4, 'Small'],
            [3, 5, 6, 'Small'],
            [4, 7, 9, 'Small'],
            [5, 10, 13, 'Small'],
            [6, 14, 18, 'Medium'],
            [7, 19, 27, 'Medium'],
            [8, 28, 36, 'Medium'],
            [9, 37, 53, 'Medium'],
            [10, 54, 75, 'Medium'],
            [11, 76, 99, 'Large'],
            [12, 100, 150, 'Large'],
            [13, 151, 215, 'Large'],
            [14, 216, 300, 'Large'],
            [15, 301, 425, 'Large'],
            [16, 426, 600, 'Legendary'],
            [17, 601, 850, 'Legendary'],
            [18, 851, 1200, 'Legendary'],
            [19, 1201, 1700, 'Legendary'],
            [20, 1701, null, 'Legendary']
        ];

        foreach ($rules as $rule) {
            $db->query(
                '
                    REPLACE INTO xf_guild_level_rule
                        (level, followers_min, followers_max, size_label)
                    VALUES
                        (?, ?, ?, ?)
                ',
                $rule
            );
        }
    }
}
