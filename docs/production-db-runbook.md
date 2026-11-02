# 手順書: 本番DBの変換と反映（DB持ち出し → 変換 → 本番反映）

本番DB内のURLなど、DB全体の文字列を一括変換して本番へ反映するときの手順。
DBやURLに依存しないテーマ単体の変更には不要で、対象ファイルだけを転送すればよい。

## 前提

- 本番環境（XREA）へはSSH接続できるが、**WP-CLIは使用できない**。DBのエクスポート/
  インポートは`mysqldump`/`mysql`コマンドを直接使い、変換だけを開発環境のWP-CLIで行う
- `mysqldump`/`mysql`が使えるかは`which mysqldump`で事前に確認する。使えない場合は、
  手順1・8・10をphpMyAdminのエクスポート/インポートに置き換える
- 手順内の`<OLD>`/`<NEW>`は変換前後の文字列を指す。URLの場合は **末尾スラッシュを
  付けない**。`siteurl`/`home`は通常末尾スラッシュなしで保存されているため、
  付けて検索すると変換漏れになる

## 実施前に確認すること

- `<PROD_SSH_USER>` / `<PROD_HOST>`: 本番SSH接続情報
- 本番のWordPressディレクトリパス
- 本番DBのおおよそのサイズ（転送時間の見積もりのため）
- 実施タイミング（アクセスの少ない時間帯を推奨）

## 全体の流れ

1. 本番DBのフルバックアップ（ロールバック用、必ず先に取得）
2. バックアップを開発環境に転送
3. 開発環境の**隔離用DB**にインポート（既存の開発・テスト用DBを壊さないため）
4. dry-runで対象件数を確認 → `wp search-replace` を実行
5. 結果を検証
6. 変換済みDBをエクスポート
7. 本番に転送
8. 本番側で直前にバックアップを取ってからインポート
9. 本番で最終確認
10. 問題があればロールバック

## 詳細手順

### 1. 本番DBのバックアップ

```bash
ssh <PROD_SSH_USER>@<PROD_HOST>
cd <本番のWordPressディレクトリ>   # wp-config.phpがある場所

# DB接続情報を確認（DB_NAME/DB_USER/DB_PASSWORD/DB_HOST）
grep -E "DB_NAME|DB_USER|DB_PASSWORD|DB_HOST" wp-config.php
```

確認した値を使ってダンプする。フラグの意味:

- `--single-transaction` … InnoDBをロックせず整合性を保ったままダンプ（サイト稼働中でも安全）
- `--no-tablespaces` … 共有ホスティングのDBユーザーは`PROCESS`権限を持たないことが多く、
  付けないと`mysqldump`がエラーで止まることがある
- `--default-character-set=utf8mb4` … 日本語コンテンツの文字化けを防ぐ

```bash
mysqldump -h <DB_HOST> -u <DB_USER> -p \
  --single-transaction --no-tablespaces --default-character-set=utf8mb4 \
  <DB_NAME> > ~/backup_before_migration_$(date +%Y%m%d%H%M%S).sql
```

`-p` の後にパスワードを直接書かず対話入力にすることで、コマンド履歴にパスワードが
残るのを避ける。実行後、ファイルサイズが0でないこと・末尾に
`-- Dump completed on ...` の行があることを確認する。

```bash
ls -la ~/backup_before_migration_*.sql
tail -3 ~/backup_before_migration_*.sql
```

### 2. バックアップを開発環境に転送

```bash
scp <PROD_SSH_USER>@<PROD_HOST>:~/backup_before_migration_*.sql \
    mayumi@192.168.100.21:/home/mayumi/work/kikujo/backups/
```

直接経路がない場合はMacを経由（本番→Mac→Raspberry Pi）で構わない。

### 3. 開発環境の隔離用DBにインポート

**dev/test用の`wordpress`DBを直接上書きしない**。同じMySQLコンテナ内に別名のDBを作って、
そこにインポートする。

```bash
# 隔離用DBを作成
docker exec kikujo_mysql mysql -uroot -prootpassword \
  -e "CREATE DATABASE IF NOT EXISTS wordpress_prod_migration;"
docker exec kikujo_mysql mysql -uroot -prootpassword \
  -e "GRANT ALL PRIVILEGES ON wordpress_prod_migration.* TO 'wordpress'@'%'; FLUSH PRIVILEGES;"

# バックアップをインポート（--default-character-set=utf8mb4 で日本語の文字化けを防ぐ）
docker exec -i kikujo_mysql mysql --default-character-set=utf8mb4 \
  -uwordpress -pwordpress_password wordpress_prod_migration \
  < /home/mayumi/work/kikujo/backups/backup_before_migration_YYYYMMDDHHMMSS.sql
```

### 4. dry-run確認 → search-replace実行

WP-CLIはwp-config.php経由でDB名を読むため、`docker exec -e` で一時的に
`WORDPRESS_DB_NAME` を上書きして、隔離用DBに向けて実行する
（`html/wp-config.php` の `getenv_docker('WORDPRESS_DB_NAME', 'wordpress')` を利用）。

```bash
# dry-run
docker exec -e WORDPRESS_DB_NAME=wordpress_prod_migration kikujo_wordpress \
  wp search-replace '<OLD>' '<NEW>' \
  --dry-run --report-changed-only --allow-root --path=/var/www/html

# 件数を確認したら本実行
docker exec -e WORDPRESS_DB_NAME=wordpress_prod_migration kikujo_wordpress \
  wp search-replace '<OLD>' '<NEW>' \
  --report-changed-only --allow-root --path=/var/www/html
```

URLを変換した場合は、プロトコルを外したホスト名でも再検索して変換漏れがないか確認する。
0件でなければ、プロトコル相対URLなど別の書き方が残っている可能性がある。

```bash
docker exec -e WORDPRESS_DB_NAME=wordpress_prod_migration kikujo_wordpress \
  wp search-replace '<OLDのホスト名>' '<NEWのホスト名>' \
  --dry-run --report-changed-only --allow-root --path=/var/www/html
```

### 5. 検証

```bash
docker exec -e WORDPRESS_DB_NAME=wordpress_prod_migration kikujo_wordpress \
  wp option get siteurl --allow-root --path=/var/www/html
docker exec -e WORDPRESS_DB_NAME=wordpress_prod_migration kikujo_wordpress \
  wp option get home --allow-root --path=/var/www/html
```

代表的な投稿の`guid`・本文中のリンクが正しく変換されているかも確認する。

```bash
docker exec kikujo_mysql mysql -uwordpress -pwordpress_password wordpress_prod_migration \
  -e "SELECT guid FROM wp_posts WHERE post_status='publish' LIMIT 5;"
```

### 6. 変換済みDBをエクスポート

```bash
docker exec kikujo_mysql mysqldump --default-character-set=utf8mb4 \
  -uwordpress -pwordpress_password wordpress_prod_migration \
  > /home/mayumi/work/kikujo/backups/wordpress_prod_migrated_$(date +%Y%m%d%H%M%S).sql
```

### 7. 本番へ転送

```bash
scp /home/mayumi/work/kikujo/backups/wordpress_prod_migrated_*.sql \
    <PROD_SSH_USER>@<PROD_HOST>:~/
```

### 8. 本番へのインポート

手順1から時間が経っている場合、その間の更新分を取りこぼさないよう、作業直前にもう一度
バックアップを取る。インポートはテーブルを丸ごと置き換えるため、事前バックアップは必須。

```bash
ssh <PROD_SSH_USER>@<PROD_HOST>
cd <本番のWordPressディレクトリ>

mysqldump -h <DB_HOST> -u <DB_USER> -p \
  --single-transaction --no-tablespaces --default-character-set=utf8mb4 \
  <DB_NAME> > ~/backup_immediately_before_import_$(date +%Y%m%d%H%M%S).sql

# 変換済みDBを流し込む（テーブルを丸ごと置き換え）
mysql -h <DB_HOST> -u <DB_USER> -p --default-character-set=utf8mb4 \
  <DB_NAME> < ~/wordpress_prod_migrated_YYYYMMDDHHMMSS.sql
```

### 9. 本番での最終確認

- トップページがブラウザで正しく表示されるか
- `wp-admin` にログインできるか、管理画面のリンクが正しいか
- パーマリンク（記事詳細ページ）が404にならないか
  - 万一崩れる場合は管理画面「設定 > パーマリンク」を開いて「変更を保存」を押すだけで
    `.htaccess` の書き換えルールが再生成される
- 画像・アップロードファイルが正しく表示されるか（`guid`だけでなく実ファイルの
  URLパスにも依存するため、ブラウザのネットワークタブで404が出ていないか確認）
- キャッシュ系プラグインを使っている場合はキャッシュを全クリア

### 10. ロールバック

問題が出た場合は、手順8で取得したバックアップを流し込めば直前の状態に戻せる。

```bash
mysql -h <DB_HOST> -u <DB_USER> -p --default-character-set=utf8mb4 \
  <DB_NAME> < ~/backup_immediately_before_import_YYYYMMDDHHMMSS.sql
```

## 補足

### シリアライズデータはSQLのREPLACE()で置換しない

`wp search-replace` はシリアライズされたPHPデータのバイト長プレフィックス(`s:NN:"..."`)も
再計算するが、phpMyAdminやSQLの`REPLACE()`は文字列を置換するだけでバイト長を更新しない。
シリアライズされた値に直接使うと`unserialize()`が失敗し、データが壊れる。

たとえば`theme_mods_kikujo-theme`にはURLを含むシリアライズ値が保存されている。
本番のDBを変換するときは、この手順のように開発環境のWP-CLIを経由する。

### wp-config.phpの WP_HOME / WP_SITEURL

DB内の`siteurl`/`home`が正しければ、本番で定数を定義する必要はない。開発環境で
`wp-config.php`にハードコードしているのは、nginxのリバースプロキシ越しに複数ホスト名を
扱うためで、開発環境特有の事情による。将来DBが巻き戻った場合の保険として本番に追記する
ことはできるが、必須ではない。
