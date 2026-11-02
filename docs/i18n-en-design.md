# kikujo.com 多言語対応（日本語をデフォルトに複数言語を追加できる設計）― 自作方式 設計

## Context

kikujo.comは開設したばかりでページ・記事数がまだ少ない。将来的に外国籍の利用者にも情報を届けたく、まず英語対応を追加したい。要件は「無料(ドメイン以外は費用をかけない)」「SEOを重視」「翻訳内容は手動で調整したい」「メンテナンスが楽」。加えて、**英語だけでなく3言語目以降の追加も既に想定されている**ため、2言語限定の設計ではなく、言語数に依存しない構造にする。

WPMLやPolylangなど既存プラグインも検討したが、`kikujo-theme`はヒーロー文言・連絡先・LINE設定などをCustomizer(`theme_mods`)で管理しており、これらをプラグインの文字列翻訳機能に載せるには結局テーマ側にも同程度の統合コードが必要になる。加えて既存プラグイン(Akismet, EWWW Image Optimizer等)以外の依存を増やしたくないという方針とも合わせ、**プラグインを追加せず`kikujo-theme`内に直接実装する自作方式**を採用する。対象範囲はサイト全体(固定ページ・お知らせ記事・アーカイブ・検索結果・404を含む)。

設計方針は「Polylangが内部でやっていることの、このサイトに必要な部分だけを最小コードで自前実装する」— 具体的には、追加言語のコンテンツは通常のWP投稿/固定ページとして作成し(編集者は使い慣れたブロックエディタでそのまま執筆・手動調整できる)、日本語側の投稿とpost metaで対応付けるだけ。URLルーティングは`functions.php`に既にある`kikujo_force_custom_templates()`(`/contact/`, `/news/`を独自テンプレートに振り分けている既存パターン)をそのまま拡張し、`add_rewrite_rule()`やパーマリンク再構築(`flush_rewrite_rules()`)を一切使わない — これにより本番デプロイ時に「パーマリンク設定を開いて保存し直す」といった追加の運用手順が発生しない。

**改訂メモ:**
- 投稿ステータスを新設する初期案は、wp-admin標準UIから選択する手段が無いという運用上の穴が見つかったため、`pre_get_posts`ベースの一つの仕組みに置き換えた
- 「英語専用ファイルを1本ずつ作る」設計は、3言語目以降を見据えて「コンテンツ種別ごとに1本の共通テンプレート、中身は言語コードで出し分け」という構造に変更した（詳細は下記）。これにより**新しい言語を追加するとき、テンプレートファイルを増やす必要が無くなった**

## 全体構成

- URL構造: `https://kikujo.com/{lang}/...`（`lang`は`en`・`zh`など。デフォルト言語である日本語だけはプレフィックス無し `https://kikujo.com/...`）。WPのネイティブなページ階層ではなく、既存の`kikujo_force_custom_templates()`と同じ「REQUEST_URIを正規表現で判定してテンプレートを差し替える」方式で実現
- **言語レジストリ**: サイトで扱う言語コードの一覧を1箇所で管理する
  ```php
  function kikujo_get_languages() {
      // 'ja' は常にデフォルト言語（URLプレフィックス無し）。
      // 新しい言語を追加するときは、ここに言語コードを1つ足すだけでよい。
      return array( 'ja', 'en' );
  }

  function kikujo_get_secondary_languages() {
      return array_values( array_diff( kikujo_get_languages(), array( 'ja' ) ) );
  }
  ```
- 言語判定: リクエストパスの先頭セグメントが、登録済みの追加言語コードのどれかに一致するかだけを見る（ハードコードで`en`を特別扱いしない）
  ```php
  function kikujo_current_lang() {
      static $lang = null;
      if ( $lang === null ) {
          $path = parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH );
          $first_segment = explode( '/', trim( (string) $path, '/' ) )[0] ?? '';
          $lang = in_array( $first_segment, kikujo_get_secondary_languages(), true )
              ? $first_segment
              : 'ja';
      }
      return $lang;
  }
  ```
- **文言の出し分け（`kikujo_t()`）**: 2引数(ja/en)ではなく、言語コードをキーにした連想配列を受け取る形にする。これにより3言語目を追加しても呼び出し側の書き方が変わらず、その場に1行足すだけで済む
  ```php
  function kikujo_t( array $strings ) {
      $lang = kikujo_current_lang();
      if ( isset( $strings[ $lang ] ) ) {
          return $strings[ $lang ];
      }
      return $strings['ja'] ?? reset( $strings );
  }
  ```
  呼び出し例:
  ```php
  echo esc_html( kikujo_t( array(
      'ja' => 'コンテンツにスキップ',
      'en' => 'Skip to content',
      // 'zh' => '跳到内容',  ← 追加時はこの1行を足すだけ
  ) ) );
  ```
- 追加言語コンテンツの実体: **通常のWP投稿/固定ページ**（ブロックエディタでそのまま執筆、ステータスは普通に`公開(publish)`のまま）。編集者から見ると、日本語記事を書くのと全く同じ操作でよい
- 対応付け: 追加言語側の投稿に post meta を2つ持たせる
  - `_kikujo_translation_of`: 日本語側の投稿ID
  - `_kikujo_lang`: その投稿自身の言語コード（`en`・`zh`等）— 1つの日本語記事に対して複数言語の翻訳がぶら下がる構造になるため、「どの言語の翻訳か」を区別するために必要
  - UIは追加開発せず、WP標準の「カスタムフィールド」パネル（投稿編集画面の「表示オプション」から有効化）で手動入力してもらう運用（新規メタボックスは作らない＝実装量を抑える）
- **重複URL・意図しない露出の防止**: 追加言語の投稿は`publish`のままなので、自分自身の素のパーマリンク（例: `/some-en-slug/`）も理論上は生きてしまう。これを、投稿ステータスではなく**`pre_get_posts`フィルタ1つ**で解決する:
  - `_kikujo_translation_of`メタを持つ投稿（＝どの言語であれ「翻訳側」の投稿）を、通常のフロント表示クエリ（日本語ホームの新着一覧・お知らせアーカイブ・検索・RSSフィード等、`WP_Query`を経由するもの全般）から一律除外する
  - この除外は「投稿本体を直接`get_post()`で取得する」独自ルーティング（後述）には影響しない
  - 副次効果として、翻訳投稿を素のパーマリンクで直接開こうとした場合、「スラッグ一致」条件と「除外」条件が両立せず**自然に404**になる。これにより「noindexメタタグ出力＋canonicalタグの上書き」という追加実装が丸ごと不要になる
  - 自前の言語別クエリ（英語お知らせ一覧など、意図的にその言語の投稿だけを取得したい箇所）は、`WP_Query`の引数に目印`'kikujo_lang' => '<言語コード>'`を渡すことでこの除外から除外する
  - EN検索(`/en/?s=...`)・ZH検索(`/zh/?s=...`)等では逆に「`_kikujo_lang`がそのリクエストの言語と一致する投稿のみ」に絞り込む
  - WPコア標準サイトマップ(`wp-sitemap.xml`)にも同じ除外条件を`wp_sitemaps_posts_query_args`フィルタで適用
  - `single.php`の「前の記事/次の記事」リンク（`get_previous_post()`/`get_next_post()`）は`WP_Query`を経由しないため上記フィルタが効かない。日本語記事から翻訳記事へ誤って遷移しないよう、`get_previous_post_where`/`get_next_post_where`フィルタで個別に除外する
- ルーティング: `functions.php`の`kikujo_force_custom_templates()`に、`kikujo_get_secondary_languages()`から動的に組み立てた正規表現で`/{lang}/...`配下のパターンを追加する（言語コードをハードコードしない）。マッチしたら対応する日本語コンテンツをslugで特定 → `_kikujo_translation_of`＋`_kikujo_lang`の逆引きでその言語の投稿を検索 → 見つかった場合は該当する共通テンプレート（下記）を返す。見つからない場合は何もせず`$template`をそのまま返す（＝WPが自然に404解決 → `404.php`がその言語で表示される）

## 新規ファイル

コンテンツ種別ごとに**共通テンプレートを1本ずつ**用意し、中身は`kikujo_current_lang()`と`kikujo_t()`で出し分ける（「英語ファイル」「中国語ファイル」のように言語ごとにファイルを増やさない — これが3言語目以降の追加をシンプルに保つための一番の勘所）。

| ファイル | 役割 |
|---|---|
| `inc/i18n.php` | `kikujo_get_languages()` / `kikujo_get_secondary_languages()` / `kikujo_current_lang()` / `kikujo_t()` / `kikujo_find_post_by_slug()` / `kikujo_find_translation( $ja_id, $lang )` / `kikujo_build_url_for_ja_post( $ja_post, $lang )` / `kikujo_translation_permalink( $translation_post_id )` / `kikujo_get_translation_urls()`（言語切替リンク一覧の算出）/ `pre_get_posts`フィルタ / `wp_sitemaps_posts_query_args`フィルタ / `get_previous_post_where`・`get_next_post_where`フィルタ / `wp_head`でのhreflang出力（登録言語ぶんループ） / `<html lang>`属性の出し分け（`language_attributes`フィルタ） |
| `home-i18n.php` | `/{lang}/` 用のトップページ。`front-page.php`と同じ構成（ヒーロー・サービス紹介・お知らせ抜粋・About・CTA）を1ファイルにまとめ、各文言を`kikujo_t()`で出し分け |
| `contact-i18n.php` | `/{lang}/contact/` 用。`page-contact.php`と同じ構造を`kikujo_t()`で出し分け、`Kikujo_Contact_Form`を`new Kikujo_Contact_Form( kikujo_current_lang() )`で呼び出す。`page-contact.php`自体は変更しない |
| `archive-news-i18n.php` | `/{lang}/news/` 用。`_kikujo_lang === 現在の言語`の投稿のみを`'kikujo_lang' => <言語コード>`付きの`WP_Query`で一覧表示。`archive-news.php`と同じAJAX「さらに読み込む」構成を流用 |
| `single-news-i18n.php` | `/{lang}/news/{slug}/` 用。ルーティング側で見つけた翻訳投稿オブジェクトを`setup_postdata()`して表示（`single.php`相当。タグ一覧・前後記事ナビは持たせない簡易版） |
| `page-i18n.php` | `/{lang}/{slug}/` 用（お知らせ・お問い合わせ以外の固定ページ全般）。同じ仕組みで日本語ページ→翻訳ページを解決 |

## 変更するファイル

- **`functions.php`**
  - `require_once get_template_directory() . '/inc/i18n.php';` を追加
  - `kikujo_force_custom_templates()` に、`kikujo_get_secondary_languages()`から組み立てた正規表現で以下の分岐を追加（既存の`/contact/`・`/news/`判定と同じ書き方で揃える。ホーム判定のみ`! is_search()`を条件に加え、`/{lang}/?s=...`との衝突を避ける）:
    - `^/({lang})/?$` → `home-i18n.php`
    - `^/({lang})/contact/?$` → `contact-i18n.php`
    - `^/({lang})/news/?$` → `archive-news-i18n.php`
    - `^/({lang})/news/([^/]+)/?$` → スラッグから日本語記事を特定し、対応する翻訳投稿があれば`single-news-i18n.php`（`$GLOBALS['kikujo_translation_post']`に格納）。無ければ`$template`をそのまま返す
    - `^/({lang})/([^/]+)/?$` → 同様に`page-i18n.php`
  - `register_nav_menus()`を、`kikujo_get_languages()`をループして`primary` / `primary_en` / `primary_zh`...、`footer` / `footer_en` / `footer_zh`...を動的に登録する形に変更（言語を1つ追加するとメニュー登録も自動で増える。実際のメニュー内容はwp-admin「メニュー」画面で編集者が言語ごとに作成）
  - `kikujo_load_more_posts()`（AJAX「さらに読み込む」）のリクエストパラメータ`lang`を、`en`固定の分岐ではなく`kikujo_get_languages()`に含まれる任意のコードとして扱うよう一般化

- **`header.php`**
  - `kikujo_current_lang()`に応じて`wp_nav_menu()`の`theme_location`を`primary` / `primary_{lang}`に切替
  - 3箇所のハードコード文字列を`kikujo_t()`でラップ
  - 言語切替リンクを`.header-inner`内に追加。`kikujo_get_translation_urls()`が返す「言語コード→URL（無ければnull）」の一覧をループして、URLがある言語だけをリンク表示する（2言語なら1本のリンク、3言語なら2本のリンクが自動的に並ぶ）

- **`footer.php`**
  - フッターナビゲーションのaria-labelを`kikujo_t()`でラップ、`wp_nav_menu()`の`theme_location`を`footer` / `footer_{lang}`に切替

- **`404.php`**
  - 4箇所のハードコード文字列を`kikujo_t()`でラップ

- **`index.php`**
  - 検索結果分岐（`is_search()`）の見出し・「続きを読む」・「記事が見つかりませんでした」等を`kikujo_t()`でラップ。絞り込み（追加言語での検索時はその言語の投稿のみ）は`inc/i18n.php`の`pre_get_posts`フィルタ側で対応するため、このファイル自体にクエリ変更は不要

- **`style.css`**
  - 言語切替リンク（複数並ぶ想定）用に`.lang-switch`クラスを追加。既存の`--spacing-*`/`--color-*`変数を使い、リンクが2本でも3本でも崩れないよう横並び・折り返し可能なレイアウトにする

- **`inc/contact-form.php`**
  - `Kikujo_Contact_Form`にコンストラクタ引数 `$lang = 'ja'` を追加し、プロパティとして保持（デフォルト値により`page-contact.php`からの既存の呼び出し `new Kikujo_Contact_Form()` は無改修で今まで通りJA動作）
  - 内部に `private function t( array $strings ) { return $strings[ $this->lang ] ?? $strings['ja']; }` を追加（`kikujo_t()`と同じ発想の連想配列版）
  - 洗い出し済みの約50箇所のハードコード日本語リテラル（バリデーションメッセージ・ボタン・メール件名/本文など）を機械的に`$this->t( array( 'ja' => ..., 'en' => ... ) )`へ置換。ロジック・分岐構造は一切変更しないため日本語側の挙動に回帰リスクは無い

### 変更しないファイル

- `front-page.php`（日本語トップはそのまま。`home-i18n.php`は別ファイル）
- `page-contact.php`（日本語お問い合わせはそのまま。`contact-i18n.php`は別ファイル）
- `archive.php` / `single.php` / `page.php`（追加言語専用の`page-i18n.php`・`single-news-i18n.php`が代わりに使われるため、これらのテンプレートが追加言語のコンテキストで呼ばれる場面が無い）

## SEO関連

- `<html lang="ja">`→`kikujo_current_lang()`の値に応じて動的に出し分け（`language_attributes`フィルタ、`inc/i18n.php`）
- 翻訳が存在するページには`wp_head`で、`kikujo_get_languages()`に登録された言語ぶん`hreflang`のalternateリンクをループ出力（`x-default`は常に日本語版を指す）
- `rel="canonical"`はWPコアが標準で各URLごとに自動出力するため追加対応不要（翻訳投稿の素のパーマリンクは上記の通り404化するため、そもそも重複canonicalの問題が発生しない）
- WPコア標準サイトマップ(`wp-sitemap.xml`)は`wp_sitemaps_posts_query_args`フィルタで翻訳投稿を除外。`/{lang}/...`配下のURL自体を専用サイトマップに載せる対応は今回見送り（小規模サイトでは内部リンク・hreflang経由のクロールで十分と判断。将来ページ数が増えたら専用サイトマッププロバイダの追加を検討）

## 言語を追加するときの手順（実装後の運用フロー）

3言語目以降を追加する際、コード変更が必要なのは`kikujo_get_languages()`への1行追加と、`kikujo_t()`呼び出し箇所への翻訳文字列追加（各箇所1行ずつ）のみ。新しいテンプレートファイルは不要。具体的な手順:

1. `inc/i18n.php`の`kikujo_get_languages()`に言語コードを追加
2. `header.php`・`footer.php`・`404.php`・`index.php`・`home-i18n.php`・`contact-i18n.php`・`archive-news-i18n.php`・`inc/contact-form.php`内の各`kikujo_t()`/`$this->t()`呼び出しに、その言語のキーを1行ずつ追加（漏れがあれば自動的に日本語がフォールバック表示されるだけなので、事故にはならない）
3. wp-adminの「メニュー」画面でその言語用の`primary_{lang}`・`footer_{lang}`メニューを作成
4. 翻訳したい既存の固定ページ・投稿について、新規に投稿を作成し`_kikujo_translation_of`（元の日本語投稿ID）と`_kikujo_lang`（言語コード）をカスタムフィールドで設定

## 既知の制約・今回のスコープ外

- ヒーロー背景画像・フッターバナー画像はどの言語でも共通（画像自体の言語依存テキストがなければ問題なし。バナー画像に日本語テキストが焼き込まれている場合は別途翻訳版画像の用意が必要 — 今回のコード実装スコープ外）
- 追加言語のコンテンツはCustomizerではなくテンプレート内`kikujo_t()`直書き/投稿データのため、将来「ヒーロー文言だけwp-adminから直接編集したい」となった場合は追加のCustomizer設定が必要（今回は見送り）
- 右から左に書く言語（アラビア語等）のレイアウト対応は今回のスコープ外（`dir="rtl"`等の追加対応が別途必要）

## 動作確認方法

開発環境のdocker-composeを一時起動し（GTM設置時と同じ手順）、以下をコンテナ内から`curl`で確認する:
1. `/` と `/en/` の両方が200で、`<html lang="ja">`/`<html lang="en">`が正しく出ること
2. `/en/`のヘッダーに`hreflang`が正しく出ること、ナビゲーションが`primary_en`メニューを参照していること
3. テスト用に日本語固定ページ1件・英語版1件（`_kikujo_translation_of`・`_kikujo_lang=en`設定済み、通常のpublish）を作成し、`/en/{slug}/`で正しく表示されること。翻訳投稿自身の素のパーマリンクに直接アクセスすると404になること。未作成のslugへ`/en/存在しないslug/`でアクセスし英語404になること
4. `/en/contact/`でお問い合わせフォームの各ステップ（OTP送信含む、`WP_DEBUG=1`のdev bypassで完了まで）が英語文言で一通り動作すること
5. `/en/news/`・`/en/news/{slug}/`が翻訳済み記事のみ表示すること、「さらに読み込む」が英語側でも正しく動作すること。日本語記事の「次の記事」ナビが翻訳記事に飛ばないこと
6. `/en/?s=...`が英語コンテンツのみに絞られ、日本語側の`/?s=...`には翻訳投稿が混ざらないこと
7. **3言語目の疎通確認**: `kikujo_get_languages()`に一時的にテスト用言語コード（例: `zh`）を追加し、`kikujo_t()`にキーを1つも足さない状態で`/zh/`にアクセスしても、フォールバックにより日本語表示のまま200で返る（＝新しい言語を追加してもエラーにならず、翻訳が無い箇所は安全にデフォルト言語へフォールバックする）ことを確認してから設定を元に戻す
8. 確認後はコンテナを停止し元の状態に戻す
