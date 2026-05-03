<?php

namespace Guild\Manager\Entity;

use XF;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/** Транспорт гильдии (блок вкладки «Последователи»). Таблица xf_guild_vehicle. */
class GuildVehicle extends Entity
{
    protected function verifyVehicleName(&$name): bool
    {
        $name = trim((string)$name);
        if ($name === '') {
            $this->error(XF::phrase('please_enter_valid_value'), 'vehicle_name');
            return false;
        }

        return true;
    }

    protected function verifyVehicleStatus(&$status): bool
    {
        $status = (string)$status;
        if ($status !== 'free' && $status !== 'busy') {
            $this->error(XF::phrase('please_enter_valid_value'), 'vehicle_status');
            return false;
        }

        return true;
    }

    public static function getStructure(Structure $structure): Structure
    {
        $structure->table = 'xf_guild_vehicle';
        $structure->shortName = 'Guild\Manager:GuildVehicle';
        $structure->primaryKey = 'vehicle_id';
        $structure->columns = [
            'vehicle_id' => ['type' => self::UINT, 'autoIncrement' => true],
            'guild_id' => ['type' => self::UINT, 'required' => true],
            'vehicle_name' => ['type' => self::STR, 'maxLength' => 150, 'default' => '', 'verify' => 'verifyVehicleName'],
            'vehicle_status' => ['type' => self::STR, 'maxLength' => 100, 'default' => 'free', 'verify' => 'verifyVehicleStatus'],
            'display_order' => ['type' => self::UINT, 'default' => 0],
            'created_date' => ['type' => self::UINT, 'default' => 0],
            'last_update' => ['type' => self::UINT, 'default' => 0],
        ];
        $structure->getters = [];
        $structure->relations = [];

        return $structure;
    }
}
