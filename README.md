# アプリケーション名

coachtech 勤怠管理アプリ

## 環境構築

### 0. リポジトリのクローン

```bash
git clone <リポジトリURL>
cd プロジェクト名
```

### 1. Docker コンテナのビルド・起動

```bash
docker compose up -d --build
```

### 2. PHP コンテナに入って依存関係をインストール

```bash
docker compose exec php bash
composer install
cp .env.example .env
php artisan key:generate
```

### 3. .env ファイルを作成

```bash
cp .env.example .env
```
### 4. APP_KEY を生成

`.env.example` はセキュリティのためキーを空にしてあります。
自分の環境でキーを作って設定してください。

```bash
docker compose exec php php artisan key:generate --ansi
```

### 5. マイグレーション & シーディング実行

```bash
docker compose exec php php artisan migrate --seed
```

## 使用技術（実行環境）

- PHP 8.x
- Laravel 8.x
- MySQL 8.x
- Nginx
- Docker / docker-compose

## ER 図

![ER図](./er.png)

## URL

- 開発環境：http://localhost/

## ログインアカウント

### 管理者ユーザー

- **name**: Admin User
- **email**: admin@example.com
- **password**: password

### 一般ユーザー

- **name**: Test User
- **email**: user@example.com
- **password**: password

## 画像について

サンプル画像を `storage/app/public/products/` に含めています。
初回は以下のコマンドでシンボリックリンクを作成してください。

```bash
php artisan storage:link
```

## メール送信の確認方法

本プロジェクトでは MailHog を使用しています。
`docker compose up -d` で MailHog コンテナも起動します。
ブラウザで http://localhost:8025 にアクセスするとメールを確認できます。
また、`認証はこちらから` ボタンで MailHog を開くために `.env` に以下を設定してください。

```env
MAIL_CLIENT_URL=http://localhost:8025
```

## テストの実行方法

以下のコマンドで PHPUnit テストを実行できます。

```bash
# PHPコンテナに入ってから
php artisan test
# または
vendor/bin/phpunit
```

テスト時には `.env.testing` が自動的に読み込まれます。
テスト環境の設定は `.env.testing` に記載済みです。

## 動作確認方法

日をまたいだ勤怠ボタン（出勤ボタンの再表示）を確認する場合は、
以下のように `Carbon::setTestNow()` を使用して日付を指定してください。

```php
use Illuminate\Support\Carbon;
Carbon::setTestNow('2023-06-02 08:00:00'); // 翌日を再現
```