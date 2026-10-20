# lab-php86-clamp

[English](README.en.md)

PHP 8.6 で追加される [`clamp()`](https://wiki.php.net/rfc/clamp_v2) の挙動を、公式 Docker イメージの 8.6.0RC2 で確かめるための検証環境。2026年9月30日に作成。

## 使い方

```sh
sh run.sh        # すべてのテスト（A〜F）を実行し、出力を logs/ に上書きする
sh run.sh a c    # テスト A と C だけ
```

必要なもの:

- Docker（テスト A〜D と、F の 8.6 側）。初回は `php:8.6.0RC2-cli-alpine` を pull する。
- ホストの PHP 8.0〜8.5 と Composer（テスト E と、F の比較側）。無ければ E は飛ばし、F は 8.6 側だけを記録する。

つまずきやすい点:

- `logs/` は実行のたびに上書きされる。A・B・C・E・F は何度実行しても同じ内容になるが、D の数値は実行ごと・マシンごとに数 ns 揺れるので、`git diff` に出るのは正常。
- イメージは `PHP86_IMAGE`、ホストの PHP は `PHP85` で差し替えられる（例: `PHP86_IMAGE=php:8.6-rc-cli-alpine sh run.sh a`）。
- E は `polyfill/` で `composer install` を実行する。Composer のホームとキャッシュは `.composer-home/`・`.composer-cache/` に閉じ込めてあり、ホスト側の設定やキャッシュには触れない。
- F の `Fatal error` は期待どおりの結果。`run.sh` はコマンドが失敗しても止まらずに記録を続ける。

## 構成

| パス | 中身 |
|---|---|
| `run.sh` | テストを実行して `logs/` に記録する。8.6 側は Docker、比較側はホストの PHP で、どちらも `-n`（php.ini なし）で動かす |
| `tests/lib.php` | 表示用のヘルパー。式を文字列のまま `eval` し、式と結果（または例外）を1行に並べるので、表示と実行がずれない |
| `tests/a-basics.php` | A: RFC の例とシグネチャ |
| `tests/b-usecases.php` | B: 使いどころ |
| `tests/c-gotchas.php` | C: つまずきそうな挙動 |
| `tests/d-bench.php` | D: 速度の比較。`run.sh` が opcache なしと opcache＋トレーシング JIT の2回実行する |
| `tests/f-*.php` | F: 自前の `clamp()` がすでにある場合（無条件のグローバル定義、`function_exists()` で囲んだ定義、名前空間つきの定義） |
| `polyfill/` | E: `symfony/polyfill-php86` を入れるための Composer プロジェクト |
| `logs/` | 検証時の記録。ファイル名の先頭がテストの記号 |

## 検証結果（2026年9月30日、PHP 8.6.0RC2）

比較側は PHP 8.5.9 と `symfony/polyfill-php86` v1.43.0。

| # | やったこと | 結果 |
|---|---|---|
| A | RFC の例をそのまま実行 | RFC どおり。シグネチャは `clamp(mixed $value, mixed $min, mixed $max): mixed`。`$min`・`$max` が NAN のとき、`$min > $max` のときは `ValueError`。`$value` が NAN ならそのまま返る |
| B | 使いどころの例（入力値の丸め、割合、`acos()` の前処理、バックオフ、色と添字、日付） | すべて期待どおり。`acos(1.0000000002)` は NAN だが、`clamp()` を挟むと 0 |
| C | つまずきそうな値を渡す | 型変換はしない（`clamp("50", 1, 100)` は文字列の `"50"`、`clamp("150", 1, 100)` は int の `100`）。`null`・`""` は下限、`"abc"` は上限になり、エラーにはならない。空配列での `clamp(3, 0, count([]) - 1)` は `ValueError`（`max(0, min(...))` は 0 を返していた） |
| D | 500万回の速度比較 | ループ分を引いた1回あたりで、JIT なしは `clamp()` 18.4 ns・インラインの三項演算子 7.1 ns・ヘルパー関数 32〜34 ns。JIT ありは 9.8 ns・1.0 ns・7〜8 ns。速度は決め手にならない |
| E | PHP 8.5.9 で、polyfill を入れて A と C を再実行 | polyfill なしでは `function_exists("clamp")` が false。polyfill ありでは、ヘッダ行を除いて 8.6 の出力と1バイトも違わない（`logs/e-diff.log`） |
| F | 自前の `clamp()` がある状態で 8.6 へ | 無条件のグローバル定義は `Fatal error: Cannot redeclare function clamp()`。`function_exists()` で囲んだものは黙ってネイティブに切り替わり、`clamp(5, 10, 1)` の結果が `10` から `ValueError` に変わる。名前空間つきの関数は影響を受けない |

A〜C・E・F は3回実行して、ログがすべて一致することを確かめた。D は実行のたびに数 ns 揺れるが、傾向は変わらない（`logs/` にあるのは最後の実行の記録）。

### 経緯

| 時期 | 出来事 |
|---|---|
| 2021-06-23 | Kim Hallberg（@thinkverse）が [RFC: clamp](https://wiki.php.net/rfc/clamp) と [php/php-src#7191](https://github.com/php/php-src/pull/7191) を公開。シグネチャは `int\|float` 限定 |
| 2021-07 | 8.1 の投票期限に間に合わず、対象を 8.2 に変更 |
| 2023-06-12 | 投票に進まないまま、作者がリソース不足を理由に取り下げ。PR は未マージでクローズ |
| 2025-08-09 | @kylekatarnls が [php/php-src#19434](https://github.com/php/php-src/pull/19434) で引き継ぎ、[RFC: clamp v2](https://wiki.php.net/rfc/clamp_v2) を公開（2025-08-21 付） |
| 2025-11-18 | 投票終了。賛成23・反対3・棄権6 で可決 |
| 2025-12-18 | マージ（コミット `06ea0e5`）。NEWS のクレジットは kylekatarnls と thinkverse の連名 |
