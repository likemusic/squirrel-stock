/*
 * SquirrelStock: тема интерфейса — «Светлая», «Тёмная» или «Как в системе» (prefers-color-scheme).
 * Подключается обычным (не отложенным) скриптом в начале <head>: атрибут data-bs-theme ставится на <html> до отрисовки,
 * поэтому при тёмной теме не мелькает светлая страница.
 * Режим берётся из <html data-sqs-theme="light|dark|auto"> (настройка пользователя, ставит base.html); на страницах без
 * входа атрибута нет — тогда из cookie sqs_theme (последний выбор на этом компьютере), иначе «как в системе».
 * При смене системной темы в режиме «как в системе» тема меняется на лету. После каждой смены — событие sqs:theme
 * на window с фактической темой (detail: 'light' | 'dark') для тех, кто рисует цвета сам (графики).
 */
(function () {
    var MODES = ['light', 'dark', 'auto'];
    var root = document.documentElement;
    var media = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;

    var valid = function (m) {
        return MODES.indexOf(m) >= 0 ? m : null;
    };
    var fromCookie = function () {
        var m = document.cookie.match(/(?:^|;\s*)sqs_theme=([^;]*)/);
        return m ? valid(m[1]) : null;
    };
    var remember = function (m) {
        document.cookie = 'sqs_theme=' + m + '; path=/; max-age=31536000; SameSite=Lax';
    };

    var own = valid(root.getAttribute('data-sqs-theme'));
    var mode = own || fromCookie() || 'auto';

    var current = function () {
        return mode === 'auto' ? (media && media.matches ? 'dark' : 'light') : mode;
    };
    var apply = function () {
        var t = current();
        root.setAttribute('data-sqs-theme', mode);
        if (root.getAttribute('data-bs-theme') !== t) {
            root.setAttribute('data-bs-theme', t);
        }
        var e;
        try {
            e = new CustomEvent('sqs:theme', { detail: t });
        } catch (x) {
            e = document.createEvent('CustomEvent');
            e.initCustomEvent('sqs:theme', false, false, t);
        }
        window.dispatchEvent(e);
    };

    window.sqsTheme = {
        mode: function () {
            return mode;
        },
        current: current,
        set: function (m) {
            if (!valid(m)) {
                return;
            }
            mode = m;
            remember(m);
            apply();
        }
    };

    if (own) {
        remember(own);   // вход с этого компьютера в следующий раз — в той же теме
    }
    apply();
    if (media) {
        var onChange = function () {
            if (mode === 'auto') {
                apply();
            }
        };
        if (media.addEventListener) {
            media.addEventListener('change', onChange);
        } else if (media.addListener) {
            media.addListener(onChange);
        }
    }
})();
