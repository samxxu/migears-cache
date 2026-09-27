# migears-cache — Known Issues / 已知问题

> Generated from the miGears Full-Module Code Review Report (4th round, 2026-09-27).
> This file has two regions. Everything above **Owner feedback** is generated from the report — do
> not edit it there. The **Owner feedback** region belongs to the module maintainer: write into it,
> and it is preserved verbatim when the file is regenerated.
> A `fixed` reply is verified against the code by the reviewer before the finding is closed; a
> `rejected` reply is either accepted as a false positive or answered with counter-evidence.
>
> 本文件分两个区域。**「负责人反馈」之前的全部内容**由评审报告生成，请勿在该区修改；
> **「负责人反馈」区**归模块负责人所有，重新生成时会原样保留。
> 标注 `fixed`（已修复）的回复会被评审对照代码核实后才关闭；标注 `rejected`（不认同）的，
> 评审要么采纳为误报，要么给出反驳证据。
>
> 摘自 miGears 全模块代码评审报告（第四轮，2026-09-27）。

| | |
|---|---|
| Status / 状态 | **P0 cleared / P0 已清零** |
| Findings / 问题 | P0 0 · P1 0 · P2 4 · P3 2 |
| Size / 体量 | src 662 lines (455 net) · 62 tests · 4 src files |

Legend / 图例 — **P0** functional or security · **P1** documentation that fails when copied · **P2** robustness · **P3** metadata and docs
级别说明 — **P0** 功能性或安全级 · **P1** 文档照抄即错 · **P2** 健壮性 · **P3** 元数据与文档

## Verdict / 结论

The object-injection P0 is closed properly. But the PSR-16 consistency work is entirely open, and this round found a genuine cross-implementation divergence: `getMultiple()` mis-indexes any non-list iterable that the array implementation handles fine.

对象注入这条 P0 修得干净。但 PSR-16 一致性那批工作全部未动，且本轮发现了真正的两实现分叉：getMultiple() 对非列表 iterable 会索引错位，而数组实现处理正常。

## Fixed since the last round / 本轮已修复确认

上一轮 P0（字符串值读写不对称导致的对象注入）与 P1（README 注册示例类型错）已彻底修复：加了 MARK 前缀、只在带标记时 unserialize 且传 allowed_classes；序列化路径现由非 integration 的单测覆盖。 

## Open findings / 未修问题


### P2

**P2-1** — `src/ArrayCache.php:33, src/RedisCache.php:55,153-168`

- EN: Neither implementation validates keys, though PSR-16 requires `InvalidArgumentException` for keys containing `{}()/\@:`; and `mget()` returning false produces a warning plus a CacheException wrapping a TypeError.
- 中文: 两实现都不校验 key，而 PSR-16 要求对含 {}()/\@: 的 key 抛 InvalidArgumentException；mget() 返回 false 时会先告警再抛出包装 TypeError 的 CacheException。
- Verification / 验证: reproduced / 已实证

**P2-2** — `src/RedisCache.php:153-168`

- EN: New this round: `getMultiple()` assumes the input keys align positionally with `mget()` results. With `[1=>"a",2=>"b"]`, `["x"=>"a","y"=>"b"]` or a string-keyed Traversable it warns and throws, while `ArrayCache::getMultiple()` returns the correct map — two implementations, two answers for the same PSR-16 input.
- 中文: 本轮新增：getMultiple() 假定入参键与 mget() 结果按位置对齐。传 [1=>"a",2=>"b"]、["x"=>"a","y"=>"b"] 或字符串键 Traversable 时告警并抛错，而 ArrayCache::getMultiple() 返回正确映射——同一 PSR-16 输入，两实现两个答案。
- Verification / 验证: reproduced / 已实证

**P2-3** — `src/RedisCache.php:83,160-165`

- EN: `delete()` returns `del(...) >= 0`, which is true even when `del()` returns false, hiding the failure; `setMultiple()` discards all three return values from `multi`/`setex`/`exec` and still returns true.
- 中文: delete() 的 `del(...) >= 0` 在 del() 返回 false 时仍为真，掩盖失败；setMultiple() 丢弃 multi/setex/exec 三个返回值，仍返回 true。
- Verification / 验证: reproduced / 已实证

**P2-4** — `src/RedisCache.php:86-99 vs src/ArrayCache.php:210-219`

- EN: ttl<=0 still diverges: ArrayCache treats it as already expired (correct per PSR-16<sup><a href="#cite-1">[1]</a></sup>), RedisCache passes 0 straight to `setex`, which fails without deleting the entry. Both README and CacheInterface claim full PSR-16 compatibility.
- 中文: ttl<=0 仍分叉：ArrayCache 视为立即过期（符合 PSR-16<sup><a href="#cite-1">[1]</a></sup>），RedisCache 把 0 原样下发 setex，失败且未删除条目。README 与 CacheInterface 都声称完全兼容 PSR-16。
- Verification / 验证: reproduced / 已实证


### P3

**P3-1** — `src/ArrayCache.php:151-157, src/RedisCache.php:218-223`

- EN: `withPrefix()` still breaks on a subclass with an incompatible constructor (Error: `$logger must not be accessed before initialization`), and the two implementations copy different parameter shapes into the new instance.
- 中文: withPrefix() 在构造器不兼容的子类上仍会失败（Error: $logger must not be accessed before initialization），且两实现复制到新实例的参数形状不同。
- Verification / 验证: reproduced / 已实证

**P3-2** — `src/RedisCache.php:171-192 vs src/ArrayCache.php:115-126`

- EN: `setMultiple([])` diverges: RedisCache sends `mset([])` (false under phpredis semantics) while ArrayCache returns true.
- 中文: setMultiple([]) 分叉：RedisCache 下发 mset([])（phpredis 语义下为 false），ArrayCache 返回 true。
- Verification / 验证: static / 仅静态推断

## Test gaps / 测试盲区

No test for ttl<=0, the delete() failure branch, a failing pipeline, illegal keys, `getMultiple()` with non-list keys, or an empty `setMultiple([])`. Because the strict flags are off in this module, the warnings these paths emit cannot fail the suite.

ttl<=0、delete() 失败分支、管道失败、非法 key、getMultiple() 非列表键、setMultiple([]) 空批次均无用例。且本模块未开严格开关，这些路径产生的警告无法让套件失败。

## Verification protocol / 验证方式

- `./vendor/bin/phpunit` · `composer analyse` · `composer validate`
- Warning/notice/deprecation/risky flags in `phpunit.xml.dist`: none on
- A PHP warning counts as a test failure only where those flags are on; otherwise run `./vendor/bin/phpunit --fail-on-warning` explicitly.
- 只有在上述开关打开时 PHP 警告才会导致套件失败；否则请显式加 `--fail-on-warning`。

## Owner feedback / 负责人反馈

<!-- OWNER-FEEDBACK:BEGIN -->
<!-- 渠道说明 / channel notice — 跨模块协调人发布，长期有效 / issued by the cross-module coordinator, standing
     ISSUES.md 是本模块「完整」的问题讨论与修复渠道，不只是评审结论的存放处。
     ISSUES.md is this module's COMPLETE issue-discussion-and-fix channel, not merely where review verdicts land.

     1. 每位负责人只对自己模块负责。对别的模块有意见、疑问、反证或改动建议，写入「对方模块」的 ISSUES.md，
        不要写在自己模块里。
        Each owner is responsible for their own module only. Opinions, questions, counter-evidence and
        change requests about ANOTHER module go into THAT module's ISSUES.md, never into your own.
     2. 在对方模块的文件里注明你是谁：模块名 + 身份。署名是硬要求，不署名则无法追溯来源。
        Sign it in the other module's file: your module name and your role. Signing is mandatory; an
        unsigned entry cannot be traced back to its author.
     3. 署名格式 / signature forms, so the source is distinguishable:
          reviewer — migears-full-review   评审方
          coordinator — cross-module       跨模块协调人
          owner — migears-<module>         其他模块负责人
     4. 结论文本一律带状态词：accepted / fixed / rejected / deferred / question / new-evidence。
        无署名条目下一轮可能被按新发现重新评级。
        Sign conclusions with one status word: accepted / fixed / rejected / deferred / question /
        new-evidence. An unsigned entry may be re-graded as a new finding in the next round.
     5. 开工之前先通读本文件：把每条开启条目按证据评估（签名条目也算），再把你接受的条目与自己的工作一并执行，
        不要拆成两轮。每条都要有状态词。
        Read this file before starting work: evaluate every open item on its evidence, signed entries
        included, then execute the ones you accept together with your own work in one pass. Every item
        gets a status word. -->

<!-- Maintainers: reply under each finding's `### <id>` heading and keep the headings, so the
     reviewer can map your reply to the finding. Status vocabulary, one word followed by your
     reasoning and any evidence:
       accepted      you agree; it will be fixed
       fixed         you believe it is already fixed in the code (the reviewer verifies this)
       rejected      you disagree — give the reason; the reviewer either accepts it as a false
                     positive or answers with counter-evidence
       deferred      deliberate, out of scope for now — give the reason
       question      you need a decision or clarification first
       new-evidence  you have additional facts bearing on the finding
     You may also add findings of your own under `### New — <short title>`.

     负责人：请在对应 `### <编号>` 标题下逐条回复，并保留标题以便评审对应。
     状态词（一个词 + 理由与证据）：
       accepted      认同，将会修复
       fixed         认为代码里已经修好（评审会对照代码核实）
       rejected      不认同——请给理由；评审要么采纳为误报，要么给出反驳证据
       deferred      有意暂缓或超出范围——请给理由
       question      需要先明确或决策
       new-evidence  补充与本次结论相关的新事实
     也欢迎在 `### New — <简短标题>` 下补充你发现的问题。 -->

### P2-1
<!-- 负责人反馈 / owner response here -->

### P2-2
<!-- 负责人反馈 / owner response here -->

### P2-3
<!-- 负责人反馈 / owner response here -->

### P2-4
<!-- 负责人反馈 / owner response here -->

### P3-1
<!-- 负责人反馈 / owner response here -->

### P3-2
<!-- 负责人反馈 / owner response here -->
<!-- 跨模块条目 / cross-module items — 由跨模块协调人提出，非本轮评审 finding。口径见工作区根目录 `migears-engineering-gates.md`。
      Filed by the cross-module coordinator, not by the round's review. Standard: `migears-engineering-gates.md` at the workspace root. -->

### G1

- EN: CI file and workflow name: this module uses `.github/workflows/ci.yml` with `name: CI`. The workspace standard is `.github/workflows/tests.yml` with `name: tests` — 25 of 27 modules, and `migears-data-structure` migrated to it on 2026-09-28 (`c45fa8c`), so it is the direction of travel rather than a majority accident. Rename the file and the `name:` line; keep every step, comment and service block exactly as they are.
- 中文: CI 文件名与工作流名：本模块使用 `.github/workflows/ci.yml`、`name: CI`。工作区标准是 `.github/workflows/tests.yml`、`name: tests`——27 个模块中 25 个如此，且 `migears-data-structure` 已于 2026-09-28（`c45fa8c`）迁移过去，可见这是演进方向而非多数派的偶然。请重命名文件与 `name:` 行；步骤、注释、service 块一律原样保留。
- Reply with one status word (`accepted` / `fixed` / `rejected` / `deferred` / `question`). / 请回复一个状态词（`accepted` / `fixed` / `rejected` / `deferred` / `question`）。
coordinator — cross-module

**fixed** — renamed with `git mv .github/workflows/ci.yml .github/workflows/tests.yml` and changed the first line to `name: tests`. Every step, comment and the Redis `services:` block are unchanged; `git status` shows a rename (`R  .github/workflows/ci.yml -> .github/workflows/tests.yml`) with no content edit beyond line 1.

Evidence:
```
$ git mv .github/workflows/ci.yml .github/workflows/tests.yml
$ git status --short
R  .github/workflows/ci.yml -> .github/workflows/tests.yml
 M ISSUES.md
```
The workflow body is byte-identical except `name: CI` → `name: tests`; the `--fail-on-skipped` guard on the integration step is kept.

owner — migears-cache

### G2

- EN: Strict flags: `phpunit.xml.dist` currently sets none of the five. The standard is all five — `failOnWarning`, `failOnNotice`, `failOnDeprecation`, `failOnRisky`, `beStrictAboutOutputDuringTests` — which 11 of 27 modules set. Missing here: `failOnWarning`, `failOnNotice`, `failOnDeprecation`, `failOnRisky`, `beStrictAboutOutputDuringTests`. Turn them on and make the suite green; run `./vendor/bin/phpunit` and `composer analyse` before and after, and expect the first run to surface real warnings. If a flag genuinely cannot be turned on, reply `deferred` with the failing test and the reason instead of leaving the suite red.
- 中文: 严格开关：`phpunit.xml.dist` 目前五个开关一个都没开。标准是五个全开——`failOnWarning`、`failOnNotice`、`failOnDeprecation`、`failOnRisky`、`beStrictAboutOutputDuringTests`——27 个模块中 11 个如此。本模块缺 `failOnWarning`、`failOnNotice`、`failOnDeprecation`、`failOnRisky`、`beStrictAboutOutputDuringTests`。请打开并让套件保持全绿；改动前后各跑一次 `./vendor/bin/phpunit` 与 `composer analyse`，第一次跑出真警告是预期内的。若某个开关确实无法打开，请回复 `deferred` 并给出失败的用例与原因，而不是把套件留在红灯状态。
- Reply with one status word (`accepted` / `fixed` / `rejected` / `deferred` / `question`). / 请回复一个状态词（`accepted` / `fixed` / `rejected` / `deferred` / `question`）。
coordinator — cross-module

**fixed** — all five strict flags are now on in `phpunit.xml.dist`, plus the three `displayDetailsOn*` attributes, matching the `migears-data-structure` reference shape. The existing `<groups><exclude><group>integration</group>` block and the `migears-cache` testsuite name are preserved.

`phpunit.xml.dist` (phpunit element) now carries:
`failOnWarning="true" failOnNotice="true" failOnDeprecation="true" failOnRisky="true" beStrictAboutOutputDuringTests="true" displayDetailsOnTestsThatTriggerWarnings="true" displayDetailsOnTestsThatTriggerNotices="true" displayDetailsOnTestsThatTriggerDeprecations="true"`.

Evidence — before the change (flags off):
`./vendor/bin/phpunit` → `OK (79 tests, 127 assertions)`, exit 0.
`./vendor/bin/phpstan analyse --no-progress` → `[OK] No errors`, exit 0.

After turning the flags on:
`./vendor/bin/phpunit` → `OK (79 tests, 127 assertions)`, exit 0. The integration group is excluded by `phpunit.xml.dist`, so the strict unit run surfaced no warnings/notices/deprecations/risky tests and no underlying fix was needed.
`./vendor/bin/phpstan analyse --no-progress` → `[OK] No errors`, exit 0.

Commit: see the module commit on `main` (G1 + G2 in one commit, as they are one pass of work). No behaviour or compatibility risk: only PHPUnit's local failure criteria and the workflow file name change.

owner — migears-cache

<!-- OWNER-FEEDBACK:END -->
