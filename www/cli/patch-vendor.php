<?php

/**
 * SquirrelStock: исправления в vendor/ (код ставится composer'ом и в репозиторий не входит).
 * Вызывается composer'ом после сборки автозагрузчика (composer.json, scripts.post-autoload-dump): composer install и
 * composer dump-autoload в docker/Dockerfile, tools/install/linux.sh; tools/release/build.sh берет vendor из образа.
 * Каждая правка — точная замена текста: уже наложенная пропускается, а если исходного текста нет (обновилась версия
 * пакета) — ошибка, сборка останавливается. Список правок — docs/CORE_CHANGES.md, раздел про vendor.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$vendor = dirname(__DIR__) . '/vendor/';

$patches = [
    // Ссылка вида index.php?r=... (RedirectLink, LinkList с кодированием): из запроса принимается только массив
    // [класс страницы, параметры], объекты не восстанавливаются; иное — как неверный запрос (главная страница)
    'leon-mbs/zippy/core/httprequest.php' => [
        [
            '            $p = unserialize(trim(base64_decode($_REQUEST["r"])));
',
            '            $p = unserialize(trim(base64_decode((string)$_REQUEST["r"])), [\'allowed_classes\' => false]);   // SquirrelStock: cli/patch-vendor.php
            if (!is_array($p) || array_keys($p) !== [0, 1] || !is_string($p[0]) || !(is_array($p[1]) || $p[1] === null)) {
                $this->querytype = self::QUERY_INVALID;
                return;
            }
',
        ],
    ],
    // Выпадающий список (DropDownChoice, MultipleChoice) собирается строкой HTML: названия из справочников (склады,
    // договоры, получатели…) с разметкой становились элементами страницы. В названии экранируются угловые скобки
    // (сущности вроде &nbsp; работают как раньше), в ключе (атрибут value) — еще & и кавычки
    'leon-mbs/zippy/core/html/form/dropdownchoice.php' => [
        [
            '            $option = "<option value=\"{$key}\" ";

            if ($key == $this->getValue()) {
                $option .= " selected ";
            }

            $option .= ">{$value}</option>";
',
            '            $option = "<option value=\"" . htmlspecialchars((string)$key, ENT_QUOTES) . "\" ";   // SquirrelStock: cli/patch-vendor.php

            if ($key == $this->getValue()) {
                $option .= " selected ";
            }

            $option .= ">" . str_replace([\'<\', \'>\'], [\'&lt;\', \'&gt;\'], (string)$value) . "</option>";
',
        ],
        [
            '            $js .= "$(\'#{$this->id}\').append(\'<option value=\"{$key}\">{$value}</option>\');";
        }
        return $js;',
            '            $js .= "$(\'#{$this->id}\').append(" . json_encode(\'<option value="\' . htmlspecialchars((string)$key, ENT_QUOTES) . \'">\' . str_replace([\'<\', \'>\'], [\'&lt;\', \'&gt;\'], (string)$value) . \'</option>\') . ");";   // SquirrelStock: cli/patch-vendor.php
        }
        return $js;',
        ],
    ],
    'leon-mbs/zippy/core/html/form/multiplechoice.php' => [
        [
            '            $option = "<option value=\"{$key}\" ";
',
            '            $option = "<option value=\"" . htmlspecialchars((string)$key, ENT_QUOTES) . "\" ";   // SquirrelStock: cli/patch-vendor.php
',
        ],
        [
            '            $option .= ">{$value}</option>";
            $this->getTag()->append($option);',
            '            $option .= ">" . str_replace([\'<\', \'>\'], [\'&lt;\', \'&gt;\'], (string)$value) . "</option>";
            $this->getTag()->append($option);',
        ],
    ],
];

$failed = 0;
foreach ($patches as $file => $list) {
    $path = $vendor . $file;
    $text = @file_get_contents($path);
    if ($text === false) {
        fwrite(STDERR, "patch-vendor: нет файла {$file}\n");
        $failed++;
        continue;
    }
    $changed = false;
    foreach ($list as $i => [$old, $new]) {
        if (substr_count($text, $new) == 1) {
            continue;   // уже наложена
        }
        if (substr_count($text, $old) != 1) {
            fwrite(STDERR, "patch-vendor: {$file}, правка " . ($i + 1) . ": исходный текст не найден (изменилась версия пакета?)\n");
            $failed++;
            continue;
        }
        $text = str_replace($old, $new, $text);
        $changed = true;
    }
    if ($changed && file_put_contents($path, $text) === false) {
        fwrite(STDERR, "patch-vendor: не удалось записать {$file}\n");
        $failed++;
    }
}
if ($failed > 0) {
    exit(1);
}
echo "patch-vendor: OK\n";
