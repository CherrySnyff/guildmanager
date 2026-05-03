<?php

namespace Guild\Manager\Service\Guild;

use Guild\Manager\Entity\Guild;
use Guild\Manager\Entity\GuildMember;
use XF\Entity\User;
use XF\PrintableException;
use XF\Service\AbstractService;

/**
 * Участники гильдии (xf_guild_member): членство, роли leader/officer/member, приглашения, синхронизация member_count.
 */
class MembershipManager extends AbstractService
{
    public function syncMemberCount(Guild $guild): int
    {
        $count = (int)$this->db()->fetchOne(
            '
                SELECT COUNT(*)
                FROM xf_guild_member
                WHERE guild_id = ?
                  AND member_state = ?
            ',
            [$guild->guild_id, 'active']
        );

        $guild->member_count = $count;
        $guild->last_update = \XF::$time;
        $guild->save();

        return $count;
    }

    protected function getActiveLeaderCount(Guild $guild): int
    {
        $count = (int)$this->db()->fetchOne(
            '
                SELECT COUNT(*)
                FROM xf_guild_member
                WHERE guild_id = ?
                  AND role = ?
                  AND member_state = ?
            ',
            [$guild->guild_id, PermissionPreset::ROLE_LEADER, 'active']
        );

        if ($count === 0 && $guild->leader_user_id > 0) {
            return 1;
        }

        return $count;
    }

    protected function assertNotLastLeader(Guild $guild, GuildMember $member): void
    {
        if ($member->role !== PermissionPreset::ROLE_LEADER || $member->member_state !== 'active') {
            return;
        }

        if ($this->getActiveLeaderCount($guild) <= 1) {
            throw new PrintableException('Cannot remove or ban the last active guild leader.');
        }
    }

    public function addMember(Guild $guild, User $user, string $role = 'member', string $state = 'active'): GuildMember
    {
        /** @var \Guild\Manager\Repository\GuildMember $memberRepo */
        $memberRepo = $this->repository('Guild\Manager:GuildMember');
        /** @var GuildMember|null $member */
        $member = $memberRepo->findGuildMember($guild->guild_id, $user->user_id)->fetchOne();

        if (!$member) {
            $member = $this->em()->create('Guild\Manager:GuildMember');
            $member->guild_id = $guild->guild_id;
            $member->user_id = $user->user_id;
            $member->joined_date = \XF::$time;
        }

        $member->username = $user->username;
        $member->role = $role;
        $member->member_state = $state;
        $member->last_update = \XF::$time;
        $member->save();
        $this->syncMemberCount($guild);

        return $member;
    }

    public function removeMember(Guild $guild, User $user): bool
    {
        /** @var \Guild\Manager\Repository\GuildMember $memberRepo */
        $memberRepo = $this->repository('Guild\Manager:GuildMember');
        /** @var GuildMember|null $member */
        $member = $memberRepo->findGuildMember($guild->guild_id, $user->user_id)->fetchOne();
        if (!$member) {
            return false;
        }

        $this->assertNotLastLeader($guild, $member);
        $member->delete();
        $this->syncMemberCount($guild);
        return true;
    }

    public function setMemberRole(Guild $guild, User $user, string $role): GuildMember
    {
        /** @var \Guild\Manager\Repository\GuildMember $memberRepo */
        $memberRepo = $this->repository('Guild\Manager:GuildMember');
        /** @var GuildMember|null $existingMember */
        $existingMember = $memberRepo->findGuildMember($guild->guild_id, $user->user_id)->fetchOne();
        if (
            $existingMember
            && $existingMember->role === PermissionPreset::ROLE_LEADER
            && $existingMember->member_state === 'active'
            && $role !== PermissionPreset::ROLE_LEADER
        ) {
            $this->assertNotLastLeader($guild, $existingMember);
        }

        return $this->addMember($guild, $user, $role, 'active');
    }

    public function banMember(Guild $guild, User $user): GuildMember
    {
        /** @var \Guild\Manager\Repository\GuildMember $memberRepo */
        $memberRepo = $this->repository('Guild\Manager:GuildMember');
        /** @var GuildMember|null $existingMember */
        $existingMember = $memberRepo->findGuildMember($guild->guild_id, $user->user_id)->fetchOne();
        if ($existingMember) {
            $this->assertNotLastLeader($guild, $existingMember);
        }

        return $this->addMember($guild, $user, 'member', 'banned');
    }

    public function unbanMember(Guild $guild, User $user): GuildMember
    {
        return $this->addMember($guild, $user, 'member', 'active');
    }

    public function getUserGuildRole(Guild $guild, User $user): ?string
    {
        if ($guild->leader_user_id === $user->user_id) {
            return PermissionPreset::ROLE_LEADER;
        }

        /** @var \Guild\Manager\Repository\GuildMember $memberRepo */
        $memberRepo = $this->repository('Guild\Manager:GuildMember');
        /** @var GuildMember|null $member */
        $member = $memberRepo->findGuildMember($guild->guild_id, $user->user_id)
            ->where('member_state', 'active')
            ->fetchOne();

        if (!$member) {
            return null;
        }

        return $member->role;
    }
}
