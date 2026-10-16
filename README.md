# notedot

업종에 묶이지 않는 **범용 예약·고객관리 서비스**.
하나의 코드로 미용실, 필라테스, 스터디룸처럼 예약 방식이 다른 매장을 운영합니다.
업종에 맞는 말(디자이너·강사·공간)과 예약 방식(1:1·그룹·공간 대여)은 매장 설정으로 바뀝니다.

- **데모**: https://kir1.cafe24.com/notedot/
- **스택**: PHP 8.3 · Laravel 13 · MySQL 8 · Blade + Tailwind CSS 4 + Alpine.js · FullCalendar · Chart.js · Node.js(Socket.io)

## 데모 계정

비밀번호는 모두 `notedot1234` 입니다. 데모 데이터는 매일 04:00 에 처음 상태로 돌아갑니다.

| 매장 | 업종 | 예약 방식 | 관리자 | 손님 예약 화면 |
|---|---|---|---|---|
| 노트닷 헤어 | 미용 | 1:1 | `owner@notedot.test` (대표), `staff@notedot.test` (직원) | `/notedot/s/demo-hair` |
| 노트닷 필라테스 | 피트니스 | 1:1 + 그룹(정원제) | `pilates@notedot.test` | `/notedot/s/demo-pilates` |
| 노트닷 스터디룸 | 공간 대여 | 공간(시간 단위) | `studyroom@notedot.test` | `/notedot/s/demo-studyroom` |

데모에서는 실제 결제·문자 발송·비밀번호 변경·직원 삭제가 막혀 있습니다. 보증금 결제는 데모 결제창으로 끝까지 해 볼 수 있습니다.

## 주요 기능

| 영역 | 내용 |
|---|---|
| 예약 캘린더 | 담당자(resource)별 보기, 드래그로 시간 이동, 1:1·그룹 일정·공간 예약, 상태(대기·확정·완료·노쇼·취소), 변경 이력 |
| 손님 예약 (모바일) | 상품 → 담당자/공간/일정 → 날짜·시간 → 정보 입력. 근무표·쉬는 시간·휴무·기존 예약·정리 시간을 빼서 가능한 시간만 표시 |
| 실시간 | 예약이 생기면 관리자 캘린더가 새로고침 없이 바뀌고 알림이 뜬다. 손님이 보던 시간이 잡히면 바로 "마감" |
| 보증금 결제 | 포트원 V2 연결(데모 결제창으로 대체 가능), 서버 금액 검증, 웹훅 서명 확인, 15분 미결제 자동 취소, 취소 규정에 따른 환불 |
| 고객 관리 | 방문 이력·메모·노쇼, 회원권·정액권(횟수형·금액형) 발급·차감·사용 취소, 예약 완료 포인트 적립 |
| 알림 | 예약 확정·취소, 전날 리마인드(스케줄러), 큐 발송, 발송 기록·다시 보내기, 솔라피 연결(키가 없으면 기록만) |
| 통계 | 기간별 매출 추이, 담당자별 실적·가동률, 상품별 매출 비중, 재방문율·노쇼율·객단가, 예약 경로 |
| 권한 | 대표(전체) / 직원(자기 담당 예약·고객만, 매출 숨김). 모든 조회는 매장(`shop_id`) 범위 안에서만 |

## 설계에서 신경 쓴 점

### 1. 업종 추상화
코드는 `resource`(예약이 걸리는 대상), `service`(상품), `customer`(예약자)라는 중립적인 말만 씁니다.
화면 문구는 `term('resource')` 헬퍼가 매장 설정 → 업종 프리셋(`config/industries.php`) 순서로 찾아서 "디자이너", "강사", "공간"으로 바꿉니다.

### 2. 중복 예약·정원 초과 방지
같은 순간 같은 시간에 예약이 두 건 들어와도 하나만 성공해야 합니다.
- 트랜잭션 안에서 담당자(resource) 행을 `SELECT ... FOR UPDATE`로 잠근 뒤 겹침을 검사합니다. 잠금 순서는 언제나 `resource → 그룹 일정 → 예약`입니다.
- 그룹 수업은 일정(`class_sessions`) 행을 잠그고 `신청 인원 합 + 내 인원 ≤ 정원`을 확인합니다.
- 겹침 조건: `기존.시작 < 새.끝(정리 시간 포함) AND 기존.끝(정리 시간 포함) > 새.시작`
- 검증: 프로세스 8개를 동시에 띄워(`pcntl_fork`) 같은 시간을 잡게 하는 테스트를 넣었습니다. 잠금을 빼면 8건이 모두 저장되고, 잠금이 있으면 정확히 1건(그룹은 정원만큼)만 저장됩니다. 회원권 차감과 결제 확인도 같은 방식으로 검증합니다.

### 3. 화면을 믿지 않는다
- 손님이 고른 시간은 저장 직전에 서버가 `SlotService`로 다시 계산합니다.
- 결제 금액은 서버가 예약을 만들 때 정해 둔 값만 씁니다. 결제 결과는 PG에 직접 조회해서 금액·상점을 대조하고, 다르면 받은 돈을 돌려주고 실패 처리합니다.
- 웹훅은 HMAC 서명과 시각(5분)을 확인한 뒤에도 내용을 믿지 않고 PG에 다시 조회합니다.

### 4. 사건(Event)으로 기능 붙이기
예약이 바뀌면 `ReservationChanged` 사건 하나만 발생합니다. 사건은 트랜잭션이 commit 된 뒤에만 나갑니다(`ShouldDispatchAfterCommit`).
듣는 쪽은 둘입니다.
- 실시간 화면 갱신 → `PushToRealtime`
- 고객 알림 → `SendReservationNotification`

예약 코드를 고치지 않고 기능을 붙였습니다.

### 5. 외부 서비스는 인터페이스로
| 인터페이스 | 구현 | 키가 없을 때 |
|---|---|---|
| `RealtimePublisher` | Node 서버로 HTTP POST | 화면이 10초마다 다시 불러오기 |
| `PaymentGateway` | 포트원 V2 | 데모 결제창 |
| `MessageSender` | 솔라피 (HMAC 인증) | 발송 기록만 저장 |

`.env` 값만으로 바뀌고, 테스트는 가짜 HTTP 응답(`Http::fake`)으로 실제 연결 코드를 검증합니다.

### 6. 돈과 잔액은 "거래 내역 + 잔액"
회원권(`pass_transactions`)과 포인트(`point_transactions`)는 거래를 쌓기만 하고 고치지 않습니다. 잔액은 언제나 거래 합과 같습니다.
포인트 적립은 "있어야 할 값과 지금 값의 차이"만 기록하는 방식(`PointService::sync`)이라, 상태가 여러 번 바뀌어도 두 번 적립되지 않습니다.

## 구조

```
app/
 ├─ Http/Controllers/Admin/   관리자 (캘린더, 예약·일정 API, 고객, 회원권, 통계, 알림, 설정)
 ├─ Http/Controllers/Shop/    손님 (예약 화면, 시간·일정 조회, 예약 저장, 보증금 결제)
 ├─ Services/                 비즈니스 로직 — 컨트롤러에는 두지 않는다
 │   ├─ ReservationService     예약 만들기·수정·이동·상태 (겹침·정원 검사)
 │   ├─ AvailabilityService    담당자 잠금 + 빈 시간 확인 (1:1·그룹 공용)
 │   ├─ ClassSessionService    그룹 일정 (반복 생성·이동·취소)
 │   ├─ SlotService            손님 화면의 예약 가능 시간 계산
 │   ├─ OnlineBookingService   손님 예약 (상관없음 배정, 동의 저장, 결제 시작)
 │   ├─ PaymentService         보증금 결제 확인·시간 초과·환불
 │   ├─ PassService, PointService   회원권·포인트
 │   ├─ NotificationService    알림 고르기·발송·리마인드
 │   └─ StatsService           통계
 ├─ Contracts/                RealtimePublisher, PaymentGateway, MessageSender
 ├─ Events/, Listeners/, Jobs/
 └─ Support/term.php          업종 용어 헬퍼
routes/admin.php (/admin, guard: admin) · routes/shop.php (/s/{shop_slug}) · routes/console.php (스케줄)
resources/views/admin, shop       관리자(PC·태블릿) / 손님(모바일 375px)
realtime/server.js                Node 실시간 서버 (Socket.io + /publish)
```

요청 흐름 예시:
```
POST /s/demo-hair/booking → BookingController@store → OnlineBookingService::book
  → SlotService::isBookable → ReservationService::create (resource 잠금 → 겹침 검사 → 저장)
  → ReservationChanged (commit 뒤) → Node 서버 → 관리자 캘린더 갱신 / 알림 큐
```

## DB

| 표 | 내용 |
|---|---|
| `shops` | 매장 (업종, 용어 덮어쓰기, 영업시간, 예약 단위, 적립률, 취소 규정, 알림 설정, 계절 테마) |
| `staffs` | 관리자 로그인 계정 (대표/직원, 연결된 resource) |
| `resources` · `resource_service` · `resource_schedules` | 예약 대상(사람·공간), 담당 상품, 근무표·휴무·특별 근무 |
| `services` | 상품 (예약 방식, 소요·정리 시간, 정원, 가격, 보증금) |
| `customers` | 예약자 (개인정보·마케팅 동의 분리, 포인트, 노쇼 횟수) |
| `reservations` · `reservation_items` | 예약, 예약 당시 상품 복사본 |
| `class_sessions` | 그룹 수업 시간표 (정원) |
| `payments` | 보증금 결제·환불 |
| `passes` · `pass_transactions` · `point_transactions` | 회원권·정액권과 거래 내역, 포인트 내역 |
| `notification_logs` | 알림 발송 기록 (중복 방지 키) |
| `audit_logs` | 누가 언제 무엇을 바꿨는지 |

금액은 정수(원), 삭제는 soft delete, 시간대는 `Asia/Seoul`.

## 로컬 실행

```bash
composer install
npm install && npm run build
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed            # 데모 매장 3곳·예약·회원권

php artisan serve              # 웹
php artisan queue:work         # 알림 발송 (큐)
php artisan schedule:work      # 결제 대기 정리(1분)·리마인드(10분)·데모 초기화(04:00)

# 실시간 (선택. 없으면 REALTIME_DRIVER=poll 로 10초 폴링)
cd realtime && npm install && node server.js
```

주요 환경 변수 (`.env.example` 참고):

| 변수 | 값 |
|---|---|
| `DEMO_MODE` | `true`면 데모 안내 화면·계정 표시·위험한 변경 차단·매일 초기화 |
| `REALTIME_DRIVER` | `node` / `poll` (+ `REALTIME_SECRET`, `REALTIME_JWT_SECRET`) |
| `PAYMENT_DRIVER` | `demo` / `portone` (+ `PORTONE_*`) |
| `NOTIFICATION_DRIVER` | `log` / `solapi` (+ `SOLAPI_*`) |

## 테스트

```bash
php artisan test
```

테스트 전용 DB(`notedot_test`)를 씁니다. 다른 DB를 가리키면 실행 전에 멈춥니다.

| 범위 | 테스트 |
|---|---|
| 동시 요청 | 같은 시간 동시 예약 → 1건만, 그룹 정원 → 정원만큼만, 회원권 동시 차감, 결제 동시 확인 |
| 예약 가능 시간 | 근무·쉬는 시간·휴무·특별 근무·정리 시간·그룹 일정·최소 준비 시간 |
| 예약·권한 | 겹침, 상태 변경, 매장 범위, 직원 권한, 로그인 제한 |
| 결제 | 데모·포트원 응답, 금액 위조, 웹훅 서명, 시간 초과, 취소 규정 환불 |
| 회원권·포인트·알림·통계·실시간 | 각 기능별 |

## 배포 (하위 경로)

`https://kir1.cafe24.com/notedot/` 처럼 도메인의 하위 경로에서 동작합니다.

- **nginx:** `location ^~ /notedot/`에서 `alias .../public/`로 연결하고, `SCRIPT_NAME`을 `/notedot/index.php`로 넘깁니다.
- **실시간 서버:** `/notedot/socket.io/`만 Node(127.0.0.1)로 프록시합니다. Node의 `/publish`는 밖으로 열지 않습니다.
- **`.env`:** `APP_URL=https://kir1.cafe24.com/notedot`, `SESSION_PATH=/notedot`(같은 도메인의 다른 서비스와 쿠키 분리)
- **상시 실행:** cron에서 `schedule:run`을 1분마다 돌리고, systemd로 `queue:work`와 `realtime/server.js`를 띄웁니다.
- **URL 생성:** 모든 URL은 `url()` · `route()` · `asset()`로만 만듭니다.
