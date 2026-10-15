<?php

/**
 * SquirrelStock: перевод по английскому ключу, см. App\Translator.
 * __('common.btn.save'), __('msg.stock.below_min', ['name' => $itemname])
 */
function __(string $key, array $params = []): string {
    return \App\Translator::get($key, $params);
}
