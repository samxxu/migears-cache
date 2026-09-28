# migears-cache — Known Issues

> Summary of this module's issues. The items themselves are in [`issues/`](issues/README.md), one file
> per item: a front-matter header and a thread. This file is generated from them and can be rewritten at
> any time; edit an item, never this file.
>
> From the miGears Full-Module Code Review Report (5th round, 2026-09-28).

| | |
|---|---|
| Status | **P2 open** |
| Size | src 528 lines (net) · 79 tests · 3 src files |

Legend — **P0** functional or security · **P1** documentation that fails when copied · **P2** robustness · **P3** metadata and docs

## At a glance

| | |
|---|---|
| Unsettled | P0 0 · P1 0 · P2 4 · P3 2 · other 3 |
| Settled | 0 of 9 |
| Waiting on the owner | _nothing_ |
| Waiting on the reviewer | `P2-1`, `P2-2`, `P2-3`, `P2-4`, `P3-1`, `P3-2`, `G1`, `G2` |
| Waiting on the coordinator | `G3` |
| Deferred, owing nobody | _nothing_ |

| id | level | status | title |
|---|---|---|---|
| [`P2-1`](issues/P2-1.md) | P2 | **fixed** | Neither implementation validates keys, though PSR-16 requires … |
| [`P2-2`](issues/P2-2.md) | P2 | **fixed** | New this round: `getMultiple()` assumes the input keys align … |
| [`P2-3`](issues/P2-3.md) | P2 | **fixed** | `delete()` returns `del(...) >= 0`, which is true even when `del()` … |
| [`P2-4`](issues/P2-4.md) | P2 | **fixed** | ttl<=0 still diverges: ArrayCache treats it as already expired (correct … |
| [`P3-1`](issues/P3-1.md) | P3 | **fixed** | `withPrefix()` still breaks on a subclass with an incompatible … |
| [`P3-2`](issues/P3-2.md) | P3 | **fixed** | `setMultiple([])` diverges: RedisCache sends `mset([])` (false under … |
| [`G1`](issues/G1.md) | - | **fixed** | CI file and workflow name: this module uses `.github/workflows/ci.yml` … |
| [`G2`](issues/G2.md) | - | **fixed** | Strict flags: `phpunit.xml.dist` currently sets none of the five. The … |
| [`G3`](issues/G3.md) | - | **question** | The logger is optional and silence is the default: … |

## Unclosed

What is left to do here: every item whose `status` is not `verified` or `closed`,
highest severity first. `waiting on` is the party who acts next, read from that status.

| | |
|---|---|
| Unclosed | **9** of 9 |
| By status | `question` 1 · `fixed` 8 |
| Waiting on | reviewer 8 · coordinator 1 |

| level | item | status | waiting on | title |
|---|---|---|---|---|
| **P2** | [`P2-1`](issues/P2-1.md) | `fixed` | reviewer | Neither implementation validates keys, though PSR-16 requires … |
| **P2** | [`P2-2`](issues/P2-2.md) | `fixed` | reviewer | New this round: `getMultiple()` assumes the input keys align … |
| **P2** | [`P2-3`](issues/P2-3.md) | `fixed` | reviewer | `delete()` returns `del(...) >= 0`, which is true even when `del()` … |
| **P2** | [`P2-4`](issues/P2-4.md) | `fixed` | reviewer | ttl<=0 still diverges: ArrayCache treats it as already expired (correct … |
| **P3** | [`P3-1`](issues/P3-1.md) | `fixed` | reviewer | `withPrefix()` still breaks on a subclass with an incompatible … |
| **P3** | [`P3-2`](issues/P3-2.md) | `fixed` | reviewer | `setMultiple([])` diverges: RedisCache sends `mset([])` (false under … |
| **-** | [`G1`](issues/G1.md) | `fixed` | reviewer | CI file and workflow name: this module uses `.github/workflows/ci.yml` … |
| **-** | [`G2`](issues/G2.md) | `fixed` | reviewer | Strict flags: `phpunit.xml.dist` currently sets none of the five. The … |
| **-** | [`G3`](issues/G3.md) | `question` | coordinator | The logger is optional and silence is the default: … |

## Verdict

Both PSR-16 implementations now validate keys and handle edge cases correctly; two silent-failure gaps remain in deleteMultiple() and clear() where Redis del() failures are swallowed.

## Fixed since the last round

All six prior P2/P3 items fixed in code: PSR-16 key validation, getMultiple key alignment, delete/setMultiple return checks, ttl<=0 delete path, withPrefix clone, empty setMultiple short-circuit.

## Test gaps

No integration test for deleteMultiple return value on Redis failure; no test for clear() with prefix when del() fails; no test for getOrSet() race conditions or factory exception handling.

## Verification protocol

- `./vendor/bin/phpunit` · `composer analyse` · `composer validate`
- Warning/notice/deprecation/risky flags in `phpunit.xml.dist`: all four on
- A PHP warning counts as a test failure only where those flags are on; otherwise run `./vendor/bin/phpunit --fail-on-warning` explicitly.


---

# migears-cache — 已知问题

> 本模块问题的概览。条目本体在 [`issues/`](issues/README.md)，一条目一文件：前置字段加讨论串。
> 本文件由条目生成，随时可以整段重写；请改条目，不要改本文件。
>
> 出自 miGears 全模块代码评审报告（5th round，2026-09-28）。

| | |
|---|---|
| 状态 | **P2 待修** |
| 体量 | src 528 行（净）· 79 个用例 · 3 个源文件 |

级别说明 — **P0** 功能性或安全级 · **P1** 文档照抄即错 · **P2** 健壮性 · **P3** 元数据与文档

## 状态一览

| | |
|---|---|
| 未了结 | P0 0 · P1 0 · P2 4 · P3 2 · 其他 3 |
| 已了结 | 0 / 9 |
| 等负责人 | _无_ |
| 等评审方 | `P2-1`, `P2-2`, `P2-3`, `P2-4`, `P3-1`, `P3-2`, `G1`, `G2` |
| 等协调人 | `G3` |
| 已暂缓，不欠谁 | _无_ |

| id | 级别 | 状态 | 标题 |
|---|---|---|---|
| [`P2-1`](issues/P2-1.md) | P2 | **fixed** | 两实现都不校验 key，而 PSR-16 要求对含 {}()/\@: 的 key 抛 … |
| [`P2-2`](issues/P2-2.md) | P2 | **fixed** | 本轮新增：getMultiple() 假定入参键与 mget() 结果按位置对齐。传 … |
| [`P2-3`](issues/P2-3.md) | P2 | **fixed** | delete() 的 `del(...) >= 0` 在 del() 返回 false 时仍为真，掩盖失败；setMultiple() 丢弃 … |
| [`P2-4`](issues/P2-4.md) | P2 | **fixed** | ttl<=0 仍分叉：ArrayCache 视为立即过期（符合 PSR-16<sup><a … |
| [`P3-1`](issues/P3-1.md) | P3 | **fixed** | withPrefix() 在构造器不兼容的子类上仍会失败（Error: $logger must not be accessed before … |
| [`P3-2`](issues/P3-2.md) | P3 | **fixed** | setMultiple([]) 分叉：RedisCache 下发 mset([])（phpredis 语义下为 … |
| [`G1`](issues/G1.md) | - | **fixed** | CI 文件名与工作流名：本模块使用 `.github/workflows/ci.yml`、`name: CI`。工作区标准是 … |
| [`G2`](issues/G2.md) | - | **fixed** | 严格开关：`phpunit.xml.dist` … |
| [`G3`](issues/G3.md) | - | **question** | logger 是可选的，而默认就是静默：`ArrayCache::__construct(?LoggerInterface $logger = … |

## 未关闭

本模块还剩什么要做：所有 `status` 不是 `verified` 或 `closed` 的条目，按严重度从高到低。
`waiting on` 是下一步该动手的一方，由其状态读出。

| | |
|---|---|
| 未关闭 | **9** / 9 |
| 按状态 | `question` 1 · `fixed` 8 |
| 等在谁 | 评审方 8 · 协调人 1 |

| 级别 | 条目 | 状态 | 等在谁 | 标题 |
|---|---|---|---|---|
| **P2** | [`P2-1`](issues/P2-1.md) | `fixed` | 评审方 | 两实现都不校验 key，而 PSR-16 要求对含 {}()/\@: 的 key 抛 … |
| **P2** | [`P2-2`](issues/P2-2.md) | `fixed` | 评审方 | 本轮新增：getMultiple() 假定入参键与 mget() 结果按位置对齐。传 … |
| **P2** | [`P2-3`](issues/P2-3.md) | `fixed` | 评审方 | delete() 的 `del(...) >= 0` 在 del() 返回 false 时仍为真，掩盖失败；setMultiple() 丢弃 … |
| **P2** | [`P2-4`](issues/P2-4.md) | `fixed` | 评审方 | ttl<=0 仍分叉：ArrayCache 视为立即过期（符合 PSR-16<sup><a … |
| **P3** | [`P3-1`](issues/P3-1.md) | `fixed` | 评审方 | withPrefix() 在构造器不兼容的子类上仍会失败（Error: $logger must not be accessed before … |
| **P3** | [`P3-2`](issues/P3-2.md) | `fixed` | 评审方 | setMultiple([]) 分叉：RedisCache 下发 mset([])（phpredis 语义下为 … |
| **-** | [`G1`](issues/G1.md) | `fixed` | 评审方 | CI 文件名与工作流名：本模块使用 `.github/workflows/ci.yml`、`name: CI`。工作区标准是 … |
| **-** | [`G2`](issues/G2.md) | `fixed` | 评审方 | 严格开关：`phpunit.xml.dist` … |
| **-** | [`G3`](issues/G3.md) | `question` | 协调人 | logger 是可选的，而默认就是静默：`ArrayCache::__construct(?LoggerInterface $logger = … |

## 结论

两个 PSR-16 实现现在都能正确校验 key 并处理边界情况；deleteMultiple() 和 clear() 中仍有两处静默失败缺口——Redis del() 失败被吞掉。

## 本轮已修复确认

All six prior P2/P3 items fixed in code: PSR-16 key validation, getMultiple key alignment, delete/setMultiple return checks, ttl<=0 delete path, withPrefix clone, empty setMultiple short-circuit.

## 测试盲区

无 Redis 失败下 deleteMultiple 返回值集成测试；无前缀 clear() del() 失败测试；无 getOrSet() 竞态条件与工厂异常测试。

## 验证方式

- `./vendor/bin/phpunit` · `composer analyse` · `composer validate`
- `phpunit.xml.dist` 中的 warning/notice/deprecation/risky 开关：四个全开
- 只有在上述开关打开时 PHP 警告才会导致套件失败；否则请显式加 `--fail-on-warning`。
