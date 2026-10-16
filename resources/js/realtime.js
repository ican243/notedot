// 실시간 받기: Node 서버(Socket.io)에 접속해서 이벤트를 받는다. 접속이 안 되거나 poll 방식이면 몇 초마다 refresh() 로 대신한다
// cfg = 서버가 넘긴 설정 { driver: 'node'|'poll', path, tokenUrl, pollSeconds }
// handlers = { events: { '이벤트이름': fn }, refresh: fn }
import { api } from './api';

export function listen(cfg, handlers) {
    let timer = null;

    const startPolling = () => {
        if (timer) return;
        timer = setInterval(() => {
            if (document.visibilityState === 'visible') handlers.refresh();
        }, (cfg.pollSeconds || 10) * 1000);
    };
    const stopPolling = () => {
        clearInterval(timer);
        timer = null;
    };

    if (!cfg || cfg.driver !== 'node') {
        startPolling();
        return;
    }

    // socket.io 클라이언트는 node 방식일 때만 불러온다 (poll 방식이면 내려받지 않음)
    import('socket.io-client').then(({ io }) => {
        let connectedOnce = false;
        const socket = io({
            path: cfg.path,
            // 접속(재접속)할 때마다 새 출입증을 받아서 낸다 (출입증 유효 시간이 짧다)
            auth: (cb) => api('GET', cfg.tokenUrl).then((r) => cb({ token: r.token })).catch(() => cb({})),
        });

        Object.entries(handlers.events || {}).forEach(([name, fn]) => socket.on(name, fn));

        socket.on('connect', () => {
            stopPolling();
            // 끊긴 사이에 바뀐 것을 놓쳤을 수 있으므로 다시 접속하면 한 번 새로 불러온다
            if (connectedOnce) handlers.refresh();
            connectedOnce = true;
        });
        socket.on('disconnect', startPolling);
        socket.on('connect_error', startPolling);
    }).catch(startPolling);
}

// 짧은 시간에 여러 번 와도 한 번만 실행
export function debounce(fn, ms = 300) {
    let t = null;
    return (...args) => {
        clearTimeout(t);
        t = setTimeout(() => fn(...args), ms);
    };
}
