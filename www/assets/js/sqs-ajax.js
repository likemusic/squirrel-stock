/*
 * SquirrelStock: ответ AJAX Zippy (updateFromAjax из бандла фреймворка) перестраивает выпадающий список строкой
 * "<option value=\"" + key + "\">" + value + "</option>", то есть названия из справочников (договоры получателя и т. п.)
 * вставлялись как HTML. Перед обработкой ответа ключи и названия вариантов экранируются; остальное — без изменений.
 * Слой поверх функции фреймворка (vendor не правится — его перезапишет composer).
 * Подключается в templates/pages/base.html после бандла фреймворка.
 */
(function () {
    var orig = window.updateFromAjax;
    if (typeof orig !== 'function') {
        return;
    }
    // название: только угловые скобки (сущности вроде &nbsp; в названиях работают, как при обычной отрисовке списка);
    // ключ стоит в атрибуте value="..." — еще & и кавычка
    var text = function (s) {
        return String(s === null || s === undefined ? '' : s).replace(/</g, '&lt;').replace(/>/g, '&gt;');
    };
    var attr = function (s) {
        return text(String(s === null || s === undefined ? '' : s).replace(/&/g, '&amp;')).replace(/"/g, '&quot;');
    };
    window.updateFromAjax = function (str) {
        var arr;
        try {
            arr = JSON.parse(str);
        } catch (e) {
            return orig.apply(this, arguments);
        }
        if (Array.isArray(arr)) {
            arr.forEach(function (c) {
                if (c && c.type === 'DropDownChoiceOptions' && Array.isArray(c.data)) {
                    c.data.forEach(function (o) {
                        o.key = attr(o.key);
                        o.value = text(o.value);
                    });
                }
            });
            str = JSON.stringify(arr);
        }
        return orig.call(this, str);
    };
})();
