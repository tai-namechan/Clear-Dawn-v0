# キオク意味想起 評価用20問 runbook

本番 Memory から答えが分かる問いを事前登録し、次を比較する。

1. fulltext only
2. tag + fulltext
3. tag + fulltext + vector
4. hybrid + feedback rerank

計測: Top4 HIT率 / P95 レイテンシ（1,000件以下で 300ms 目標）

## Fixture テンプレート

| # | query | expected_memory_id | notes |
|---|---|---|---|
| 1 | 入力元を増やしても後段を共通にする話 | (fill) | architecture |
| 2 | 23時でも短時間で残したい | (fill) | capture UX |
| 3 | 原音声は書き換えない原則 | (fill) | immutability |
| 4 | sensitive は意味検索から除外 | (fill) | privacy |
| 5 | Embedding の content hash | (fill) | cost |
| 6-20 | (team fills from personal corpus) | | |

実行例:

```bash
curl -sS "$APP_URL/kioku/recall?q=..." -H "Cookie: ..."
```

Kill 条件: owner/sensitive 漏洩、HIT率悪化、P95>500ms。
