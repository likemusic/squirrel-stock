# SquirrelStock

Lightweight web-based inventory and warehouse management in PHP. Keeps stock the way a squirrel keeps its supplies: knows what is stored where, and stays quick.

**SquirrelStock is an unofficial fork of [Zippy](https://zippy.com.ua/) ([leon-mbs/zstore](https://github.com/leon-mbs/zstore)) by Leon, MIT License.** It is not affiliated with or endorsed by the original author. The original README is kept in [README.zippy.md](README.zippy.md) (Ukrainian).

## Why a fork

The fork is developed for charities and non-profits that hand out supplies and equipment free of charge (first user: a children's hospice). Planned changes:

- fixes for stock accounting reliability (double posting, batch order by expiry date, import date and others);
- interface translation with English keys (`uk`, `ru`, later `be` and `en`);
- full offline operation (no external scripts or CDNs);
- equipment lending: who has each unit now, contracts, returns, repairs;
- reports for free issue of supplies: write-off act, recipient × month, item and recipient movements for a period;
- regression tests.

Status: early stage, the code currently equals upstream commit `c74df59f4` (13.10.2026).

## Русский

SquirrelStock — неофициальный форк складской программы Zippy (leon-mbs/zstore, лицензия MIT) для благотворительных организаций, которые бесплатно выдают расходные материалы и оборудование. Первый пользователь — детский хоспис. В планах: исправления надёжности учёта, перевод интерфейса на русский (ключи на английском), работа без интернета, учёт выдачи оборудования по договорам, отчёты для бухгалтерии, автотесты.

## License

MIT, see [LICENSE](LICENSE). Copyright (c) 2018 Leon (original Zippy code), (c) 2026 SquirrelStock contributors.
