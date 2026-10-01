# migears-cache — Known Issues

> Summary of this module's issues. The items themselves are in [`issues/`](issues/README.md), one file
> per item: a front-matter header and a thread. This file is generated from them and can be rewritten at
> any time; edit an item, never this file.
>
> From the miGears Full-Module Code Review Report (6th round, 2026-10-01).

| | |
|---|---|
| Status | **Best state** |
| Size | src 536 lines (net) · 86 tests · 5 src files |

Legend — **P0** functional or security · **P1** documentation that fails when copied · **P2** robustness · **P3** metadata and docs

## At a glance

| | |
|---|---|
| Unsettled | P0 0 · P1 0 · P2 0 · P3 0 · other 0 |
| Settled | 11 of 11 |
| Waiting on the owner | _nothing_ |
| Waiting on the coordinator | _nothing_ |
| Waiting on the reviewer | _nothing_ |
| Deferred, owing nobody | _nothing_ |

| id | level | status | title |
|---|---|---|---|
| [`P2-1`](issues/P2-1.md) | P2 | **verified** | Neither implementation validates keys, though PSR-16 requires … |
| [`P2-2`](issues/P2-2.md) | P2 | **verified** | New this round: `getMultiple()` assumes the input keys align … |
| [`P2-3`](issues/P2-3.md) | P2 | **verified** | `delete()` returns `del(...) >= 0`, which is true even when `del()` … |
| [`P2-4`](issues/P2-4.md) | P2 | **verified** | ttl<=0 still diverges: ArrayCache treats it as already expired (correct … |
| [`P2-5`](issues/P2-5.md) | P2 | **verified** | `incr` and `decr` diverge on a non-integer: `ArrayCache` silently … |
| [`P3-1`](issues/P3-1.md) | P3 | **verified** | `withPrefix()` still breaks on a subclass with an incompatible … |
| [`P3-2`](issues/P3-2.md) | P3 | **verified** | `setMultiple([])` diverges: RedisCache sends `mset([])` (false under … |
| [`P3-3`](issues/P3-3.md) | P3 | **closed** | Six issue frontmatter files still list status 'open' despite the code … |
| [`G1`](issues/G1.md) | - | **verified** | CI file and workflow name: this module uses `.github/workflows/ci.yml` … |
| [`G2`](issues/G2.md) | - | **verified** | Strict flags: `phpunit.xml.dist` currently sets none of the five. The … |
| [`G3`](issues/G3.md) | - | **verified** | The logger is optional and silence is the default: … |

## Unclosed

_Nothing unclosed — every item in this module is `verified` or `closed`._

## Verdict

The logger requirement chosen on 2026-09-29 is genuinely implemented in both implementations, and the two drivers now agree on what a non-integer counter means.

## Fixed since the last round

G3 (both constructors now require a LoggerInterface and the NullLogger fallback is gone) and P2-5 (incr/decr agree on a non-integer) were both verified by mutation — reverting either guard turns the module’s own test red.

## Test gaps

The default suite excludes the @group integration tests, and the FakeRedis used by the unit test has no eval(), so RedisCache’s Lua counter script and its MULTI pipeline are never executed locally — P2-5’s "both implementations agree" is pinned on the ArrayCache side only. A subclass fixture in ArrayCacheTest never calls the parent constructor, so its logger stays uninitialised and the test passes only because it never logs.

## Verification protocol

- `./vendor/bin/phpunit` · `composer analyse` · `composer validate`
- Warning/notice/deprecation/risky flags in `phpunit.xml.dist`: all four on
- A PHP warning counts as a test failure only where those flags are on; otherwise run `./vendor/bin/phpunit --fail-on-warning` explicitly.


---

# migears-cache — 已知问题

> 本模块问题的概览。条目本体在 [`issues/`](issues/README.md)，一条目一文件：前置字段加讨论串。
> 本文件由条目生成，随时可以整段重写；请改条目，不要改本文件。
>
> 出自 miGears 全模块代码评审报告（6th round，2026-10-01）。

| | |
|---|---|
| 状态 | **状态最好** |
| 体量 | src 536 行（净）· 86 个用例 · 5 个源文件 |

级别说明 — **P0** 功能性或安全级 · **P1** 文档照抄即错 · **P2** 健壮性 · **P3** 元数据与文档

## 状态一览

| | |
|---|---|
| 未了结 | P0 0 · P1 0 · P2 0 · P3 0 · 其他 0 |
| 已了结 | 11 / 11 |
| 等模块主 | _无_ |
| 等协调人 | _无_ |
| 等评审方 | _无_ |
| 已暂缓，不欠谁 | _无_ |

| id | 级别 | 状态 | 标题 |
|---|---|---|---|
| [`P2-1`](issues/P2-1.md) | P2 | **verified** | 两实现都不校验 key，而 PSR-16 要求对含 {}()/\@: 的 key 抛 … |
| [`P2-2`](issues/P2-2.md) | P2 | **verified** | 本轮新增：getMultiple() 假定入参键与 mget() 结果按位置对齐。传 … |
| [`P2-3`](issues/P2-3.md) | P2 | **verified** | delete() 的 `del(...) >= 0` 在 del() 返回 false 时仍为真，掩盖失败；setMultiple() 丢弃 … |
| [`P2-4`](issues/P2-4.md) | P2 | **verified** | ttl<=0 仍分叉：ArrayCache 视为立即过期（符合 PSR-16<sup><a … |
| [`P2-5`](issues/P2-5.md) | P2 | **verified** | `incr` 与 `decr` 在非整数上不一致：`ArrayCache` 静默强转，`RedisCache` … |
| [`P3-1`](issues/P3-1.md) | P3 | **verified** | withPrefix() 在构造器不兼容的子类上仍会失败（Error: $logger must not be accessed before … |
| [`P3-2`](issues/P3-2.md) | P3 | **verified** | setMultiple([]) 分叉：RedisCache 下发 mset([])（phpredis 语义下为 … |
| [`P3-3`](issues/P3-3.md) | P3 | **closed** | 六个 issue 前置字段仍为 "open"，但代码已修复——issue 元数据与实际代码状态不一致。 |
| [`G1`](issues/G1.md) | - | **verified** | CI 文件名与工作流名：本模块使用 `.github/workflows/ci.yml`、`name: CI`。工作区标准是 … |
| [`G2`](issues/G2.md) | - | **verified** | 严格开关：`phpunit.xml.dist` … |
| [`G3`](issues/G3.md) | - | **verified** | logger 是可选的，而默认就是静默：`ArrayCache::__construct(?LoggerInterface $logger = … |

## 未关闭

_无未关闭条目——本模块每条都已是 `verified` 或 `closed`。_

## 结论

2026-09-29 选定的 logger 必填要求已在两个实现里真正落地，两个驱动现在对「非整数计数器」的一致答复相同。

## 本轮已修复确认

G3 (both constructors now require a LoggerInterface and the NullLogger fallback is gone) and P2-5 (incr/decr agree on a non-integer) were both verified by mutation — reverting either guard turns the module’s own test red.

## 测试盲区

默认套件排除了 @group integration，而单测用的 FakeRedis 没有 eval()，因此 RedisCache 的 Lua 计数器脚本与 MULTI 管道在本机从不执行——P2-5 的「两实现同答」只被 ArrayCache 一侧钉住。ArrayCacheTest 里有个子类夹具不调用父构造器，其 logger 处于未初始化态，用例只是恰好不触发日志才通过。

## 验证方式

- `./vendor/bin/phpunit` · `composer analyse` · `composer validate`
- `phpunit.xml.dist` 中的 warning/notice/deprecation/risky 开关：四个全开
- 只有在上述开关打开时 PHP 警告才会导致套件失败；否则请显式加 `--fail-on-warning`。
