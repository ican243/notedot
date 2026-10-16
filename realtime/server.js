// notedot 실시간 서버 (Node + Socket.io)
// Laravel → POST /publish (공유 시크릿 헤더) → 매장 룸으로 emit → 열려 있는 화면(관리자 캘린더, 고객 예약 화면)이 받는다
//
// 룸 2개 (매장마다)
//   shop:{id}         관리자 화면. 로그인한 직원에게 Laravel 이 발급한 출입증(JWT, aud=admin)
//   shop:{id}:public  고객 예약 화면. 누구나 받을 수 있는 출입증(aud=public) → 고객 정보 없이 "시간이 바뀌었다"만 받는다
import http from 'node:http';
import { timingSafeEqual } from 'node:crypto';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { Server } from 'socket.io';
import jwt from 'jsonwebtoken';

// 비밀값은 Laravel 과 같은 .env 하나에서 읽는다 (REALTIME_ 으로 시작하는 줄만. 이미 환경 변수로 받은 값이 우선)
function loadRealtimeEnv(file) {
    let text = '';
    try {
        text = readFileSync(file, 'utf8');
    } catch {
        return;
    }
    for (const line of text.split(/\r?\n/)) {
        const m = line.match(/^\s*(REALTIME_[A-Z_]+)\s*=\s*(.*?)\s*$/);
        if (m && process.env[m[1]] === undefined) process.env[m[1]] = m[2].replace(/^(['"])(.*)\1$/, '$2');
    }
}
loadRealtimeEnv(process.env.REALTIME_ENV_FILE || fileURLToPath(new URL('../.env', import.meta.url)));

const PORT = Number(process.env.REALTIME_PORT || 3100);
const SECRET = process.env.REALTIME_SECRET || '';
const JWT_SECRET = process.env.REALTIME_JWT_SECRET || '';
const AUDIENCES = ['admin', 'public'];
const MAX_BODY = 16 * 1024;

if (SECRET.length < 32 || JWT_SECRET.length < 32) {
    console.error('REALTIME_SECRET / REALTIME_JWT_SECRET 이 없거나 너무 짧습니다 (32자 이상).');
    process.exit(1);
}

const roomOf = (shopId, audience) => (audience === 'admin' ? `shop:${shopId}` : `shop:${shopId}:public`);

// 길이가 달라도 시간 차이로 비밀값이 새지 않게 비교
function sameSecret(given) {
    const a = Buffer.from(String(given || ''));
    const b = Buffer.from(SECRET);
    return a.length === b.length && timingSafeEqual(a, b);
}

function reply(res, status, body = null) {
    res.writeHead(status, { 'Content-Type': 'application/json' });
    res.end(body ? JSON.stringify(body) : '');
}

// Laravel 이 보내는 알림 받기: { shop_id, audience, event, data }
function publish(req, res) {
    if (!sameSecret(req.headers['x-realtime-secret'])) return reply(res, 401, { error: 'unauthorized' });

    let size = 0;
    const chunks = [];
    req.on('data', (chunk) => {
        size += chunk.length;
        if (size > MAX_BODY) {
            reply(res, 413, { error: 'too large' });
            req.destroy();
            return;
        }
        chunks.push(chunk);
    });
    req.on('end', () => {
        if (res.writableEnded) return;
        let msg;
        try {
            msg = JSON.parse(Buffer.concat(chunks).toString('utf8'));
        } catch {
            return reply(res, 400, { error: 'invalid json' });
        }

        const shopId = Number(msg.shop_id);
        if (!Number.isInteger(shopId) || shopId < 1 || !AUDIENCES.includes(msg.audience)
            || typeof msg.event !== 'string' || !/^[a-z]+\.[a-z]+$/.test(msg.event)) {
            return reply(res, 422, { error: 'invalid message' });
        }

        io.to(roomOf(shopId, msg.audience)).emit(msg.event, msg.data ?? {});
        reply(res, 204);
    });
}

// Socket.io 가 처리하지 않는 주소 (/socket.io/ 외)
const server = http.createServer((req, res) => {
    if (req.method === 'POST' && req.url === '/publish') return publish(req, res);
    if (req.method === 'GET' && req.url === '/health') return reply(res, 200, { ok: true, clients: io.engine.clientsCount });
    reply(res, 404, { error: 'not found' });
});

// 같은 주소(nginx /notedot/socket.io/ → 여기 /socket.io/)로만 접속하므로 CORS 는 열지 않는다
const io = new Server(server, { serveClient: false });

// 접속할 때 출입증 확인 → 매장 룸에 넣기. 출입증이 없거나 틀리면 접속 거절
io.use((socket, next) => {
    try {
        const claims = jwt.verify(socket.handshake.auth?.token || '', JWT_SECRET, {
            algorithms: ['HS256'],
            issuer: 'notedot',
            audience: AUDIENCES,
        });
        if (!Number.isInteger(claims.shop)) throw new Error('no shop');
        socket.data.shopId = claims.shop;
        socket.data.audience = claims.aud;
        next();
    } catch {
        next(new Error('unauthorized'));
    }
});

io.on('connection', (socket) => {
    socket.join(roomOf(socket.data.shopId, socket.data.audience));
});

// 서버 안에서만 열기 (밖에서는 nginx 를 거쳐 /socket.io/ 만 들어온다)
server.listen(PORT, '127.0.0.1', () => {
    console.log(`notedot realtime listening on 127.0.0.1:${PORT}`);
});

// systemd 가 멈출 때 접속을 정리하고 끝내기
process.on('SIGTERM', () => io.close(() => process.exit(0)));
