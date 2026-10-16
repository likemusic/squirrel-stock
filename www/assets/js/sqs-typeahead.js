/*
 * SquirrelStock: гонка в подсказке (bootstrap3-typeahead из фреймворка Zippy, ZIPPY_GAPS п. 3).
 * Подсказка отправляет запрос на каждое нажатие клавиши, а Enter/Tab выбирает активный вариант из списка, который
 * показан сейчас. При быстром вводе «X-039» и Enter до ответа на полный код выбирался вариант из списка по «X-03»
 * (X-030); ответы на старые запросы, пришедшие позже новых, тоже перерисовывали список.
 * Слой поверх прототипа Typeahead (vendor не правится — его перезапишет composer):
 *  - ответ, пришедший не на последний запрос, отбрасывается;
 *  - Enter/Tab, нажатые, пока показан список не для текущего текста поля, откладываются и выбирают первый вариант,
 *    когда придет ответ на этот текст (если текст до того изменился — отложенный выбор отменяется); щелчок мышью
 *    по варианту выбирает его сразу.
 * Еще: вариант списка вставляется как HTML (render → .html(highlighter(...))), а highlighter Zippy возвращает название
 * из справочника как есть — разметка в названии позиции или получателя становилась элементами страницы. Результат
 * highlighter экземпляра экранируется (у Zippy это просто текст, видимое название не меняется).
 * Подключается в templates/pages/base.html после бандла фреймворка.
 */
(function ($) {
    var T = $ && $.fn && $.fn.typeahead && $.fn.typeahead.Constructor;
    if (!T) {
        return;
    }
    var P = T.prototype, lookup = P.lookup, select = P.select, keyup = P.keyup, render = P.render;
    var text = function (t) { return t.$element.val() || t.$element.text() || ''; };

    P.lookup = function () {
        if (!this.sqsWrapped && $.isFunction(this.source)) {
            var self = this, source = this.source;
            self.sqsSeq = 0;
            this.source = function (query, done) {
                var seq = ++self.sqsSeq;
                return source.call(this, query, function (items) {
                    if (seq !== self.sqsSeq) {
                        return;   // ответ на устаревший запрос
                    }
                    self.sqsShown = query;
                    var r = done(items);
                    if (self.sqsPending !== undefined) {
                        var wanted = self.sqsPending;
                        self.sqsPending = undefined;
                        if (self.shown && wanted === query && query === text(self)) {
                            select.call(self);
                        }
                    }
                    return r;
                });
            };
            this.sqsWrapped = true;
        }
        return lookup.apply(this, arguments);
    };

    P.render = function () {
        if (!this.sqsEscaped) {
            var h = this.highlighter;
            this.highlighter = function () {
                return $('<div>').text(String(h.apply(this, arguments))).html();
            };
            this.sqsEscaped = true;
        }
        return render.apply(this, arguments);
    };

    // Enter/Tab: щелчок мышью по варианту не откладывается — пользователь выбрал то, что видит
    P.keyup = function (e) {
        if ((e.keyCode === 13 || e.keyCode === 9) && this.shown && this.sqsWrapped && this.sqsShown !== text(this)) {
            this.sqsPending = text(this);   // список устарел: выбрать, когда придет ответ на текущий текст
            return;
        }
        return keyup.apply(this, arguments);
    };
})(window.jQuery);
