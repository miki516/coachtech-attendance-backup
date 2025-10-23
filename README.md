# アプリケーション名

coachtech 勤怠管理アプリ

## 使用技術（実行環境）

- PHP 8.x
- Laravel 8.x
- MySQL 8.x
- Nginx
- Docker / docker-compose

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

### 2. PHP コンテナに入ってセットアップ

```bash
docker compose exec php bash

# 依存関係
composer install

# .env 作成 & APP_KEY 生成
cp .env.example .env
php artisan key:generate
```

※`.env.example` はセキュリティのためキーを空にしてあります。
自分の環境でキーを作って設定してください。

### 3. マイグレーション & シーディング実行

```bash
# php コンテナ内で実行
php artisan migrate --seed
```

## ER 図

![ER図](./er.png)

## URL

- 開発環境：http://localhost/
- 一般ユーザーログイン: http://localhost/login
- 管理者ログイン: http://localhost/admin/login
- MailHog UI: http://localhost:8025

## ログインアカウント

### 管理者

- **name**: Admin User
- **email**: admin@example.com
- **password**: password

### 一般ユーザー

- **name**: Test User
- **email**: user@example.com
- **password**: password

## メール送信の確認方法

本プロジェクトでは MailHog を使用しています。`docker compose up -d` で MailHog コンテナも起動します。  
ブラウザで http://localhost:8025 にアクセスするとメールを確認できます。また、`認証はこちらから` ボタンで MailHog を開くために `.env` に以下を設定してください。

```env
MAIL_CLIENT_URL=http://localhost:8025
```

## テストの実行方法

`php artisan test` は `.env.testing` を自動読込します。
実行前に **テスト DB 作成 → migrate:fresh --seed（--env=testing）** を行ってください。

### 事前準備

#### .env.testing の作成

`.env.testing` はリポジトリに含めていません。以下の設定をもとに `.env` と同階層に作成してください。  
※ `.env.testing` の例（Docker の MySQL サービス名が `mysql` の場合）：

```bash
# ========== App ==========
APP_NAME=CoachtechAttendance
APP_ENV=testing
# ↓ `php artisan key:generate --show` の結果を貼り付け
APP_KEY=base64:PASTE_YOUR_TESTING_KEY_HERE
APP_DEBUG=true
APP_URL=http://localhost

# ========== Logging ==========
LOG_CHANNEL=stack
LOG_LEVEL=debug

# ========== Database (Testing) ==========
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=coachtech_attendance_test
DB_USERNAME=root
DB_PASSWORD=root

# ========== Cache / Queue / Session ==========
CACHE_DRIVER=array
SESSION_DRIVER=array
QUEUE_CONNECTION=sync

# ========== Mail (テストでは送信しない) ==========
MAIL_MAILER=log
MAIL_FROM_ADDRESS="test@example.com"
MAIL_FROM_NAME="${APP_NAME}"

# ========== Filesystem ==========
FILESYSTEM_DRIVER=local

```

#### APP_KEY の作成（値をコピーして .env.testing に貼り付け）

```bash
php artisan key:generate --show
```

#### テスト用 DB 作成（未作成の場合）

```bash
# root の例
docker compose exec mysql mysql -u root -p -e \
"CREATE DATABASE coachtech_attendance_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# laravel_user を使う例（docker-compose の設定に合わせて）
docker compose exec mysql mysql -u laravel_user -plaravel_pass -e \
"CREATE DATABASE coachtech_attendance_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

#### テスト環境用マイグレーション＆シーディング

```bash
php artisan migrate:fresh --seed --env=testing
```

#### テスト実行

```bash
# PHPコンテナに入ってから
php artisan test
# または
vendor/bin/phpunit
```

## 動作確認方法

日をまたいだ勤怠ボタン（出勤ボタンの再表示）を確認する場合は、
以下のように `Carbon::setTestNow()` を使用して日付を指定してください。

```php
use Illuminate\Support\Carbon;
Carbon::setTestNow('2023-06-02 08:00:00');
```
