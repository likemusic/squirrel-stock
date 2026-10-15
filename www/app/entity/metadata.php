<?php

namespace App\Entity;

/**
 * Клас-сущность  метаданные
 *
 * @table=metadata
 * @keyfield=meta_id
 */
class MetaData extends \ZCL\DB\Entity
{
    public const METATYPE_DOC = 1;
    public const METATYPE_REP = 2;
    public const METATYPE_REG = 3;
    public const METATYPE_REF = 4;
    public const METATYPE_SER = 5;

    public static function getNames() {
        $list = array();
        $list[1] = __('common.dokument');
        $list[2] = __('common.zvit');
        $list[3] = __('common.zhurnal');
        $list[4] = __('common.dovidnyk');
        $list[5] = __('common.servisna_storinka');

        return $list;
    }

}
