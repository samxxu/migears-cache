# migears-cache — Known Issues / 已知问题

> Summary of this module's issues. The items themselves are in [`issues/`](issues/README.md), one file
> per item: a front-matter header and a thread. This file is generated from them and can be rewritten at
> any time; edit an item, never this file.
>
> 本模块问题的概览。条目本体在 [`issues/`](issues/README.md)，一条目一文件：前置字段加讨论串。
> 本文件由条目生成，随时可以整段重写；请改条目，不要改本文件。
>
> From the miGears Full-Module Code Review Report (4th round, 2026-09-27).

| | |
|---|---|
| Status / 状态 | **P0 cleared / P0 已清零** |
| Size / 体量 | src 662 lines (455 net) · 62 tests · 4 src files |

Legend / 图例 — **P0** functional or security · **P1** documentation that fails when copied · **P2** robustness · **P3** metadata and docs
级别说明 — **P0** 功能性或安全级 · **P1** 文档照抄即错 · **P2** 健壮性 · **P3** 元数据与文档

## At a glance / 状态一览

| | |
|---|---|
| Items / 条目 | P0 0 · P1 0 · P2 4 · P3 2 · other 2 |
| Answered / 已回复 | 2 of 8 |
| Waiting / 等待回复 | `P2-1`, `P2-2`, `P2-3`, `P2-4`, `P3-1`, `P3-2` |

| id | level | status | title |
|---|---|---|---|
| [`P2-1`](issues/P2-1.md) | P2 | **open** | Neither implementation validates keys, though PSR-16 requires … |
| [`P2-2`](issues/P2-2.md) | P2 | **open** | New this round: `getMultiple()` assumes the input keys align … |
| [`P2-3`](issues/P2-3.md) | P2 | **open** | `delete()` returns `del(...) >= 0`, which is true even when `del()` … |
| [`P2-4`](issues/P2-4.md) | P2 | **open** | ttl<=0 still diverges: ArrayCache treats it as already expired (correct … |
| [`P3-1`](issues/P3-1.md) | P3 | **open** | `withPrefix()` still breaks on a subclass with an incompatible … |
| [`P3-2`](issues/P3-2.md) | P3 | **open** | `setMultiple([])` diverges: RedisCache sends `mset([])` (false under … |
| [`G1`](issues/G1.md) | - | **fixed** | CI file and workflow name: this module uses `.github/workflows/ci.yml` … |
| [`G2`](issues/G2.md) | - | **fixed** | Strict flags: `phpunit.xml.dist` currently sets none of the five. The … |

## Verdict / 结论

The object-injection P0 is closed properly. But the PSR-16 consistency work is entirely open, and this round found a genuine cross-implementation divergence: `getMultiple()` mis-indexes any non-list iterable that the array implementation handles fine.

对象注入这条 P0 修得干净。但 PSR-16 一致性那批工作全部未动，且本轮发现了真正的两实现分叉：getMultiple() 对非列表 iterable 会索引错位，而数组实现处理正常。

## Fixed since the last round / 本轮已修复确认

上一轮 P0（字符串值读写不对称导致的对象注入）与 P1（README 注册示例类型错）已彻底修复：加了 MARK 前缀、只在带标记时 unserialize 且传 allowed_classes；序列化路径现由非 integration 的单测覆盖。 

## Test gaps / 测试盲区

No test for ttl<=0, the delete() failure branch, a failing pipeline, illegal keys, `getMultiple()` with non-list keys, or an empty `setMultiple([])`. Because the strict flags are off in this module, the warnings these paths emit cannot fail the suite.

ttl<=0、delete() 失败分支、管道失败、非法 key、getMultiple() 非列表键、setMultiple([]) 空批次均无用例。且本模块未开严格开关，这些路径产生的警告无法让套件失败。

## Verification protocol / 验证方式

- `./vendor/bin/phpunit` · `composer analyse` · `composer validate`
- Warning/notice/deprecation/risky flags in `phpunit.xml.dist`: all four on
- A PHP warning counts as a test failure only where those flags are on; otherwise run `./vendor/bin/phpunit --fail-on-warning` explicitly.
- 只有在上述开关打开时 PHP 警告才会导致套件失败；否则请显式加 `--fail-on-warning`。
