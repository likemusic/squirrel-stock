<?php

namespace App\SQS;

use App\Entity\Message;

/**
 * SquirrelStock: права на прикрепленный файл (таблица files). В Zippy loadfile.php отдавал файл по номеру любому вошедшему,
 * а удаление в окне документа удаляло файл с любым номером: права проверялись только скрытием кнопки.
 * Смотреть — тот, кто видит объект, к которому прикреплен файл:
 *  - документ — ACL::checkShowDoc (тип документа в правах роли, «только свои»);
 *  - контрагент, договор, сотрудник — право просмотра справочника;
 *  - задача и проект — модуль «Задачи» у пользователя, заметка — модуль «Заметки»;
 *  - прочее (чат) — только загрузивший.
 * Удалять — загрузивший или автор документа (как кнопка в интерфейсе Zippy), если он видит объект. admins — всё.
 */
class FileAccess
{
    private const REFS = [Message::TYPE_CUST => 'CustomerList', Message::TYPE_CONTRACT => 'ContractList', Message::TYPE_EMP => 'EmployeeList'];
    private const MODULES = [Message::TYPE_ISSUE => 'issue', Message::TYPE_PROJECT => 'issue', Message::TYPE_NOTE => 'note', 0 => 'note'];

    /** Запись файла без содержимого или null */
    public static function file(int $fileId): ?array {
        $row = \ZDB\DB::getConnect()->GetRow('select file_id, item_id, item_type, user_id from files where file_id = ' . $fileId);
        return $row ?: null;
    }

    public static function canView(array $f, ?\App\Entity\User $user = null): bool {
        $user ??= \App\System::getUser();
        if ($user->user_id == 0) {
            return false;
        }
        if ($user->rolename == 'admins') {
            return true;
        }
        $type = (int)$f['item_type'];
        if ($type == Message::TYPE_DOC) {
            $doc = \App\Entity\Doc\Document::load((int)$f['item_id']);
            return $doc != null && \App\ACL::checkShowDoc($doc, true, false, $user->user_id);
        }
        if (isset(self::REFS[$type])) {
            $meta = (int)\ZDB\DB::getConnect()->GetOne("select meta_id from metadata where meta_type = 4 and meta_name = '" . self::REFS[$type] . "'");
            return $meta > 0 && in_array($meta, array_map('intval', explode(',', (string)$user->aclview)), true);
        }
        if (isset(self::MODULES[$type]) && in_array(self::MODULES[$type], explode(',', (string)$user->modules), true)) {
            return true;
        }
        return (int)$f['user_id'] == $user->user_id;
    }

    public static function canDelete(array $f, ?\App\Entity\User $user = null): bool {
        $user ??= \App\System::getUser();
        if (!self::canView($f, $user)) {
            return false;
        }
        if ($user->rolename == 'admins' || (int)$f['user_id'] == $user->user_id) {
            return true;
        }
        if ((int)$f['item_type'] == Message::TYPE_DOC) {
            $doc = \App\Entity\Doc\Document::load((int)$f['item_id']);
            return $doc != null && $doc->user_id == $user->user_id;
        }
        return false;
    }
}
