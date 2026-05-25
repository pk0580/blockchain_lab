# GUIDE.md — учебник по блокчейну на примере `blockchain-lab`

Этот документ — пошаговый учебник. Цель: человек, никогда не работавший с блокчейном, должен после прочтения понимать **что такое блокчейн, как устроены сети уровня L1/L2, как программа на стороне сервера принимает входящие платежи и отправляет исходящие** — и где конкретно в этом репозитории живёт каждая из этих идей.

Структура: 12 уроков от азов к нюансам. Каждый урок построен по схеме «аналогия → точное определение → класс/метод в коде». Если в коде есть подвох, я отмечаю его явно.

Образовательный слой приложения (Inertia-уроки, песочницы, `Modules/Education`) в этом учебнике не описывается — он — про *как учить других*. Здесь же — *как работает сама платёжная инфраструктура*.

---

## Оглавление

1. [Что такое блокчейн и зачем он нужен](#урок-1--что-такое-блокчейн)
2. [Ключи, адреса и HD-кошельки (BIP-32/39/44)](#урок-2--ключи-адреса-и-hd-кошельки)
3. [Транзакция: UTXO (Bitcoin) vs аккаунт-модель (EVM)](#урок-3--транзакция-utxo-против-аккаунта)
4. [L1, L2 и семейства сетей](#урок-4--l1-l2-и-семейства-сетей)
5. [Сканирование цепи и обнаружение поступлений](#урок-5--сканирование-цепи-и-обнаружение-поступлений)
6. [Подтверждения и финализация](#урок-6--подтверждения-и-финализация)
7. [Реорганизации (reorg) и почему их надо «откатывать»](#урок-7--реорганизации-цепи)
8. [Двойная бухгалтерия (Ledger) и идемпотентность учёта](#урок-8--двойная-бухгалтерия-ledger)
9. [Комиссия (fee) в Bitcoin и EIP-1559 в EVM](#урок-9--комиссия-fee)
10. [Вывод средств: nonce, сборка, подпись, broadcast](#урок-10--вывод-средств-withdrawal)
11. [Застрявшие транзакции, RBF и пауза сети](#урок-11--застрявшие-транзакции-и-rbf)
12. [Надёжность: idempotency, webhook outbox, health-checks](#урок-12--надёжность-и-наблюдаемость)

---

## Урок 1 — Что такое блокчейн

### Аналогия

Представьте общую тетрадь, в которой *тысячи* людей по всему миру одновременно пишут платежи. Никто никому не доверяет, но каждый видит всё, что пишут другие. Чтобы тетрадь не превратилась в хаос, у участников есть правило: писать можно только страницами целиком, каждая страница ссылается на предыдущую, а решение «какая страница следующая» принимается всем кругом по математическим правилам.

### Точное определение

**Блокчейн** — это распределённый журнал транзакций, разбитый на блоки. Каждый блок содержит:

- **header** — высоту (`height`), хэш предыдущего блока (`previousblockhash`), временную метку, корень дерева транзакций;
- **payload** — список транзакций;
- **hash** — хэш самого блока (вычисляется детерминированно).

Свойства, которые из этого вытекают:

- **Цепочка**: каждый блок ссылается на родителя `parent_hash`. Изменить старый блок → изменится его хэш → следующий блок перестанет указывать на него → сломается вся цепочка. Поэтому история практически неизменяема.
- **Согласие (consensus)**: разные узлы могут на пару секунд иметь разные «вершины» (`head`). Правило выбора одной канонической цепи — это и есть «алгоритм консенсуса» (PoW у Bitcoin, PoS у Ethereum).
- **Открытость**: любая нода может загрузить всю историю и проверить её сама — никакой «центральный сервер» не нужен.

### В коде

В нашем проекте блок — это сущность `Block` модуля `BlockIngestion`. Минимальный набор полей, который мы храним, виден в миграции `src/app/Modules/BlockIngestion/Infrastructure/Persistence/migrations/2026_05_23_000001_create_block_ingestion_tables.php`. Сам блок-агрегат:

- `App\Modules\BlockIngestion\Domain\Entity\Block::ingest()` — фабричный метод, который создаёт блок из данных RPC.

«Высота» и «хэш» — это **value objects**:

- `App\Modules\Network\Domain\ValueObject\BlockHeight`
- `App\Modules\Network\Domain\ValueObject\BlockHash`
- `App\Modules\Network\Domain\ValueObject\TxHash`

Что важно: мы **не храним и не валидируем** криптографические заголовки блоков сами. Мы доверяем полному узлу (`bitcoind`, `geth`), к которому ходим по RPC. Этот узел сам проверил подписи, хэши и консенсус — наша роль ниже по стеку.

---

## Урок 2 — Ключи, адреса и HD-кошельки

### Аналогия

Замок и ключ. Адрес — это замочная скважина: на него можно посылать деньги, его можно показать кому угодно. Приватный ключ — это уникальный ключ, который открывает только этот замок. Подписать транзакцию = доказать, что ключ у тебя, не показав сам ключ.

### Криптография за кулисами

В Bitcoin и EVM (Ethereum/Polygon/Arbitrum/...) и Tron используется одна и та же эллиптическая кривая **secp256k1**:

1. **Приватный ключ** — случайное 256-битное число.
2. **Публичный ключ** — точка на кривой = `private_key * G` (где `G` — известная всем «генераторная точка»).
3. **Адрес** — короткое представление публичного ключа:
   - **Bitcoin (BIP-84, P2WPKH bech32)**: `bech32(0, RIPEMD160(SHA256(pubkey)))` — адрес вида `bc1q...`.
   - **EVM**: `last20bytes(keccak256(uncompressed_pubkey_no_prefix))` + кодировка `0x...` с контрольной суммой EIP-55.
   - **Tron**: `base58check(0x41 || last20bytes(keccak256(pubkey)))` — адрес вида `T...`.

Реализация каждого варианта в Go signing-svc:

| Файл | Что делает |
|---|---|
| `signing-svc/internal/chain/bitcoin.go::bitcoinAdapter.Address` | bech32-кодировка P2WPKH |
| `signing-svc/internal/chain/ethereum.go::evmAdapter.Address` | `keccak256` + EIP-55 контрольная сумма |
| `signing-svc/internal/chain/tron.go::tronAdapter.Address` | `keccak256` → `0x41`-префикс → base58check |

### HD-кошельки: один сид — много адресов

Хранить миллион отдельных приватных ключей бессмысленно — теряются, утекают. Стандарты BIP-32 / BIP-39 / BIP-44 решают это:

- **BIP-39**: 12 или 24 слова (мнемоника) → 64 байта seed-материала (`PBKDF2-HMAC-SHA512`, 2048 итераций).
- **BIP-32**: из 64 байт получаем **мастер-ключ** + chain code. Из них **детерминированно** выводим любой дочерний ключ по индексу. Алгоритм: `HMAC-SHA512(chain_code, parent_pubkey || index)`.
- **BIP-44**: соглашение по семантике пути:
  ```
  m / purpose' / coin_type' / account' / change / address_index
  ```
  Апостроф (`'` или `h`) — это «hardened» шаг (`index += 0x80000000`): дочерний ключ нельзя вывести только из родительского публичного — нужен приватный. Это критично для безопасности — если утечёт один pubkey, не утекут «соседние» аккаунты.

В нашем коде:

- **Семейство → путь** строит `App\Modules\Address\Domain\Service\DerivationPathFactory::buildExternal()`. Конкретные шаблоны:
  - Bitcoin: `m/84'/0'/0'/0/{i}` (BIP-84 для bech32)
  - EVM: `m/44'/60'/0'/0/{i}`
  - Tron: `m/44'/195'/0'/0/{i}`
- Числа `0/60/195` называются **SLIP-44 coin types** — общесогласованные идентификаторы сетей.

### Почему ключи живут в отдельном сервисе

Главный принцип: **приватный ключ не должен попадать в PHP-процесс**. Утечка PHP-процесса = утечка ключей всех клиентов.

Поэтому в проекте есть отдельный Go-сервис `signing-svc`, и ничто, кроме него, ключей не видит.

- Laravel хранит лишь **ссылку** на сид: `App\Modules\Address\Domain\ValueObject\HdSeedReference` — это непрозрачная строка (UUID-подобная). Сама мнемоника хранится в файле в `signing-svc`, зашифрована **XChaCha20-Poly1305** ключом из конфига — см. `signing-svc/internal/seed/store.go::FileStore`.
- Создание сида: `POST /v1/seeds` (`signing-svc/internal/server/handlers.go::createSeed`) → внутри `seed.Generate()` (`signing-svc/internal/seed/seed.go`) — генерация 256-бит энтропии → мнемоника BIP-39 → мастер-ключ BIP-32.
- Деривация адреса: `POST /v1/addresses/derive` → `chain.DerivePath(master, "m/.../...")` → adapter.Address(child).

PHP вызывает это через `App\Modules\Network\Infrastructure\Signer\HttpSigningClient::deriveAddress()`.

### Ссылка на ключ

В таблице `hd_seeds` (PHP-сторона) лежит **не сам сид, а его учётная карточка**: какой-то сид с такой-то непрозрачной `reference` существует в signing-svc, создан тогда-то, опционально привязан к семейству сети. Ни мнемоники, ни мастер-ключа, ни одного байта секретного материала тут нет — только метаданные. Это и есть «ссылка на ключ», которой PHP оперирует вместо самого ключа.

Минимальный набор полей таблицы (см. `App\Modules\Address\Infrastructure\Persistence\Eloquent\Models\HdSeedModel` и миграцию `2026_05_22_000001_create_address_tables.php`):

```
id          UUID         (PK, наш внутренний идентификатор)
reference   VARCHAR(64)  (та самая «непрозрачная строка», по ней signing-svc находит файл .sealed)
family      VARCHAR|NULL (bitcoin | evm | tron, либо NULL = универсальный сид для нескольких семейств)
created_at  TIMESTAMPTZ
```

Аналогия: у вас в сейфе банка лежит мешок с ключами от 1000 квартир (это `signing-svc`, файл `<reference>.sealed`). В вашем блокноте записано: «в сейфе лежит мешок с тегом `prod-hot-001`, выдан тогда-то, для Ethereum» (это строка в `hd_seeds`). Запись в блокноте бесполезна для вора — без мешка она ничего не открывает. Но она нужна вам, чтобы знать, к какому мешку обращаться, когда понадобится новый адрес.

Сиды создаются одним из двух путей:

1. **CLI-команда** (типичный prod-сценарий первоначальной настройки):
   ```bash
   php artisan address:seed:create prod-hot-001 --family=evm
   # или с импортом существующей мнемоники из бэкапа:
   php artisan address:seed:create prod-hot-001 --family=evm --mnemonic="...12 words..."
   ```
   Команда определена в `App\Modules\Address\UI\Console\CreateHdSeedCommand` и просто вызывает Action.
2. **Программный вызов** `CreateHdSeedAction` из любого тестового сидера или скрипта инициализации.

**Что делает `App\Modules\Address\Application\UseCase\CreateHdSeed\CreateHdSeedAction::handle()`** (полная последовательность):

1. Завернуть строку `reference` в VO `HdSeedReference` — проверка charset/длины ещё до похода в сеть.
2. `SigningClient::ensureSeed(reference, importMnemonic)` → HTTP `POST /v1/seeds` в signing-svc. Это **источник правды** о том, существует ли такой сид. Идемпотентно: если уже есть — `created=false`, ничего не пересоздаётся; если нет — генерируется новая мнемоника BIP-39 (или импортируется переданная) и кладётся в зашифрованный файл `<reference>.sealed`.
3. После успешного ответа signing-svc — создать доменный агрегат `HdSeed::create($id, $reference, $family, now)` и сохранить через репозиторий в строке в `hd_seeds`.
4. После COMMIT поднять событие `HdSeedCreated`.

**Почему именно такой порядок «сначала signing-svc, потом БД»**. Если бы сначала писали в БД, а сервис подписи упал — у PHP осталась бы карточка на сид, которого нет: при попытке деривации `signing-svc` вернёт 404. Обратный порядок (как у нас) даёт безобидный сценарий: signing-svc сохранил сид, БД не записалась → следующий ретрай с тем же `reference` пройдёт благодаря идемпотентности `ensureSeed` и доведёт операцию до конца.

После этого таблица `hd_seeds` пополнилась одной строкой — и теперь у `GenerateAddressAction` есть из чего выводить адреса.

### Создание адреса

`App\Modules\Address\Application\UseCase\GenerateAddress\GenerateAddressAction::handle()`. Алгоритм:

1. Загрузить `HdSeed` из БД (по `id`). Если строки нет — `HdSeedNotFoundException`. Если у сида указано `family`, и оно не совпадает с запрошенным — отдельная проверка `supportsFamily()`.
2. Под advisory-lock в Postgres выделить следующий индекс деривации (`AddressRepository::nextDerivationIndex`).
3. Собрать путь через `DerivationPathFactory`.
4. Попросить signing-svc вывести адрес (`SigningClient::deriveAddress(reference, family, path)`).
5. Сохранить запись `Address` + поднять событие `AddressGenerated` после COMMIT.

Шаг 2 важен: без advisory-lock два параллельных запроса с одинаковым `(seed, family)` могли бы получить *одинаковый* индекс и потом одинаковый адрес — и испортить уникальность. См. [Урок 10](#урок-10--вывод-средств-withdrawal) — там тот же приём, но для nonce.

---

## Урок 3 — Транзакция: UTXO против аккаунта

Существуют две принципиально разные модели «что такое транзакция и баланс». Если их не различать, дальше будет каша.

### UTXO-модель (Bitcoin)

UTXO = **Unspent Transaction Output**, «непотраченный выход транзакции».

Аналогия: у вас в кошельке не «100 рублей баланса», а **физические купюры разного номинала**. Когда нужно заплатить 30 — даёте купюру на 50, получаете сдачу 20. Купюра «потрачена», вместо неё родились две новые: «30 продавцу» и «20 вам сдачей».

> **Единицы измерения.** Внутри Bitcoin никакие дроби не существуют — все суммы хранятся целыми числами в наименьшей единице, которая называется **сатоши** (satoshi, sat) — в честь псевдонима создателя Bitcoin. Соотношение фиксировано: **1 BTC = 100 000 000 сатоши (10⁸)**, то есть один сатоши = 0.00000001 BTC. «0.5 BTC» — это просто человекочитаемая запись числа «50 000 000 сатоши». Когда вы видите в коде `546 сатоши` (это «dust threshold» — порог пыли, ниже которого UTXO нет смысла создавать, потому что комиссия за её будущую трату превысит её саму) или `sat/vbyte` (комиссия в сатоши за виртуальный байт транзакции) — речь именно об этой единице. У EVM-сетей логика та же, но единица называется **wei**: 1 ETH = 10¹⁸ wei. У Tron — **sun**: 1 TRX = 10⁶ sun. Хранение в целых числах принципиально: float-арифметика теряет точность, а тут речь о деньгах.

В Bitcoin: транзакция = `inputs[]` (ссылки на старые UTXO, которые мы тратим) + `outputs[]` (новые UTXO, которые рождаются). Баланс адреса = сумма всех UTXO, в которых `scriptPubKey` указывает на этот адрес и которые ещё никем не потрачены.

Следствия:

- Адрес может «получить» деньги много раз — каждый платёж создаёт отдельный UTXO.
- Чтобы потратить, нужно собрать UTXO и приложить подпись по каждому из них.
- Понятия «nonce» нет — порядок транзакций ничем не определяется, главное — нельзя дважды потратить тот же UTXO.

В коде:

- **Чтение блока**: `App\Modules\BlockIngestion\Infrastructure\BlockSource\BitcoinCoreBlockSource::fetchBlockAt()` — вызывает `getblockhash` + `getblock` через RPC. Для каждой `vout` (output) пытается вытащить адрес и сумму.
- **Сборка платежа**: `App\Modules\Withdrawal\Infrastructure\TxBuilder\BitcoinTxBuilder::build()`:
  1. `listunspent 1 9999 [hot_address]` — список доступных UTXO горячего кошелька.
  2. **Greedy largest-first**: сортируем UTXO по убыванию суммы, берём пока `Σinputs ≥ amount + fee`.
  3. Размер транзакции (vsize) оценивается эвристикой: 110 байт на вход + 34 на выход + 10 на заголовок.
  4. Сдача = `Σinputs − amount − fee`. Если меньше 546 сатоши (dust threshold) — она «съедается» комиссией.
  5. `createrawtransaction` → unsigned hex.

Подвох: значения сумм в RPC Bitcoin приходят как float (BTC). Конвертация в сатоши делается через `bcmath` (`btcToSatoshi`), а не приведением к float — float бы потерял точность на больших суммах.

### Аккаунт-модель (EVM, Tron)

Аналогия: банковская карта. Есть «счёт» (= адрес), на нём один **баланс**. Перевод = `balance[sender] -= X; balance[receiver] += X` (плюс комиссия). Никаких «купюр» нет.

В Ethereum транзакция содержит:

- `from`, `to`, `value` (в wei = 10⁻¹⁸ ETH)
- `nonce` — **порядковый номер** транзакции этого `from`-адреса. Сеть строго проверяет: транзакция с `nonce=N` будет принята только если в этой сети от этого адреса уже подтверждено ровно `N` транзакций.
- `gas_limit`, `max_fee_per_gas`, `max_priority_fee_per_gas` — про комиссию см. [Урок 9](#урок-9--комиссия-fee).
- `chain_id` — защита от replay-атаки (одна и та же подпись не должна работать в Ethereum и в Polygon).

Следствия:

- **Nonce — это единственная очередь**. Если в mempool лежит `nonce=5`, а вы хотите послать `nonce=7`, она будет «висеть» в pending, пока не пройдёт 6.
- Аккаунту не нужно собирать «купюры» — баланс просто проверяется и уменьшается.

В коде:

- **Выделение nonce**: `App\Modules\Withdrawal\Infrastructure\Nonce\EloquentNonceAllocator::allocate()`. Алгоритм:
  1. `BEGIN`
  2. `pg_advisory_xact_lock(crc32(chain_id + ':' + hot_address))` — сериализовать выделение для пары (сеть, горячий адрес) на уровне Postgres.
  3. `SELECT MAX(nonce) WHERE chain_id=? AND hot_address=?`.
  4. Если `MAX` есть → `next = MAX + 1`. Если нет — спросить ноду: `eth_getTransactionCount(addr, "latest")` (`EvmNonceProbe`).
  5. `INSERT` в `nonce_assignments` с UNIQUE constraint на `(chain_id, hot_address, nonce)`.
  6. `COMMIT`. Если случился `UNIQUE violation` (кто-то параллельно — например, человек из Metamask — отправил с того же адреса) — кидаем `NonceAllocationFailedException::collision` и пусть слой выше решает.

- **Сборка EVM-транзакции**: `App\Modules\Withdrawal\Infrastructure\TxBuilder\EvmTxBuilder::build()`. Здесь нюанс: **PHP не делает RLP-сериализацию сам**. Он собирает все поля (`chain_id`, `nonce`, `max_fee_per_gas_wei`, `max_priority_fee_per_gas_wei`, `gas_limit`, `to`, `value_wei`) в `signingExtras` и кладёт в `rawHex` детерминированный плейсхолдер `tx-pending-{chainId}-{nonce}`. Реальный RLP делает Go-сервис подписи. Так PHP остаётся в стороне от криптографии.

---

## Урок 4 — L1, L2 и семейства сетей

### Что такое L1 и L2

**L1 (Layer 1)** — базовая блокчейн-сеть, у которой есть собственный консенсус, собственные валидаторы/майнеры и собственный токен (Bitcoin, Ethereum, Tron).

**L2 (Layer 2)** — сеть, которая «живёт поверх» L1: она использует L1 как точку финальной правды (`settlement layer`), но обрабатывает большинство транзакций у себя — быстрее и дешевле. Примеры: Polygon (PoS), Arbitrum (Optimistic Rollup), Optimism, Base. Все они построены так, что выглядят для приложения **как обычные EVM-сети**: те же RPC-методы, тот же формат адреса (`0x...`), та же модель аккаунта.

Главная мысль для разработчика приложения: **с точки зрения интеграции L2 — это просто ещё один EVM**. Меняется `chain_id`, RPC URL, иногда характеристики комиссии и время блока. Бизнес-логика не меняется.

### Как это структурировано в коде

Ключевой принцип архитектуры: **семейство** (`family`) — это «контракт работы с сетью», а **сеть** (`chain`) — это конкретная инстанция семейства.

- `App\Modules\Network\Domain\ValueObject\ChainFamily` — перечисление: `Bitcoin | Evm | Tron`.
- `App\Modules\Network\Domain\Contract\ChainAdapter` — интерфейс с методами `currentHead()`, `broadcast(SignedRawTx)`, `feeEstimator()`, `addressValidator()`. По одному адаптеру на семейство:
  - `App\Modules\Network\Infrastructure\Adapter\BitcoinAdapter`
  - `App\Modules\Network\Infrastructure\Adapter\EvmAdapter`
- `App\Modules\Network\Domain\Entity\Chain` — агрегат **зарегистрированной сети**. Содержит `ChainId`, `ChainName`, `ChainFamily`, `NativeCurrency` (`BTC`, `ETH`, `MATIC`...), `ConfirmationRequirement` и список `RpcEndpoint`. Метод `Chain::register()` гарантирует инвариант «как минимум один RPC-эндпоинт».

Добавить новую EVM-сеть = добавить запись в БД через `RegisterChainAction` (см. `App\Modules\Network\Application\UseCase\RegisterChain\RegisterChainAction`). **Никакого нового PHP-кода не нужно** — адаптер семейства EVM возьмётся автоматически. Это и есть выгода clean-architecture-разделения: бизнес-логика не знает про конкретную сеть, она работает через семейный контракт.

Добавить *новое семейство* (например, Solana) = новый класс адаптера + новые контракты в `ChainAdapterRegistry`. Это редкая, тяжёлая операция — потому что Solana работает иначе и на уровне ключей (Ed25519, не secp256k1), и на уровне модели (account, но с rent), и на уровне сериализации.

### ConfirmationRequirement

`App\Modules\Network\Domain\ValueObject\ConfirmationRequirement` несёт **два** числа:

- `requiredConfirmations` — сколько подтверждений нужно, чтобы платёж считался «достаточно надёжным» для применения к балансу. Типичные значения: Bitcoin = 6, Ethereum = 12, Polygon = 64.
- `maxReorgDepth` — насколько глубоко мы вообще *согласны* откатывать историю автоматически. Глубже = «человеческая тревога» (см. [Урок 7](#урок-7--реорганизации-цепи)).

Инвариант: `maxReorgDepth >= requiredConfirmations`. Иначе подтверждённая транзакция оказалась бы «уже неотменимой по нашей логике», но при этом её *могло* откатить сеть — нельзя.

---

## Урок 5 — Сканирование цепи и обнаружение поступлений

### Зачем нам сканер

Блокчейн нам ничего «не пушит». Чтобы узнать, что **на наш адрес** пришли деньги, мы должны сами регулярно ходить к ноде и спрашивать: «какие сейчас блоки? что в них есть для нас?»

Это называется **scanner** или **ingestion**. У нас он живёт в модуле `BlockIngestion`.

### Курсор

Чтобы не сканировать одни и те же блоки повторно, мы храним **курсор** на сеть:

`App\Modules\BlockIngestion\Domain\Entity\ScanCursor`:

- `lastScannedHeight` — самый высокий блок, который мы уже обработали.
- `lastSeenHeadHeight` — какая вершина была у сети на момент последнего опроса.
- `hasPendingBlocks()` = `head > lastScanned` — есть ли что догнать.

Инвариант: `lastScannedHeight <= lastSeenHeadHeight`. Курсор продвигается **строго по одному блоку** (`advanceTo(+1)`), что упрощает обработку реоргов.

### Цикл сканирования

`App\Modules\BlockIngestion\Application\UseCase\ScanNextBlock\ScanNextBlockAction::handle()`:

```
1. Загрузить Chain и ScanCursor.
2. Спросить currentHead у источника (BitcoinCoreBlockSource).
3. cursor.observeHead(head).
4. Пока есть pending-блоки и не превысили лимит за тик:
   a. fetchBlockAt(cursor.nextHeight())
   b. ingestBlock(...) — В СОБСТВЕННОЙ ТРАНЗАКЦИИ
5. Если за тик не сканировали ни одного блока — отдельно сохранить курсор
   (чтобы зафиксировать наблюдение `lastSeenHeadHeight`).
```

**Каждый блок обрабатывается в собственной транзакции.** Это важно: если на блоке `K+5` что-то упало, блоки `K..K+4` уже сохранены и не пропадут.

### Что значит «найти поступление»

Внутри `ingestBlock()`:

1. Перебираем все транзакции блока, для каждой — все её outputs.
2. Для каждого output смотрим адрес-получателя.
3. Если этот адрес есть в нашем «справочнике отслеживаемых» (`AddressDirectory::isWatched`) — это наша транзакция.
4. Проверяем, что записи о ней ещё нет (`existsForRecipient`), и создаём `IncomingTransaction` со статусом `Detected` и `confirmations = 1`.

Что такое «справочник»: `App\Modules\BlockIngestion\Domain\Contract\AddressDirectory` — порт, реализация — Redis-набор (`RedisAddressDirectory`). Когда `AddressGenerated`-событие срабатывает, листенер `RegisterAddressInDirectory` (модуль `Address`) добавляет новый адрес в Redis. Сканер за один блок делает O(N) проверок «есть в наборе?» — это O(1) по Redis.

### Почему Redis, а не PostgreSQL

Источник правды для адресов — это всё-таки таблица `addresses` в PostgreSQL (туда пишет `GenerateAddressAction`, см. [Урок 2](#урок-2--ключи-адреса-и-hd-кошельки)). Redis тут — **не БД, а специализированный кеш-индекс** под одну операцию: «есть ли строка `X` в наборе?». Причины:

1. **Профиль нагрузки.** Один Bitcoin-блок типично содержит 2 000–4 000 транзакций, у каждой 1–10+ outputs. Сканер каждый блок делает **десятки тысяч** проверок «наш адрес?». При средней частоте блока (10 минут BTC, 12 секунд Ethereum, 2 секунды Polygon) и нескольких параллельных сетях речь о миллионах проверок в минуту на пустом месте. Чтобы это не превратилось в боттлнек, нужна операция, которая укладывается в микросекунды.

2. **Подходящая структура данных у Redis.** Redis SET — это hash-set in-memory. Команда `SISMEMBER key value` — это `O(1)` по содержимому набора (всегда одинаково быстрая, независимо от того, сколько у нас адресов: 10 или 10 миллионов). По сети до Redis — это один RTT, типично десятки микросекунд. У PostgreSQL `SELECT 1 FROM addresses WHERE address = ?` тоже `O(log n)` по B-tree-индексу, но накладные расходы выше: парсинг SQL, планировщик, MVCC, журналирование, fsync. На «горячем пути» сканера это ощутимо.

3. **Это денормализованная проекция, не источник истины.** Если Redis упадёт или потеряет данные — катастрофы не происходит: набор перестраивается из `addresses` (rebuild-команды пока нет, ручное переразвёртывание). Поэтому Redis тут можно держать без AOF-фsync и репликации — durability не нужна. PostgreSQL же платит за durability на каждый запрос. Использовать его на горячем пути — это «возить молоко на катафалке».

4. **Шардирование по семейству.** Структура хранения — отдельный SET на семейство (`bl:addr:bitcoin`, `bl:addr:evm`, `bl:addr:tron`). Сканер BTC-блока проверяет только BTC-набор и никогда не «трогает» EVM-набор. У PostgreSQL пришлось бы либо плодить индексы с предикатом, либо платить за фильтр по `family` каждый раз.

5. **Локальность.** PostgreSQL у нас уже несёт основную бизнес-нагрузку (withdrawals, ledger, idempotency, outbox, миграции). Снять с него ещё и read-heavy hot-path запрос — это разгрузка, а не дополнительная зависимость: Redis уже есть в стеке для очередей Horizon и rate-limit-кэша.

Архитектурно: контракт `AddressDirectory` лежит в `BlockIngestion::Domain::Contract` (порт), реализация `RedisAddressDirectory` — в `Address::Infrastructure::AntiCorruption` (адаптер). BlockIngestion **никак** не знает про Redis. Завтра решим перейти на BloomFilter, отдельную in-memory ноду или вернуться в PostgreSQL — поменяется только bind в Service Provider, ни строчки в сканере. Это типичная инфраструктурная инверсия в DDD.

Тесты используют другую реализацию того же порта — `App\Modules\Address\Infrastructure\AntiCorruption\InMemoryAddressDirectory` (массив в памяти). Те же интерфейсные методы, ноль внешних зависимостей — `make test` не требует ни Redis, ни PostgreSQL для unit-уровня.

### Поднятие событий после COMMIT

Везде в кодовой базе мы используем паттерн `DB::afterCommit()`. Зачем: если события поднять до COMMIT, а COMMIT упадёт, появятся «фантомные» события («депозит зачислен», хотя в БД ничего нет). После COMMIT транзакция гарантирована — события безопасны.

Это правило встречается в `ScanNextBlockAction`, `GenerateAddressAction`, `RecordLedgerCreditAction`, `RequestWithdrawalAction`, `UpdateConfirmationsAction` — везде. Один из ключевых принципов проекта.

---

## Урок 6 — Подтверждения и финализация

### Что значит «подтверждение»

Транзакция, попавшая в блок `B`, считается имеющей **1 подтверждение** в момент, когда `B` — это вершина цепи. Когда сверху ляжет блок `B+1`, у неё станет 2 подтверждения. Чем больше подтверждений, тем дороже атакующему *переписать* историю — нужно построить более длинную альтернативную цепь, начиная с блока выше `B`.

Формула:

```
confirmations = max(0, lastScannedHeight - txBlockHeight + 1)
```

(Когда транзакция в самом последнем сканированном блоке — 1 подтверждение. На каждый новый блок сверху — +1.)

### Состояния транзакции

`App\Modules\Confirmation\Domain\ValueObject\ConfirmationOutcome`:

- `Detected` — нашли в блоке, ещё не считали подтверждения.
- `Confirming` — есть подтверждения, но меньше `requiredConfirmations`.
- `Confirmed` — подтверждений ≥ `requiredConfirmations`. Это момент, когда зачисляем на баланс.
- `Finalized` — подтверждений > `maxReorgDepth`. Откатить уже считается практически невозможным.
- `Orphaned` — блок попал в reorg; см. [Урок 7](#урок-7--реорганизации-цепи).

Чистая функция, которая решает «какой outcome для текущей высоты»:

```php
App\Modules\Confirmation\Domain\Service\ConfirmationCalculator::compute(
    txBlockHeight, lastScannedHeight, requiredConfirmations, maxReorgDepth
)
```

Это статический алгоритм без побочных эффектов — легко тестируется юнитами.

### Кто это запускает

Job `UpdateConfirmationsJob` (модуль `Confirmation`) периодически вызывает `UpdateConfirmationsAction`:

1. Получить `lastScannedHeight` сети (через адаптер `ChainScannerHead`, который читает курсор BlockIngestion — кросс-модульный порт).
2. Загрузить все `IncomingTransaction` со статусом не-`Finalized` для этой сети.
3. Для каждой посчитать новый outcome через `ConfirmationCalculator`.
4. Если статус *изменился* — обновить + поднять событие (`TransactionConfirming`, `TransactionConfirmed`, `TransactionFinalized`).
5. Если число не изменилось — ничего не делать, не плодить событий (идемпотентность).

Сам метод `IncomingTransaction::applyConfirmation()` имеет инвариант: **число подтверждений никогда не уменьшается** (`if ($confirmations < $this->confirmations) throw`). Откат при реорге — это отдельный путь (`Orphaned`), а не «уменьшение счётчика».

### Подтверждения для исходящих транзакций

Аналогично, но проще, для нашего собственного withdrawal: `App\Modules\Withdrawal\Application\UseCase\UpdateWithdrawalConfirmations\UpdateWithdrawalConfirmationsAction`.

Для EVM реализация наблюдения — `EvmWithdrawalConfirmationLookup`:

- `eth_getTransactionByHash(hash)` → `null` ⇒ ноду «забыла» — отметить `dropped`.
- `blockNumber === null` ⇒ ещё в mempool, pending.
- иначе → `confirmations = head − txBlock + 1`.

State-machine продвигается строго одна-за-одной: `Broadcasted → Confirming → Confirmed`.

---

## Урок 7 — Реорганизации цепи

### Что происходит

Иногда два майнера/валидатора почти одновременно находят блок на одной и той же высоте. На несколько секунд сеть «разветвляется». Затем кто-то находит следующий блок — он указывает на одного из двух «соперников». Победитель становится канонической цепью, проигравший — **orphaned** (сирота). Все транзакции из проигравшего блока возвращаются в mempool (если их не было в выигравшем).

Это и есть **reorg** (reorganization). Для нас это значит: тот платёж, который мы считали «подтверждённым в блоке H» теперь в этом блоке отсутствует. Если мы уже зачислили деньги — надо откатить.

### Алгоритм обнаружения

`App\Modules\ReorgDetection\Application\UseCase\EvaluateBlockReorg\EvaluateBlockReorgAction` срабатывает на каждое событие `BlockIngested` через листенер `EvaluateOnBlockIngested`.

Решение «есть ли reorg» принимает чистый сервис `App\Modules\ReorgDetection\Domain\Service\ChainComparator::analyze()`:

```
вход:  новый блок (height H, parent_hash P) и stored prev (блок, который у нас сохранён на высоте H-1)

если storedPrev == null         → noBaseline
если storedPrev.hash == P       → cleanExtension     (всё хорошо, родитель совпал)
иначе                            → reorg(orphanedHeight = H-1)
```

Идея простая: новый блок утверждает, что его родитель — `P`. У нас на той же высоте сохранён блок с другим хэшем. Значит, *этот* блок (тот, что у нас) — orphan. Его надо вытеснить.

### Что делает Action при обнаружении reorg

В одной транзакции:

1. `orphanIncomingAtHeight(chainId, H-1)` — пометить все наши `IncomingTransaction` из этой высоты статусом `Orphaned` (а не удалить).
2. `deleteBlockAtHeight(chainId, H-1)` — удалить запись блока.
3. `rollbackScanCursorTo(chainId, H-2)` — откатить курсор на один шаг назад. Это заставит `BlockIngestion` снова попытаться скачать `H-1` — но на этот раз получит уже новую версию блока.

После COMMIT:

- Поднимается событие `ReorgDetected(chainId, orphanedHeight, depth=1, orphanedTransactionCount)`.
- Если `depth > chain.confirmationRequirement.maxReorgDepth` → дополнительно `ReorgTooDeep`.

Важная деталь: **walk-back делается итеративно через цикл сканирования, а не глубокой рекурсией внутри одного Action**. Action всегда отступает ровно на один блок. Следующий тик `ScanNextBlockAction` снова попробует скачать тот же блок, снова прогонит `ChainComparator` — если родитель опять не совпадает, снова отступит. Это продолжается до общего предка. Поэтому в `ScanNextBlockAction::handle()` есть строчка: «если за тик не было ингеста, **не** перезаписываем курсор» — иначе откат, сделанный ReorgDetection в afterCommit, был бы сразу затёрт обратно.

### Реакция других модулей

`ReorgDetected` слушают:

- `App\Modules\Ledger\Infrastructure\Listener\ReverseLedgerOnReorg` → вызывает `ReverseLedgerForReorgAction`. Создаёт **компенсирующие проводки** (см. [Урок 8](#урок-8--двойная-бухгалтерия-ledger)).
- `App\Modules\Webhook\Infrastructure\Listener\RecordOutboxOnReorgDetected` → отправляет webhook клиенту.

`ReorgTooDeep` слушает:

- `App\Modules\Withdrawal\Infrastructure\Listener\PauseChainOnReorgTooDeep` → ставит сеть на паузу (`CacheChainPauseRegistry::pause(ttl=86400)`). Пока пауза активна, новый `RequestWithdrawalAction` бросит `ChainPausedException`.

Это и есть «human alert»: автоматический отказ от исходящих, оповещение, пока инженер не разберётся.

---

## Урок 8 — Двойная бухгалтерия (Ledger)

### Зачем двойная бухгалтерия

Изобретение бухгалтеров XV века. Принцип: каждая денежная операция фиксируется **двумя** записями — `debit` и `credit` — так, что суммы по дебету и кредиту по всему журналу всегда равны. Это даёт два свойства:

1. **Аудит**: можно по любой записи показать, откуда деньги пришли и куда ушли.
2. **Невозможность «потерять» деньги в коде**: ошибка обнуления нарушает баланс, его легко поймать.

В нашем проекте даже не двойная в полном смысле (мы пока пишем только credit-ную сторону для пользователя), но **главный принцип удержан: ничего никогда не удаляем, изменения = новые компенсирующие записи**.

### Сущность LedgerEntry

`App\Modules\Ledger\Domain\Entity\LedgerEntry` — иммутабельная запись:

- `direction`: `Credit` (приход) или `Debit` (расход).
- `money` (`Money` — сумма как строка в minor units + валюта).
- `operationType`: `Deposit` (зачисление от входящей tx) или `ReorgReversal` (компенсация после реорга).
- `operationRef`: уникальная ссылка на источник операции (для `Deposit` это `incomingTransactionId`, для `ReorgReversal` — `reorg:{originalEntryId}`).
- `status`: `Confirmed` / `Pending` / `Reversed`.
- `reversesEntryId`: ссылка на оригинал, если это компенсация.

Метод `Money` (`Ledger\Domain\ValueObject\Money`) хранит сумму как **строку** (`NUMERIC(40,0)`). Потому что в wei балансы могут быть до 40 десятичных цифр — `int64` (макс. ~19 цифр) не хватит.

### Зачисление при подтверждении

Листенер `RecordCreditOnConfirmed` слушает `TransactionConfirmed` из модуля Confirmation и вызывает `RecordLedgerCreditAction`:

1. **Идемпотентность**: проверяем, нет ли уже записи `(operationType=Deposit, operationRef=incomingTransactionId)`. Если есть — `created=false`, выходим. Это спасает от повторной обработки одного и того же события.
2. Подгружаем данные транзакции через порт `ConfirmedTransactionView`.
3. Через порт `WalletOwnership` ищем, какому кошельку принадлежит адрес-получатель. Если нет — выбрасываем `WalletOwnershipMissingException` (адрес не зарегистрирован у нас — кейс ошибки в данных).
4. Создаём `LedgerEntry::recordDeposit(...)` — `Direction::Credit`, `Status::Confirmed`.
5. COMMIT → событие `LedgerEntryRecorded` после COMMIT.

### Откат при реорге

`ReverseLedgerOnReorg` слушает `ReorgDetected`. Алгоритм в `ReverseLedgerForReorgAction`:

1. Найти все `LedgerEntry`, у которых:
   - `chainId` совпадает,
   - `blockHeight >= fromHeight` (попадают в orphaned диапазон),
   - `status = Confirmed`.
2. Для каждого:
   - Создать **новую** запись `LedgerEntry::reverse($original, ...)`:
     - `direction = original.direction.opposite()` (Credit → Debit),
     - `operationType = ReorgReversal`,
     - `reversesEntryId = original.id`.
   - Оригинал перевести в `Reversed` (`markReversed()`).
3. Поднять события `LedgerEntryReversed`.

Историческая запись о зачислении **не удаляется** — она остаётся в журнале со статусом `Reversed`. Можно показать клиенту: «3 мая на ваш адрес зачислили 0.5 BTC, 4 мая блок был отменён сетью, зачисление отменено компенсирующей проводкой». Полный аудит сохранён.

Баланс кошелька = `Σ(Credit.Confirmed) − Σ(Debit.Confirmed)` по всем записям с этим `walletId`. Это **read model**, которую можно пересчитывать или хранить отдельно.

---

## Урок 9 — Комиссия (fee)

Комиссию платит **отправитель** транзакции — это плата сети за включение в блок. Без неё транзакция «висит» в mempool бесконечно. Модель комиссии у Bitcoin и EVM устроена по-разному.

### Bitcoin: sat/vbyte

Размер транзакции измеряется в **vbyte** (virtual byte, учитывает segwit-скидку). Комиссия = `vsize * fee_rate`, где `fee_rate` — сатоши за vbyte. Чем больше нагрузка на сеть, тем выше нужен `fee_rate`, чтобы попасть в ближайший блок.

`bitcoind` сам умеет оценивать: `estimatesmartfee N [mode]` → «какой `fee_rate` нужен, чтобы попасть в ближайшие N блоков». Возвращает BTC/kB (исторический формат).

`App\Modules\Fee\Infrastructure\Estimator\BitcoinFeeEstimator::estimate()`:

1. По `FeePriority::Low | Standard | High` выбираем `target` (число блоков) и `mode` (`CONSERVATIVE` / `ECONOMICAL`).
2. RPC `estimatesmartfee(target, mode)` → `feerate` в BTC/kB.
3. Конвертация в sat/vbyte: `ceil(feerate * 1e8 / 1000)`, через `bcmath` — без float.
4. Если RPC вернул `-1` (нет данных, типично на regtest) → fallback `minSatPerVbyte` из конфига.

Считая итог: размер транзакции мы оцениваем эвристикой `vsize ≈ 110*inputs + 34*outputs + 10`. Итоговая комиссия = `vsize * sat_per_vbyte`. Это видно в `BitcoinTxBuilder::build()`.

### EVM: EIP-1559

До EIP-1559 (август 2021) комиссия была одна цифра — `gas_price`. Аукционная модель: кто больше предложил, того и блок. Это вело к диким скачкам и плохому UX.

EIP-1559 разделил комиссию на две части:

- **base fee** — динамическая ставка, которая *обязательна* и **сжигается** (не получает майнер). Она автоматически растёт на 12.5%, если предыдущий блок был >50% заполнен, и падает, если <50%. То есть сеть сама регулирует base fee «гомеостазом».
- **priority fee** (он же tip) — что вы готовы заплатить майнеру/валидатору сверху, как «чаевые», чтобы он предпочёл вашу транзакцию.

Транзакция в EIP-1559 указывает:

- `max_fee_per_gas` — потолок (`base_fee + priority`, который вы готовы заплатить).
- `max_priority_fee_per_gas` — желаемый tip.
- Фактическая стоимость единицы газа: `min(max_fee, base_fee + priority)`.

Полная комиссия: `effective_gas_price * gas_used`. Для простого ETH-перевода `gas_used = 21000` (протокольный минимум).

`App\Modules\Fee\Infrastructure\Estimator\EvmFeeEstimator::estimate()`:

1. `eth_feeHistory(4, "latest", [percentile])` — последние 4 блока, для запрошенного перцентиля (10/50/90 на Low/Standard/High).
2. `base_fee` = `baseFeePerGas[последний]` — это base fee следующего (pending) блока, который сеть уже посчитала.
3. `priority_fee` = среднее ненулевых сэмплов перцентиля. Нули отбрасываем — это пустые блоки тестнета, неинформативные.
4. `max_fee_per_gas = base_fee * multiplier + priority_fee`. Множитель (например, 1.25) даёт запас на 2 подряд полных блока (`1.125^2 ≈ 1.27`).
5. Защита: если `max_fee` посчитался 0 (пустой тестнет с `base_fee=0` и `priority=0`) → принудительно 1 wei, иначе ноду отклонит.

### Bitcoin vs EVM — про комиссию в одной таблице

| | Bitcoin | EVM |
|---|---|---|
| Что регулируется | `sat/vbyte` (рейт на размер) | `gas_price` через `max_fee` + `max_priority` |
| Размер | физический vsize транзакции | абстрактный `gas_used` |
| Полная стоимость | `vsize * fee_rate` | `effective_gas_price * gas_used` |
| Базовая ставка | определяется рынком | детерминированная `base_fee` (сжигается) |
| RPC оценки | `estimatesmartfee` | `eth_feeHistory` |

`FeeQuote` — общий value object, в котором `breakdown` — это полиморфный массив (`BitcoinFeeBreakdown` или `EvmFeeBreakdown`). Это позволяет одному `FeeQuoteSnapshot` лежать в `withdrawal.signing_extras` для любой сети.

---

## Урок 10 — Вывод средств (withdrawal)

Это самая сложная часть. Здесь сходятся nonce/UTXO, fee, ключи, broadcast и идемпотентность.

### State machine

`App\Modules\Withdrawal\Domain\ValueObject\WithdrawalStatus`:

```
Requested → Built → Signed → Broadcasted → Confirming → Confirmed
                                       │
                                       ├─→ Stuck → Replaced
                                       └─→ Failed
```

Каждый переход охраняется методом `assertCanTransitionTo()` — если кто-то попытается перепрыгнуть через шаг, домен бросит исключение. Это инварианты, которые делают `Withdrawal` *настоящим* агрегатом, а не CRUD-моделью.

Сами шаги в `App\Modules\Withdrawal\Domain\Entity\Withdrawal`:

- `request()` — фабрика, статус `Requested`.
- `markAsBuilt(rawTxHex, nonce, signingExtras)` — собрали черновик транзакции.
- `markAsSigned(signedHex)` — подпись от signing-svc получена.
- `markAsBroadcasted(txHash)` — отправили в mempool сети.
- `markAsConfirming(N)` / `markAsConfirmed(N)` — обновление от polling-задачи.
- `markAsStuck()` — слишком долго висит без подтверждения.
- `markAsReplaced(replacementId)` — был заменён через RBF/resend.
- `fail(reason)` — терминальный сбой.

### Оркестрация: `RequestWithdrawalAction`

`App\Modules\Withdrawal\Application\UseCase\RequestWithdrawal\RequestWithdrawalAction::handle()`:

1. **Идемпотентность** по заголовку `Idempotency-Key`. Если запись существует:
   - сравнить отпечаток (`wallet_id`, `chain_id`, `to_address`, `amount`, `currency`, `priority`),
   - если совпал → вернуть существующую (`reused=true`),
   - если нет → 409 `IdempotencyConflictException::payloadMismatch`.
2. Проверить, что сеть не на паузе (`ChainPauseRegistry::isPaused`). Иначе `ChainPausedException` → HTTP 503.
3. Загрузить `Chain`. Определить горячий кошелёк (`HotWalletResolver`).
4. Оценить комиссию: `Fee::Application::EstimateFeeAction`. Снимок (`FeeQuoteSnapshot`) кладём в withdrawal.
5. Для EVM — выделить nonce (`NonceAllocator::allocate`). Для BTC — пропускаем (нет nonce).
6. `Withdrawal::request(...)` — создать агрегат, статус `Requested`.
7. **Build → Sign → Broadcast в трёх отдельных транзакциях**:
   - `TxBuilder::build()` → COMMIT (`markAsBuilt`).
   - `SigningClient::signRawTx()` → COMMIT (`markAsSigned`).
   - `ChainAdapter::broadcast()` → COMMIT (`markAsBroadcasted`) + поднять события.

**Почему три транзакции, а не одна**. Если упасть посередине, состояние всё равно будет сохранено в БД. Polling-задача увидит «есть Built, но не Signed» → попробует продолжить со следующего шага. Это превращает withdrawal в **возобновляемый** процесс. Альтернатива «всё в одной транзакции» оставила бы нас с потерянными подписями и неотправленными байтами — а это деньги.

Любая ошибка в этой цепочке вызывает `failQuietly()` → `withdrawal.fail(reason)` → клиент получает 5xx с осмысленным сообщением. Если withdrawal уже терминальный (`Confirmed`/`Failed`/`Replaced`) — `fail()` бы упал на assertCanTransitionTo, и мы гасим исключение, чтобы не «перебить» истинную причину.

### Идемпотентность во всех слоях

Платёжная инфраструктура обязана быть идемпотентной — иначе ретрай по таймауту переведёт деньги дважды. У нас идемпотентность защищена сразу на трёх уровнях:

1. **Middleware** (`Modules/Idempotency`): глобально для любого POST с заголовком `Idempotency-Key`. См. [Урок 12](#урок-12--надёжность-и-наблюдаемость).
2. **Локальная защита в `Withdrawal`-репозитории**: `findByIdempotencyKey()` + UNIQUE на колонке `idempotency_key`. Если middleware по какой-то причине пропустил (например, кеш не сработал) — БД защитит.
3. **Идемпотентность по `replacementOf`** для RBF — следующая глава.

---

## Урок 11 — Застрявшие транзакции и RBF

### Почему транзакции «застревают»

После broadcast транзакция падает в mempool. Если комиссия слишком низкая для текущей загрузки — её майнеры пропускают, она «висит». Может висеть часами/днями. Пользователь ждёт, мы — отвечаем за SLA.

### Как мы это ловим

`App\Modules\Withdrawal\Application\UseCase\MarkStuckWithdrawals\MarkStuckWithdrawalsAction` (вызывается `WatchStuckWithdrawalsJob` по расписанию):

1. Найти все `Broadcasted` withdrawal'ы, у которых `broadcastAt < now - stuckAfterSeconds`.
2. Для каждой пометить `markAsStuck($now)` в собственной транзакции и поднять `WithdrawalStuck`.

Listener `ReplaceOnWithdrawalStuck` подхватывает событие и вызывает `ReplaceStuckWithdrawalAction`.

### Замена

`App\Modules\Withdrawal\Application\UseCase\ReplaceStuckWithdrawal\ReplaceStuckWithdrawalAction::handle()`:

1. Снова грузим оригинал (`originalId`). Если он *уже не* `Stuck` (например, между событием и обработкой добавилось подтверждение) → no-op. Это первая защита от двойной замены.
2. Ищем существующий withdrawal по `idempotency_key = "rbf:{original_id}"`. Если есть — кто-то уже создал замену, выходим. Вторая защита.
3. **Bump fee** (`bumpFee`):
   - `config('withdrawal.rbf.fee_multiplier_bps')` = 12500 = 125% (т.е. +25%).
   - Bitcoin: `new_sat_per_vbyte = max(prev+1, prev * bps/10000)`.
   - EVM: `max_fee_per_gas` и `max_priority_fee_per_gas` умножить на `bps/10000` (через `bcmul`/`bcdiv` — потому что wei это строки).
4. Создаём новый `Withdrawal::request(...)` с теми же `to/amount`, новой fee, `replacement_of = original.id`, `idempotency_key = "rbf:..."`.
5. `TxBuilder::rebuild(...)` — пересборка:
   - **Bitcoin**: те же входы (`signing_extras['inputs']`), но `sequence = 0xfffffffd` (BIP-125 «replaceable»). Подняли fee → сеть заменит старую транзакцию.
   - **EVM**: тот же `nonce`, новые fee-поля. Любая EVM-нода заменит pending транзакцию с тем же `(from, nonce)`, если новая газовая цена выше как минимум на ~10-12.5%.
6. Дальше build → sign → broadcast в трёх транзакциях, как обычный withdrawal.
7. В финальной транзакции перепроверяем статус оригинала — если он всё ещё `Stuck`, делаем `original.markAsReplaced(replacement.id)`. Если нет — например, подтверждение пришло прямо перед нами — мы новой записью уже отправили в сеть конкурирующую транзакцию, но это безопасно: либо она будет отвергнута (Bitcoin: «уже есть в блоке»), либо она же и есть оригинал.

### BIP-125 в Bitcoin: подробнее

Bitcoin позволяет заменять mempool-транзакцию **только если**:

- Все входы новой транзакции имеют `sequence < 0xfffffffe` (это и есть «opt-in RBF»).
- Новая транзакция платит достаточно высокую дополнительную fee (не ниже min-relay).
- Она «расходует» хотя бы один из тех же входов, что и старая.

Поэтому `BitcoinTxBuilder::rebuild()` берёт *те же* входы из `previousExtras['inputs']`, что и оригинал, и явно ставит `sequence = 0xfffffffd`.

### Пауза сети

`ReorgTooDeep` из модуля ReorgDetection (см. [Урок 7](#урок-7--реорганизации-цепи)) ловится листенером `PauseChainOnReorgTooDeep` и **запрещает создание новых withdrawal** на эту сеть на 24 часа (по умолчанию). Чтобы не отправить деньги в сеть, история которой только что переписалась настолько глубоко, что мы не уверены в её консистентности.

Хранилище паузы — `CacheChainPauseRegistry` поверх Laravel Cache (Redis в проде). `RequestWithdrawalAction` проверяет `isPaused()` на третьем шаге своей оркестрации.

---

## Урок 12 — Надёжность и наблюдаемость

Последний урок — это «как мы гарантируем, что сложная система не сломается, когда что-то одно упадёт».

### 12.1. HTTP-идемпотентность

`App\Modules\Idempotency\Infrastructure\Http\IdempotencyMiddleware`:

- Подключается **глобально** на POST-эндпоинтах.
- Если в запросе нет заголовка `Idempotency-Key` — pass-through.
- Иначе:
  1. Вычислить `request_hash = sha256(method + "\n" + path + "\n" + body)`.
  2. Поискать в `idempotency_keys` по `key`. Если есть:
     - Тот же хэш → replay ответа со статусом `X-Idempotent-Replay: true`.
     - Другой хэш → 409 `idempotency_conflict` (тот же ключ, другой запрос — это ошибка клиента).
  3. Если нет → выполнить запрос, и **только если** статус 2xx-4xx (но не 5xx) → сохранить ответ в `idempotency_keys` с TTL 24 часа.

Почему 5xx **не** кешируем: 5xx обычно транзиентная ошибка (сеть упала, узел не ответил). Если её закешировать, клиент получит «вечный 500» на ретрай — это хуже, чем без кеша.

### 12.2. Transactional Outbox

Проблема **dual-write**: «сохранить в БД» и «отправить webhook» — это две разные системы, между ними нет распределённой транзакции. Если сделать наивно:

```php
DB::transaction(fn () => $repo->save(...));
$messageBus->publish(...);   // ← процесс упал здесь
```

— клиент не получит уведомление, хотя транзакция в БД есть. Или наоборот: отправили webhook, а транзакция упала на COMMIT — у клиента есть «уведомление о платеже, которого нет».

Решение — **transactional outbox** (классический паттерн):

1. В той же транзакции, где меняется бизнес-сущность, мы записываем событие в таблицу `outbox_messages` (`App\Modules\Webhook\Domain\Entity\OutboxMessage`). Поле `published_at = null` пока.
2. Отдельный воркер (`PublishOutboxJob` → `PublishOutboxAction`) находит unpublished записи и для каждой:
   - Найти подходящие подписки (`subscriptions.event_name = message.event_name`).
   - Создать `WebhookDelivery` per (message, subscription) — `UNIQUE(outbox_id, subscription_id)` защищает от дублей.
   - `outbox.markPublished($now)`.
3. Другой job (`DispatchDueDeliveriesJob`) реально отправляет HTTP-запросы.

Гарантия: **at-least-once**. Получатель должен сам быть идемпотентным.

В коде:

- Listener `RecordOutboxOnTransactionConfirmed` слушает `TransactionConfirmed` и пишет в outbox.
- Listener `RecordOutboxOnReorgDetected` пишет событие реорга.
- Listener `RecordOutboxOnWithdrawalConfirmed` пишет событие подтверждения withdrawal.

### 12.3. HMAC-подпись webhook'ов

Когда наш сервер шлёт webhook клиенту, клиент должен убедиться, что это действительно мы, а не злоумышленник, узнавший URL.

`App\Modules\Webhook\Domain\ValueObject\WebhookSignature::compute()`:

```
sig = sha256=<hex(hmac_sha256(secret, timestamp + "." + body))>
```

Это **Stripe-style**. Заголовки:

- `X-Timestamp: <epoch seconds>`
- `X-Signature: sha256=<hex>`

Клиент повторяет вычисление с тем же `secret` (выданным нами при подписке), сравнивает через `hash_equals` (timing-safe), и дополнительно проверяет, что `now − timestamp ≤ 5 минут` — это защита от replay (перехват и переотправка через час).

`App\Modules\Webhook\Infrastructure\Http\HttpWebhookDispatcher::dispatch()` решает по статусу ответа:

- 2xx → `delivered`.
- 4xx → `failed` (постоянная ошибка, ретраить бессмысленно).
- 5xx / timeout / DNS → `retryable` (повторим позже).

### 12.4. NodeHealth: выбор живой ноды и circuit-breaker

У каждой сети может быть несколько RPC-эндпоинтов (`Chain::endpoints()`). Они могут падать, тормозить, отдавать неверные данные. Чтобы это автоматически переживать:

- `App\Modules\NodeHealth\Application\UseCase\ProbeChainEndpoints\ProbeChainEndpointsAction` периодически (через `ProbeChainEndpointsJob`) опрашивает все эндпоинты сети.
- Для каждого вызывается probe-семейства (`EvmEndpointHealthProbe::probe` или `BitcoinEndpointHealthProbe::probe`). Probe:
  - делает простой RPC (`eth_blockNumber` / `getblockchaininfo`),
  - меряет latency,
  - возвращает `EndpointObservation`: `healthy` / `degraded (>2000 ms)` / `unhealthy (любая ошибка)`.
- Состояние пишется в `CacheEndpointHealthRegistry` (Laravel Cache).
- Если статус изменился — событие `EndpointHealthChanged`.

Когда какому-то модулю нужен URL для RPC-вызова, он спрашивает `App\Modules\NodeHealth\Infrastructure\Picker\HealthBasedRpcEndpointPicker::pick()`:

```
кандидаты = endpoints, у которых status.isUsable() (т.е. не Unhealthy)
сортировка: Healthy → Degraded → Unknown
вернуть первый; если кандидатов нет — NoRpcEndpointException → 503
```

Это эффективно работает как **circuit-breaker**: упавший эндпоинт автоматически исключается из выбора, пока probe не увидит, что он снова жив.

### 12.5. Bulkhead через очереди

Каждый «дорогой» процесс (ingestion, confirmation update, outbox publish, stuck-watcher, webhook dispatch) — это отдельный Laravel Job. У нас они идут **по разным очередям на разные сети** (см. конфигурацию Horizon). Это паттерн **bulkhead**: если Bitcoin-ингест внезапно замедлился из-за тяжёлого блока, EVM-обработка продолжает идти на своих воркерах. Один отсек тонет — корабль продолжает плыть.

### 12.6. Where the work actually happens

Сводная таблица фоновых задач (что и почему запускается):

| Job | Модуль | Что делает |
|---|---|---|
| `ScanChainJob` | BlockIngestion | один тик сканера для одной сети |
| `UpdateConfirmationsJob` | Confirmation | пересчёт подтверждений для входящих |
| `WatchWithdrawalConfirmationsJob` | Withdrawal | опрос подтверждений для исходящих |
| `WatchStuckWithdrawalsJob` | Withdrawal | пометить зависшие → выпустить `WithdrawalStuck` |
| `ProbeChainEndpointsJob` | NodeHealth | health-check RPC-эндпоинтов |
| `PublishOutboxJob` | Webhook | вытащить outbox-сообщения, нагенерировать deliveries |
| `DispatchDueDeliveriesJob` | Webhook | реально слать HTTP, фиксировать outcome |
| `CleanupExpiredIdempotencyKeysJob` | Idempotency | удалять истёкшие записи (TTL прошёл) |

---

## Финал — карта зависимостей

Кратко, как модули общаются:

```
        ┌──────────────────────────┐
        │   Идемпотентность (HTTP) │  глобальный middleware на POST
        └────────────┬─────────────┘
                     │
                ┌────▼────────────────────────────┐
   POST  ────►  │  Withdrawal::Request            │  использует ↓
                │   Fee::EstimateFeeAction        │
                │   Network::Chain + ChainAdapter │
                │   Network::SigningClient (→ Go) │
                │   Withdrawal::TxBuilder         │
                └────┬────────────────────────────┘
                     │ build/sign/broadcast в трёх tx
                     │
                     ▼
                  сеть (BTC/EVM)
                     │
                     │ позже…
                     ▼
        ┌──────────────────────────┐
        │ BlockIngestion::ScanChain│ ◄── видит свою же tx как входящую
        │   ↳ TransactionDetected  │
        └────────────┬─────────────┘
                     │ events
       ┌─────────────┼─────────────────────────┐
       ▼             ▼                          ▼
ReorgDetection   Confirmation              Webhook::Outbox
       │             │                          │
       │ events      │ events                   │ schedule deliveries
       │             ▼                          ▼
       │      Ledger::RecordCredit       HttpWebhookDispatcher
       │      (двойная запись)            (HMAC + retries)
       │
       │ ReorgDetected
       ▼
  Ledger::ReverseLedgerForReorg
  Withdrawal::PauseChainOnReorgTooDeep
```

Это и есть полная картина: каждое из 12 понятий имеет ровно одно место в коде, никакое из них не «размазано» по слоям.

---

## Куда идти дальше

Когда вы это поняли — открывайте конкретный модуль и читайте его как «увеличенную» версию нужного урока:

- **«Хочу понять, как deposit становится записью в ledger»** → начните с `Modules/BlockIngestion/Application/UseCase/ScanNextBlock`, дальше события поведут через `Confirmation → Ledger`.
- **«Хочу понять, как мы отправляем платёж»** → `Modules/Withdrawal/Application/UseCase/RequestWithdrawal`, потом `TxBuilder/*` и `signing-svc/internal/server/handlers.go`.
- **«Хочу увидеть реорг в действии»** → запустите regtest (`make up`), отслеживайте, как сканер ингестит блоки, потом через RPC `invalidateblock` принудительно вызовите реорг и читайте логи `EvaluateBlockReorgAction`.

Реальный код — лучший учебник, чем эта страница. Но без неё в нём легко потеряться.
