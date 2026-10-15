# notedot

업종에 묶이지 않는 범용 예약·고객관리 서비스.
미용실, 필라테스, 병원, 스터디룸 등 하나의 코드로 운영한다.

- 데모: https://kir1.cafe24.com/notedot/
- 스택: PHP 8.3, Laravel 13, MySQL 8, Blade + Tailwind + Alpine.js, Node.js(Socket.io)

## 로컬 실행

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
```
