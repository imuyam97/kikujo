# 開発用SSL証明書について

このディレクトリの証明書はすべて自己署名CA (`ca/ca-cert.pem`, CN: `Ballet Dev Root CA`) で
署名されています。このCAは他プロジェクト（ballet等）と共通で使い回している開発用ルートCAです。

- `ca/ca-cert.pem` … ルートCA証明書（Macに信頼させる対象はこれ）
- `sites/<name>.crt` / `sites/<name>.key` … 各ドメイン用のサーバー証明書（`generate-site-cert.sh`で発行）

## Mac(クライアント端末)でこのCAを信頼させる方法

Raspberry Pi（Docker実行ホスト、LAN IP: `192.168.100.21`）で動いているnginxにブラウザから
警告なしでHTTPSアクセスするには、Mac側でこの自己署名CAをルート証明書として信頼させる必要があります。

> 補足: すでに別プロジェクト（ballet等）で同じ `Ballet Dev Root CA` を信頼済みの場合、
> このMacでは再設定不要です。Keychain Access で「Ballet Dev Root CA」を検索してヒットすれば
> 設定済みです。

### 1. 証明書ファイルをMacに転送

`docker/ssl/ca/ca-cert.pem` をAirDrop・`scp`・クラウドストレージなどでMacにコピーします。

```bash
scp <user>@192.168.100.21:/home/mayumi/work/kikujo/docker/ssl/ca/ca-cert.pem ~/Downloads/
```

### 2. Keychain Accessに登録して信頼する

**方法A: ターミナル（推奨・確実）**

```bash
sudo security add-trusted-cert -d -r trustRoot \
  -k /Library/Keychains/System.keychain ~/Downloads/ca-cert.pem
```

Macのログインパスワードを求められます。これでシステム全体（Safari/Chrome含む）から信頼されます。

**方法B: GUI（Keychain Access.app）**

1. `ca-cert.pem` をダブルクリック → Keychain Access が開き、証明書が追加される
2. 追加先は「システム」キーチェーンを選択（ログイン用ではなく）
3. Keychain Access内で「Ballet Dev Root CA」を検索し、ダブルクリックして詳細を開く
4. 「信頼」セクションを展開し、「この証明書を使用するとき」を **「常に信頼」** に変更
5. パネルを閉じ、Macのパスワードを入力して確定

### 3. 名前解決の設定（`/etc/hosts`）

`kikujo.com` は現時点で本番DNSに向いていないため、Mac側で開発ホストのIPに直接向ける必要があります。

```
sudo sh -c 'echo "192.168.100.21  kikujo.com" >> /etc/hosts'
```

phpMyAdminにアクセスする場合は同様に `pma.kikujo.s325.xrea.com` も追加してください。

### 4. 動作確認

```bash
curl -v https://kikujo.com/
```

証明書エラーが出ず、`SSL certificate verify ok` 相当の結果になれば成功です。ブラウザでアクセスして
鍵アイコンに警告が出ないことも確認してください。

## 証明書を再発行した場合

新しいドメイン用に `generate-site-cert.sh` で証明書を発行しても、署名元CA自体は変わらないため、
Mac側でCAを信頼させる作業はドメイン追加のたびにやり直す必要はありません
（初回のみでOK。ドメインが変わったら `/etc/hosts` の追記だけ必要です）。

## `docker/nginx/nginx.conf` を編集したあとの注意（bind mountの罠）

`docker-compose.yml` では `nginx.conf` を単一ファイルとしてbind mountしている
（`./docker/nginx/nginx.conf:/etc/nginx/nginx.conf:ro`）。

編集ツールによっては保存時にファイルを新しいinodeで置き換える（一時ファイル書き込み→rename）ため、
その場合コンテナ側は古いinode（=編集前の内容）を参照し続け、`docker exec kikujo_nginx nginx -s reload`
を実行しても変更が反映されないことがある（同じ`docker/ssl`のようにディレクトリ単位でマウントしている
ものは、ディレクトリ経由の参照になるためこの問題は起きない）。

**症状**: `nginx.conf` を編集したのに、Mac等の外部から接続すると古い設定（古いserver_name、
古いSSL証明書など）が返ってくる。`docker exec kikujo_nginx md5sum /etc/nginx/nginx.conf` と
ホスト側の `md5sum docker/nginx/nginx.conf` を比較するとハッシュ値が食い違っていることで判別できる。

**対処**: `nginx -s reload` では直らないため、コンテナを作り直してbind mountを張り直す。

```bash
docker compose up -d --force-recreate nginx
```

`nginx.conf` を編集したら、`nginx -t` で構文チェックしたうえで、必ずこのコマンドで
`nginx` コンテナを再作成する運用にすること（`docker compose restart nginx` では不十分な場合がある）。
